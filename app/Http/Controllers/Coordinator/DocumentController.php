<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\DocumentRequirement;
use App\Models\Program;
use App\Models\Student;
use App\Support\EvaluationRouting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class DocumentController extends Controller
{
    /**
     * Figure 6 - Document Tracking: five tracked items per student -
     * Endorsement Letter, MOA Status, Latest DTR, Narrative Report, and
     * Evaluation Form - each pulled from its real source table rather
     * than a single generic "documents" loop, since MOA and DTR aren't
     * document uploads.
     */
    public function index(Request $request): View
    {
        $students = $this->filteredStudents($request)->paginate(20)->withQueryString();
        $students->getCollection()->transform(fn ($student) => $this->withDocsByCode($student));

        $programs = Program::where('campus_id', auth()->user()->campus_id)->orderBy('name')->get();

        [$hteEvaluations, $hteEvalCounts, $hteEvalFilter] = $this->hteEvaluationForms($request);

        return view('coordinator.documents.index', compact('students', 'programs', 'hteEvaluations', 'hteEvalCounts', 'hteEvalFilter'));
    }

    /**
     * HTE Evaluation Forms - the tracking panel for Form 23b (Evaluation
     * Instrument of PSU Partner Agencies, Self Ratee). Each host training
     * establishment submits one per completed student deployment; the
     * Coordinator sees every one of them here, submitted or still outstanding.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array<string,int>, 2: string}
     */
    private function hteEvaluationForms(Request $request): array
    {
        $campusId = auth()->user()->campus_id;

        $requirement = DocumentRequirement::where('campus_id', $campusId)
            ->where('code', 'PARTNER_AGENCY_EVAL_SELF')
            ->where('is_active', true)
            ->first();

        if (! $requirement) {
            return [collect(), ['all' => 0, 'submitted' => 0, 'pending' => 0], 'all'];
        }

        $rows = Deployment::where('status', 'completed')
            ->whereHas('internship', fn ($q) => $q->where('campus_id', $campusId))
            ->with([
                'hte',
                'internship.student.user',
                'internship.student.program',
                'internship.documents' => fn ($q) => $q->where('document_requirement_id', $requirement->id)->with('uploader'),
            ])
            ->get()
            ->map(function ($deployment) {
                $document = $deployment->internship->documents->first();

                return (object) [
                    'deployment' => $deployment,
                    'hte' => $deployment->hte,
                    'student' => $deployment->internship->student,
                    'document' => EvaluationRouting::isSubmitted($document) ? $document : null,
                ];
            })
            // Submitted first (newest first), then the outstanding ones by HTE name.
            ->sortBy(fn ($row) => [
                $row->document ? 0 : 1,
                $row->document ? -($row->document->submitted_at?->timestamp ?? 0) : 0,
                $row->hte?->name,
            ])
            ->values();

        $counts = [
            'all' => $rows->count(),
            'submitted' => $rows->filter(fn ($r) => $r->document)->count(),
            'pending' => $rows->filter(fn ($r) => ! $r->document)->count(),
        ];

        $filter = in_array($request->query('hte_eval'), ['submitted', 'pending'], true) ? $request->query('hte_eval') : 'all';
        $rows = match ($filter) {
            'submitted' => $rows->filter(fn ($r) => $r->document)->values(),
            'pending' => $rows->filter(fn ($r) => ! $r->document)->values(),
            default => $rows,
        };

        if ($search = trim((string) $request->query('hte_search'))) {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower(($r->hte?->name ?? '').' '.($r->student->user->name ?? '')), $needle))->values();
        }

        return [$rows, $counts, $filter];
    }

    /**
     * Same table, same filters, exported as CSV instead of paginated HTML.
     */
    public function export(Request $request): StreamedResponse
    {
        $students = $this->filteredStudents($request)->get()
            ->map(fn ($student) => $this->withDocsByCode($student));

        $callback = function () use ($students) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Student', 'ID Number', 'Program', 'Endorsement Letter',
                'MOA Status', 'Latest DTR', 'Narrative Report', 'Evaluation Form',
            ]);

            foreach ($students as $student) {
                $hte = $student->currentInternship?->deployment?->hte;
                $latestDtr = $student->currentInternship?->deployment?->latestDtr;

                fputcsv($handle, [
                    $student->user->name,
                    $student->user->id_number,
                    $student->program->code,
                    $this->docLabel($student, 'ENDORSEMENT_LETTER'),
                    $this->moaLabel($student, $hte),
                    $latestDtr ? $latestDtr->log_date->format('M d, Y') : 'Missing',
                    $this->docLabel($student, 'NARRATIVE_REPORT'),
                    $this->docLabel($student, 'EVALUATION_FORM'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="document-tracking-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    private function filteredStudents(Request $request)
    {
        $campusId = auth()->user()->campus_id;

        $query = Student::with([
            'user', 'program',
            'currentInternship.deployment.hte',
            'currentInternship.deployment.latestDtr',
            'currentInternship.documents.requirement',
        ])->where('campus_id', $campusId);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($qq) => $qq
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                );
            });
        }

        if ($programId = $request->query('program_id')) {
            $query->where('program_id', $programId);
        }

        return $query->latest('id');
    }

    private function withDocsByCode(Student $student): Student
    {
        $docs = $student->currentInternship?->documents ?? collect();
        $student->setAttribute('docs_by_code', $docs->keyBy(fn ($d) => $d->requirement->code));

        return $student;
    }

    /**
     * MOA status: the student's own Form 10 (MOA) document once it exists,
     * otherwise the HTE's partnership status, otherwise "Not Submitted".
     */
    private function moaLabel(Student $student, $hte): string
    {
        $doc = $student->docs_by_code->get('MOA');

        if ($doc) {
            return $this->docLabel($student, 'MOA') === 'Missing' ? 'Pending' : $this->docLabel($student, 'MOA');
        }

        return $hte ? ucfirst($hte->moa_status) : 'Not Submitted';
    }

    private function docLabel(Student $student, string $code): string
    {
        $doc = $student->docs_by_code->get($code);

        return match ($doc?->status) {
            'approved' => 'Approved',
            'submitted' => 'Submitted',
            'for_review' => 'For Review',
            'returned' => 'Returned',
            'pending' => 'Pending',
            default => 'Missing',
        };
    }
}
