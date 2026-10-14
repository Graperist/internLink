<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentViewController extends Controller
{
    /**
     * Read-only trail of one student's forms - every pre-deployment,
     * active, and post-deployment requirement, mirroring the Subject
     * Instructor's Document Review checklist but with no approve/return/
     * print/complete actions, since those decisions stay with the
     * instructor. This is purely visibility for the coordinator.
     */
    public function show(Student $student): View
    {
        $coordinator = auth()->user();
        abort_unless($student->campus_id === $coordinator->campus_id, 403);

        $student->load(['user', 'program', 'currentInternship.deployment.hte', 'currentInternship.instructor.user']);
        $internship = $student->currentInternship;

        $requirementsByStage = DocumentRequirement::where('campus_id', $coordinator->campus_id)
            ->where('is_required', true)
            ->where('is_active', true)
            ->with('fieldDefinitions')
            ->orderBy('stage')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->groupBy('stage');

        $documentsByRequirement = $internship
            ? $internship->documents()->with(['latestReview', 'fieldValues', 'entries.values'])->get()->keyBy('document_requirement_id')
            : collect();

        return view('coordinator.documents.show', compact(
            'student', 'internship', 'requirementsByStage', 'documentsByRequirement'
        ));
    }

    /**
     * Same read-only printable templates the instructor uses to review
     * each form - reused as-is, since they only depend on the Document
     * itself and don't extend a portal-specific layout.
     */
    public function printable(Document $document): Response
    {
        $coordinator = auth()->user();
        abort_unless($document->internship->campus_id === $coordinator->campus_id, 403);

        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user');

        // Kept identical to Instructor\DocumentReviewController::printable()'s
        // mapping so a form looks the same wherever it's viewed from.
        $dedicatedViews = [
            'APPLICATION_FOR_OJT' => 'instructor.documents.printable-application-for-ojt',
            'CERTIFICATE_OF_UNITS_EARNED' => 'instructor.documents.printable-certificate-units-earned',
            'ENDORSEMENT_LETTER' => 'instructor.documents.printable-endorsement-letter',
            'PARENT_CONSENT' => 'instructor.documents.printable-consent-waiver',
            'OJT_RESUME' => 'instructor.documents.printable-ojt-resume',
            'INTERNSHIP_AGREEMENT' => 'instructor.documents.printable-internship-agreement',
            'INTERNSHIP_ACADEMIC_PRACTICUM_COURSE' => 'instructor.documents.printable-internship-academic-practicum',
            'DAILY_JOURNAL' => 'instructor.documents.printable-daily-journal',
            'WEEKLY_REPORT' => 'instructor.documents.printable-weekly-report',
            'INTERNSHIP_TIME_FRAME' => 'instructor.documents.printable-internship-time-frame',
            'STUDENT_FEEDBACK_FORM' => 'instructor.documents.printable-student-feedback',
            'STUDENT_HTE_EVALUATION' => 'instructor.documents.printable-student-hte-evaluation',
            'INTERNSHIP_RELEASE_FORM' => 'instructor.documents.printable-internship-release-form',
            'TRAINING_LIABILITY' => 'instructor.documents.printable-training-agreement',
            'EVALUATION_FORM' => 'instructor.documents.printable-form13',
            'TRAINING_SUPERVISOR_FEEDBACK' => 'instructor.documents.printable-training-supervisor-feedback',
            'PARTNER_AGENCY_EVAL_SELF' => 'instructor.documents.printable-partner-agency-eval-self',
            'PARTNER_AGENCY_EVAL_INSTRUCTOR' => 'instructor.documents.printable-partner-agency-eval-instructor',
        ];

        $view = $dedicatedViews[$document->requirement->code] ?? 'instructor.documents.printable';

        // View-only for the coordinator: the shared printable templates open the
        // print dialog on load and carry a Print button for the instructor's
        // print-and-sign workflow. Strip both here so the coordinator just
        // reads the form (downloads live in the Repository).
        $html = view($view, ['document' => $document, 'autoPrint' => false])->render();
        $html = str_replace('<body onload="window.print()">', '<body>', $html);
        $html = str_replace('<button class="no-print" onclick="window.print()">Print</button>', '', $html);

        return response($html);
    }


    /**
     * View an uploaded file inline without forcing a download.
     */
    public function view(Document $document): StreamedResponse
    {
        $coordinator = auth()->user();
        abort_unless($document->internship->campus_id === $coordinator->campus_id, 403);
        abort_unless($document->file_path && Storage::exists($document->file_path), 404);

        return Storage::response($document->file_path, $document->original_filename, [], 'inline');
    }

    /**
     * The scanned, signed copy the student uploaded once an instructor
     * marked the form completed (or the plain-upload requirements, once
     * approved).
     */
    public function download(Document $document): StreamedResponse
    {
        $coordinator = auth()->user();
        abort_unless($document->internship->campus_id === $coordinator->campus_id, 403);
        abort_unless($document->file_path && Storage::exists($document->file_path), 404);

        return Storage::download($document->file_path, $document->original_filename);
    }
}
