<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\Program;
use App\Models\Student;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ReportController extends Controller
{
    /**
     * No mockup exists for this screen in the prototype - built around
     * what's actually reportable from real data already in the system:
     * a per-program breakdown on-screen, plus two CSV exports.
     */
    public function index(): View
    {
        $campusId = auth()->user()->campus_id;

        // Pre-deployment requirements only (live, required, pre-deployment stage).
        $preDeploymentIds = \App\Support\PreDeployment::requirementIds($campusId);
        $requiredDocsCount = $preDeploymentIds->count();

        $incompleteDocsSql = \App\Support\PreDeployment::incompleteSql($preDeploymentIds);

        $programs = Program::where('campus_id', $campusId)->orderBy('name')->get();

        $programStats = $programs->map(function ($program) use ($requiredDocsCount, $incompleteDocsSql) {
            $base = fn () => Student::where('program_id', $program->id);

            return [
                'program' => $program,
                'total' => $base()->count(),
                'deployed' => $base()->whereHas(
                    'currentInternship.deployment',
                    fn ($q) => $q->where('status', 'active')
                )->count(),
                'pending' => $base()->where(fn ($q) => $q
                    ->whereDoesntHave('currentInternship.deployment')
                    ->orWhereHas('currentInternship.deployment', fn ($qq) => $qq->where('status', 'pending'))
                )->count(),
                'incomplete' => $requiredDocsCount > 0
                    ? $base()->whereRaw($incompleteDocsSql)->count()
                    : 0,
            ];
        });

        $hteCount = Hte::where('campus_id', $campusId)->count();
        $activeMoaCount = Hte::where('campus_id', $campusId)->where('moa_status', 'active')->count();

        return view('coordinator.reports.index', compact('programStats', 'hteCount', 'activeMoaCount'));
    }

    /**
     * Every enrolled student, one row each - the same data as the Intern
     * List table, without pagination, as a downloadable CSV.
     */
    public function exportInternRoster(): StreamedResponse
    {
        $campusId = auth()->user()->campus_id;

        $students = Student::with(['user', 'program', 'currentInternship.deployment.hte'])
            ->where('campus_id', $campusId)
            ->get();

        $callback = function () use ($students) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name', 'ID Number', 'Program', 'Host Training Establishment',
                'Deployment Status', 'Hours Rendered', 'Required Hours',
            ]);

            foreach ($students as $student) {
                $deployment = $student->currentInternship?->deployment;

                fputcsv($handle, [
                    $student->user->name,
                    $student->user->id_number,
                    $student->program->code,
                    $deployment?->hte?->name ?? '',
                    $deployment?->status ? ucfirst($deployment->status) : 'Not deployed',
                    $student->hours_rendered,
                    $student->program->required_hours,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="intern-roster-'.now()->format('Y-m-d').'.csv"',
        ]);
    }


    /**
     * Download one ZIP containing all approved student documents for the
     * coordinator's campus. Only documents with status=approved and an
     * existing uploaded file are included. A manifest is also added so the
     * coordinator can see every approved record, including any approved
     * record whose file is not currently present on storage.
     */
    public function downloadApprovedDocuments(): BinaryFileResponse
    {
        $campusId = auth()->user()->campus_id;

        $documents = \App\Models\Document::with([
                'requirement',
                'internship.student.user',
                'internship.student.program',
            ])
            ->where('status', 'approved')
            ->whereHas('internship', fn ($q) => $q->where('campus_id', $campusId))
            ->orderBy('submitted_at')
            ->get();

        $zipPath = tempnam(sys_get_temp_dir(), 'internlink-approved-docs-');
        if ($zipPath === false) {
            abort(500, 'Unable to prepare the document archive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            abort(500, 'Unable to create the document archive.');
        }

        $manifest = [
            ['Student', 'Student ID', 'Program', 'Document Type', 'Status', 'Uploaded File', 'Included in ZIP'],
        ];

        foreach ($documents as $document) {
            $student = $document->internship?->student;
            $studentName = $student?->user?->name ?? 'Unknown Student';
            $studentId = $student?->user?->id_number ?? (string) ($student?->id ?? '');
            $program = $student?->program?->code ?? '';
            $documentType = $document->requirement?->name ?? 'Document';

            $safeStudent = preg_replace('/[^A-Za-z0-9 _.-]+/', '', $studentName) ?: 'Unknown Student';
            $safeStudent = trim($safeStudent);
            $safeType = preg_replace('/[^A-Za-z0-9 _.-]+/', '', $documentType) ?: 'Document';
            $originalName = $document->original_filename ?: basename((string) $document->file_path);
            $safeOriginal = preg_replace('/[^A-Za-z0-9 _.-]+/', '', $originalName) ?: ('document-' . $document->id);

            $included = false;

            if (filled($document->file_path) && Storage::exists($document->file_path)) {
                $contents = Storage::get($document->file_path);
                $entryName = $safeStudent . '/' . $safeType . '/' . sprintf('%06d-', $document->id) . $safeOriginal;
                $zip->addFromString($entryName, $contents);
                $included = true;
            }

            $manifest[] = [
                $studentName,
                $studentId,
                $program,
                $documentType,
                $document->status,
                $originalName,
                $included ? 'Yes' : 'No - file not found on storage',
            ];
        }

        $manifestCsv = '';
        foreach ($manifest as $row) {
            $stream = fopen('php://temp', 'r+');
            fputcsv($stream, $row);
            rewind($stream);
            $manifestCsv .= stream_get_contents($stream);
            fclose($stream);
        }

        $zip->addFromString('MANIFEST.csv', $manifestCsv);
        $zip->close();

        return response()->download(
            $zipPath,
            'approved-student-documents-' . now()->format('Y-m-d') . '.zip',
            ['Content-Type' => 'application/zip']
        )->deleteFileAfterSend(true);
    }

    /**
     * Every partner HTE with MOA status and intern counts, as CSV.
     */
    public function exportHtePartnership(): StreamedResponse
    {
        $campusId = auth()->user()->campus_id;

        $htes = Hte::where('campus_id', $campusId)
            ->withCount([
                'deployments as active_interns_count' => fn ($q) => $q->where('status', 'active'),
                'deployments as total_interns_count',
            ])
            ->orderBy('name')
            ->get();

        $callback = function () use ($htes) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'HTE Name', 'Address', 'Contact Person', 'Contact Email', 'Contact Phone',
                'MOA Status', 'Active Interns', 'Total Interns Placed',
            ]);

            foreach ($htes as $hte) {
                fputcsv($handle, [
                    $hte->name,
                    $hte->address,
                    $hte->contact_person,
                    $hte->contact_email,
                    $hte->contact_phone,
                    ucfirst($hte->moa_status),
                    $hte->active_interns_count,
                    $hte->total_interns_count,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="hte-partnership-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * One row per intern on the campus, one column per required document,
     * showing each document's submission status.
     */
    public function exportDocumentsCompliance(): StreamedResponse
    {
        $campusId = auth()->user()->campus_id;

        $requirements = DocumentRequirement::where('campus_id', $campusId)
            ->where('is_required', true)
            ->orderBy('stage')
            ->orderBy('name')
            ->get();

        $students = Student::with(['user', 'program', 'currentInternship.documents'])
            ->where('campus_id', $campusId)
            ->get()
            ->sortBy(fn ($student) => [$student->program?->code, $student->user->name]);

        $callback = function () use ($students, $requirements) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, array_merge(
                ['Name', 'ID Number', 'Program'],
                $requirements->pluck('name')->all()
            ));

            foreach ($students as $student) {
                $docsByRequirement = $student->currentInternship?->documents->keyBy('document_requirement_id') ?? collect();

                $row = [$student->user->name, $student->user->id_number, $student->program?->code ?? ''];

                foreach ($requirements as $requirement) {
                    $status = $docsByRequirement->get($requirement->id)?->status;
                    $row[] = $status ? ucfirst(str_replace('_', ' ', $status)) : 'Missing';
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return $this->streamCsv($callback, 'documents-compliance');
    }

    private function streamCsv(callable $callback, string $filenamePrefix): StreamedResponse
    {
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filenamePrefix.'-'.now()->format('Y-m-d').'.csv"',
        ]);
    }
}
