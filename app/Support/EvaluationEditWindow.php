<?php

namespace App\Support;

use App\Models\Document;
use Carbon\CarbonImmutable;

/**
 * How long a student or HTE can still edit a submitted evaluation
 * (Forms 13, 21, 23b, 23c, 20). It closes at whichever comes first:
 *   - the deadline: the program's "evaluation_edit_days" (set by the Subject
 *     Instructor on Evaluations) after the internship
 *     ended (default 7 days), until 11:59 PM of that day;
 *   - the Subject Instructor locking it (e.g. once they have graded).
 * The instructor can unlock it again for a correction; the deadline is
 * then extended by the same number of days from the unlock.
 */
class EvaluationEditWindow
{
    public const DEFAULT_DAYS = 7;

    /** Last moment the evaluation can be edited, or null when there is no deadline yet. */
    public static function editableUntil(?Document $document): ?CarbonImmutable
    {
        if (! $document) {
            return null;
        }
        $internship = $document->internship;
        $days = (int) ($internship?->program?->evaluation_edit_days ?? self::DEFAULT_DAYS);
        $ended = $internship?->deployment?->end_date ?? $document->created_at;
        if (! $ended) {
            return null;
        }
        $until = CarbonImmutable::parse($ended)->addDays($days)->endOfDay();

        // Unlocked again by the instructor: a fresh window from the unlock.
        if ($document->unlocked_at ?? null) {
            $until = max($until, CarbonImmutable::parse($document->unlocked_at)->addDays($days)->endOfDay());
        }

        return $until;
    }

    public static function isLockedByInstructor(?Document $document): bool
    {
        return (bool) $document?->locked_at;
    }

    public static function deadlinePassed(?Document $document): bool
    {
        $until = self::editableUntil($document);

        return $until !== null && now()->greaterThan($until);
    }

    /** Can the student / HTE still change this submitted evaluation? (Not submitted yet: always.) */
    public static function canEdit(?Document $document): bool
    {
        if (! EvaluationRouting::isSubmitted($document)) {
            return true;
        }

        return ! self::isLockedByInstructor($document) && ! self::deadlinePassed($document);
    }

    /** Short note for the student / HTE: "Edit until Nov 2" or why it is closed. */
    public static function note(?Document $document): ?string
    {
        if (! EvaluationRouting::isSubmitted($document)) {
            return null;
        }
        if (self::isLockedByInstructor($document)) {
            return 'Locked by the Subject Instructor';
        }
        if (self::deadlinePassed($document)) {
            return 'Editing closed '.self::editableUntil($document)->format('M j, Y');
        }
        $until = self::editableUntil($document);

        return $until ? 'You can edit until '.$until->format('M j, Y') : null;
    }

    /** The message when a locked one is opened. */
    public static function closedMessage(?Document $document): string
    {
        return self::isLockedByInstructor($document)
            ? 'This evaluation was locked by the Subject Instructor and can no longer be edited.'
            : 'The time to edit this evaluation has ended ('.self::editableUntil($document)?->format('M j, Y').').';
    }
}
