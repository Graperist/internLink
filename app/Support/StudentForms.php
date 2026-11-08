<?php

namespace App\Support;

use App\Models\DocumentRequirement;
use App\Models\Internship;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * The forms listed on a student's My Documents (their own forms and the
 * coordinator-handled ones like the MOA; not the HTE's or instructor's,
 * nor the agency evaluations on Evaluate Agency), and whether each one is
 * done - so My Progress and the Dashboard count the same forms.
 */
class StudentForms
{
    /** Every form of the student's checklist (all stages). */
    public static function requirements(Student $student): Collection
    {
        return DocumentRequirement::where('campus_id', $student->campus_id)
            ->where('is_active', true)
            ->whereIn('audience', ['student', 'coordinator'])
            ->whereNotIn('code', EvaluationRouting::studentEvaluationCodes())
            ->get();
    }

    /**
     * Has the student done this form?
     * - Form 17 (Time Frame): the required hours are rendered.
     * - Form 19 (Weekly Report): every week entered has been sent to the instructor.
     * - Any other form: sent (submitted, in review, approved or signed).
     */
    public static function isDone(DocumentRequirement $requirement, ?Internship $internship, ?Collection $documents = null): bool
    {
        if (! $internship) {
            return false;
        }
        $documents ??= $internship->documents()->with('requirement')->get();
        $mine = $documents->filter(fn ($d) => $d->document_requirement_id === $requirement->id);

        if ($requirement->code === 'INTERNSHIP_TIME_FRAME') {
            $required = (float) ($internship->requiredHours() ?? 0);
            $rendered = (float) ($internship->student?->hours_rendered ?? 0);

            return $internship->hasCompletedDeployment() || ($required > 0 && $rendered >= $required);
        }

        if ($requirement->code === 'WEEKLY_REPORT') {
            $weeks = $mine->filter(fn ($d) => $d->entries()->exists());

            return $weeks->isNotEmpty()
                && $weeks->every(fn ($d) => ! in_array($d->status, ['draft', 'returned'], true));
        }

        $doc = $mine->sortByDesc('id')->first();

        return $doc !== null && ! in_array($doc->status, ['draft', 'returned'], true);
    }
}
