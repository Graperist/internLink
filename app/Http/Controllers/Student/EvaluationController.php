<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use App\Support\EvaluationRouting;
use Illuminate\Http\RedirectResponse;

/**
 * Evaluate Your Agency (on My HTE) - like the PSU faculty evaluation: the name of the agency
 * the student was deployed to, click it and rate it. Form 23c (Evaluation of
 * the Partner Agency) and Form 20 (Student-Intern's Feedback). They go to the
 * Subject Instructor only; the agency never sees them. Open once the
 * internship is completed.
 */
class EvaluationController extends Controller
{
    /** The rows for the Evaluate Your Agency card on My HTE. */
    public static function data($student): array
    {
        $internship = $student?->currentInternship;
        $deployment = $internship?->deployment;
        $completed = $internship && $internship->hasCompletedDeployment();

        $requirements = DocumentRequirement::where('campus_id', $student->campus_id)
            ->where('is_active', true)
            ->where('audience', 'student')
            ->whereIn('code', EvaluationRouting::STUDENT_CODES)
            ->get()
            ->sortBy(fn ($r) => array_search($r->code, EvaluationRouting::STUDENT_CODES))
            ->values();

        $documents = $internship
            ? $internship->documents()->whereIn('document_requirement_id', $requirements->pluck('id'))->get()->keyBy('document_requirement_id')
            : collect();

        $rows = $requirements->map(fn ($requirement) => (object) [
            'requirement' => $requirement,
            'done' => EvaluationRouting::isSubmitted($documents->get($requirement->id)),
        ]);

        return compact('deployment', 'completed', 'rows');
    }

    /** The evaluation now lives on My HTE. */
    public function index(): RedirectResponse
    {
        return redirect()->to(route('student.hte').'#evaluate');
    }
}
