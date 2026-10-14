<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Support\EvaluationRouting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HteInfoController extends Controller
{
    private const FEEDBACK_CODE = 'TRAINING_SUPERVISOR_FEEDBACK';

    /**
     * Figure 23 - My HTE Info. The weekly schedule table in the mockup
     * (Mon-Fri 8-5, Saturday rest day) isn't real data - there's no
     * schedule field in the schema, so it's omitted rather than faked.
     *
     * This page is also where the student receives Form 21, the Training
     * Supervisor's Feedback Form their host training establishment submits
     * about them once the internship is completed.
     */
    public function index(): View
    {
        $student = auth()->user()->student;
        $internship = $student->currentInternship;
        $deployment = $internship?->deployment;

        $feedback = $internship ? $this->feedbackDocument($internship) : null;

        return view('student.hte', compact('deployment', 'feedback'));
    }

    /** Read-only printable copy of the student's own Form 21. */
    public function feedback(Request $request, Document $document): View
    {
        $student = auth()->user()->student;
        abort_unless($student, 403);

        $document->load('requirement', 'internship');
        abort_unless($document->requirement?->code === self::FEEDBACK_CODE, 404);
        // Own record only - never another student's feedback.
        abort_unless($document->internship?->student_id === $student->id, 403);
        abort_unless(EvaluationRouting::isSubmitted($document), 404);

        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user', 'uploader');

        return view(EvaluationRouting::view(self::FEEDBACK_CODE), [
            'document' => $document,
            'autoPrint' => $request->boolean('download'),
            'backUrl' => route('student.hte'),
        ]);
    }

    private function feedbackDocument($internship): ?Document
    {
        $document = Document::where('internship_id', $internship->id)
            ->whereHas('requirement', fn ($q) => $q->where('code', self::FEEDBACK_CODE))
            ->with(['fieldValues', 'requirement.fieldDefinitions', 'uploader'])
            ->first();

        return EvaluationRouting::isSubmitted($document) ? $document : null;
    }
}
