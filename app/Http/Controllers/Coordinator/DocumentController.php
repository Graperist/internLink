<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    /**
     * Figure 6 - Repository (was Document Tracking): four tracked items per student -
     * Endorsement Letter, MOA Status, Latest DTR and Narrative Report
     * (evaluations are the Subject Instructor's only) - each pulled from its real source table rather
     * than a single generic "documents" loop, since MOA and DTR aren't
     * document uploads.
     */
    public function index(Request $request): View
    {
        $students = $this->filteredStudents($request)->paginate(20)->withQueryString();
        $students->getCollection()->transform(fn ($student) => $this->withDocsByCode($student));

        $campusId = auth()->user()->campus_id;
        $programs = Program::where('campus_id', $campusId)->orderBy('name')->get();
        // The Repository is merged in here: the files each intern has passed,
        // filterable by file type, with Open / Download all per student.
        // Evaluations stay with the Subject Instructor, so they aren't a file type here.
        $requirements = DocumentRequirement::where('campus_id', $campusId)->where('is_active', true)
            ->whereNotIn('code', array_keys(\App\Support\EvaluationRouting::FORMS))
            ->orderBy('name')->get();
        $stageTotals = DocumentRepositoryController::stageTotals($campusId);

        // Evaluations are not tracked here: they go to the Subject Instructor only.
        return view('coordinator.documents.index', compact('students', 'programs', 'requirements', 'stageTotals'));
    }

    private function filteredStudents(Request $request)
    {
        $campusId = auth()->user()->campus_id;

        $query = Student::with([
            'user', 'program',
            'currentInternship.deployment.hte',
            'currentInternship.deployment.latestDtr',
            'currentInternship.documents.requirement',
        ])
            // The files they have passed (the repository): approved, with a
            // file on record or uploaded scans, for live requirements.
            ->with(['documents' => fn ($q) => $q
                ->where('documents.status', 'approved')
                ->where(fn ($f) => $f->where(fn ($p) => $p->whereNotNull('documents.file_path')->where('documents.file_path', '!=', ''))
                    ->orWhereHas('attachments'))
                ->whereHas('requirement', fn ($r) => $r->where('is_active', true))
                ->with(['requirement', 'attachments']),
            ])
            ->where('campus_id', $campusId);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($qq) => $qq
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                )->orWhereHas('documents', fn ($d) => $d->where('documents.original_filename', 'like', "%{$search}%"));
            });
        }

        if ($requirementId = $request->query('document_requirement_id')) {
            $query->whereHas('documents', fn ($d) => $d
                ->where('documents.document_requirement_id', $requirementId)
                ->where('documents.status', 'approved'));
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
}
