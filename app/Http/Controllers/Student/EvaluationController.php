<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use App\Support\EvaluationRouting;
use Illuminate\View\View;

/**
 * Evaluate Agency - like the PSU faculty evaluation: the name of the agency
 * the student was deployed to, click it and rate it. Form 23c (Evaluation of
 * the Partner Agency) and Form 20 (Student-Intern's Feedback). They go to the
 * Subject Instructor only; the agency never sees them. Open once the
 * internship is completed.
 */
class EvaluationController extends Controller
{
    public function index(): View
    {
        $student = auth()->user()->student;
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

        return view('student.evaluations', compact('deployment', 'completed', 'rows'));
    }
}
