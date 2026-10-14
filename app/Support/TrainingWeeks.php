<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Dtr;
use App\Models\Internship;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Form 19 weeks begin on the day the student first logged their DTR (not on a
 * fixed Monday): Week 1 = that day .. +6, Week 2 = the next 7 days, etc.
 * Before any DTR exists, the earliest Form 19 entry date is used instead.
 */
class TrainingWeeks
{
    /** The first day the student logged a DTR (falls back to the earliest entry). */
    public static function anchorForInternship(Internship $internship, iterable $entryDates = []): ?Carbon
    {
        $firstDtr = Dtr::whereHas('deployment', fn ($q) => $q->where('internship_id', $internship->id))
            ->min('log_date');

        if ($firstDtr) {
            return Carbon::parse($firstDtr)->startOfDay();
        }

        return self::anchor($entryDates);
    }

    public static function anchorFor(Document $document): ?Carbon
    {
        return self::anchorForInternship($document->internship, $document->entries->pluck('entry_date'));
    }

    /** The student's first training day, or null if there are no entries. */
    public static function anchor(iterable $entryDates): ?Carbon
    {
        $earliest = null;

        foreach ($entryDates as $date) {
            $date = Carbon::parse($date)->startOfDay();
            if ($earliest === null || $date->lt($earliest)) {
                $earliest = $date;
            }
        }

        return $earliest;
    }

    /** The first day of the 7-day week that $date falls in. */
    public static function weekStart(Carbon $date, Carbon $anchor): Carbon
    {
        $days = (int) floor($anchor->copy()->startOfDay()->diffInDays($date->copy()->startOfDay(), false) / 7);

        return $anchor->copy()->startOfDay()->addDays($days * 7);
    }

    /**
     * Entries grouped by week, keyed by the week's first day (Y-m-d),
     * weeks in order and entries in date order within each week.
     */
    public static function group(Collection $entries, ?Carbon $anchor = null): Collection
    {
        $anchor ??= self::anchor($entries->pluck('entry_date'));

        if (! $anchor) {
            return collect();
        }

        return $entries
            ->sortBy(fn ($entry) => Carbon::parse($entry->entry_date)->timestamp)
            ->groupBy(fn ($entry) => self::weekStart(Carbon::parse($entry->entry_date), $anchor)->toDateString())
            ->sortKeys();
    }
}
