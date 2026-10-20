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
    /**
     * Week 1's first day: the first day the student logged a DTR - or an
     * earlier Form 19 entry, if they logged one before their first DTR day,
     * so no week is ever numbered 0.
     */
    public static function anchorForInternship(Internship $internship, iterable $entryDates = []): ?Carbon
    {
        // Fixed once the student sent their first week, so numbers never shift.
        $pinned = $internship->getAttribute('week_one_date');
        if ($pinned) {
            return Carbon::parse($pinned)->startOfDay();
        }

        $dates = collect($entryDates)->filter();

        $firstDtr = Dtr::whereHas('deployment', fn ($q) => $q->where('internship_id', $internship->id))->min('log_date');
        if ($firstDtr) {
            $dates->push($firstDtr);
        }
        $firstEntry = \App\Models\DocumentEntry::whereHas('document', fn ($q) => $q->where('internship_id', $internship->id)
            ->whereHas('requirement', fn ($r) => $r->where('code', 'WEEKLY_REPORT')))->min('entry_date');
        if ($firstEntry) {
            $dates->push($firstEntry);
        }

        return self::anchor($dates);
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

    /** The Form 19 (Weekly Report) documents of an internship. */
    public static function form19Documents(Internship $internship): Collection
    {
        return Document::where('internship_id', $internship->id)
            ->whereHas('requirement', fn ($r) => $r->where('code', 'WEEKLY_REPORT'))
            ->get();
    }

    /**
     * Fixes Week 1's first day for good. Called when the student sends their
     * first week to the instructor; from then on no earlier day can be added
     * and the week numbers on reviewed weeks stay the same.
     */
    public static function pin(Internship $internship): ?Carbon
    {
        if ($internship->week_one_date) {
            return Carbon::parse($internship->week_one_date)->startOfDay();
        }
        $anchor = self::anchorForInternship($internship);
        if ($anchor) {
            $internship->forceFill(['week_one_date' => $anchor->toDateString()])->save();
        }

        return $anchor;
    }

    /**
     * Before Week 1 is fixed, every week is still a draft and its start can
     * move (e.g. the student adds an earlier day, or logs an earlier DTR). This
     * puts every entry into the document of the week it now falls in, so the
     * week numbers stored on the documents always match what is shown.
     */
    public static function syncDraftWeeks(Internship $internship): void
    {
        if ($internship->week_one_date) {
            return;
        }
        $docs = self::form19Documents($internship);
        if ($docs->isEmpty() || $docs->contains(fn ($doc) => $doc->status !== 'draft')) {
            return;
        }
        $anchor = self::anchorForInternship($internship);
        if (! $anchor) {
            return;
        }

        $byNumber = $docs->keyBy('packet_number');
        $template = $docs->first();
        foreach (\App\Models\DocumentEntry::whereIn('document_id', $docs->pluck('id'))->get() as $entry) {
            $number = self::number(self::weekStart(Carbon::parse($entry->entry_date), $anchor), $anchor);
            $target = $byNumber->get($number);
            if (! $target) {
                $target = Document::create([
                    'internship_id' => $internship->id,
                    'document_requirement_id' => $template->document_requirement_id,
                    'uploaded_by_user_id' => $template->uploaded_by_user_id,
                    'status' => 'draft',
                    'packet_number' => $number,
                ]);
                $byNumber->put($number, $target);
            }
            if ($entry->document_id !== $target->id) {
                $entry->forceFill(['document_id' => $target->id])->save();
            }
        }

        foreach ($byNumber as $doc) {
            if (! $doc->entries()->exists()) {
                $doc->delete();
            }
        }
    }

    /**
     * Can this week be sent to the instructor yet? Only once it is finished:
     * on or after its last day - or earlier when the deployment has ended
     * (a short last week).
     */
    public static function weekFinished(Carbon $weekStart, Internship $internship): bool
    {
        return now()->startOfDay()->gte($weekStart->copy()->addDays(6)->startOfDay())
            || $internship->hasCompletedDeployment();
    }

    /** Days after a week ends that its Form 19 is still on time (Campus Settings). */
    public static function dueDays(Internship $internship): int
    {
        $days = $internship->campus?->weekly_report_due_days;

        return $days === null ? 2 : max(0, (int) $days);
    }

    /** When the week starting on $weekStart is due: 11:59 PM, dueDays() after its last day. */
    public static function dueDate(Carbon $weekStart, Internship $internship): Carbon
    {
        return $weekStart->copy()->addDays(6 + self::dueDays($internship))->endOfDay();
    }

    /** The due date of one week's Form 19 document, or null if it has no week. */
    public static function dueDateFor(Document $document): ?Carbon
    {
        $internship = $document->internship;
        $anchor = $internship ? self::anchorForInternship($internship) : null;
        if (! $anchor || ! $document->packet_number) {
            return null;
        }

        return self::dueDate($anchor->copy()->addDays(7 * ((int) $document->packet_number - 1)), $internship);
    }

    /**
     * Like MS Teams: 'late' when the week was first sent after its due date,
     * 'missing' when it is past due and not sent yet, otherwise null.
     */
    public static function lateness(?Document $document, Carbon $due): ?string
    {
        $sent = $document?->first_submitted_at
            ?? ($document && $document->status !== 'draft' ? $document->submitted_at : null);
        if ($sent) {
            return $sent->gt($due) ? 'late' : null;
        }

        return now()->gt($due) ? 'missing' : null;
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

    /** "Week 3" style number of the 7-day week starting on $weekStart. */
    public static function number(Carbon $weekStart, Carbon $anchor): int
    {
        return (int) floor($anchor->copy()->startOfDay()->diffInDays($weekStart->copy()->startOfDay(), false) / 7) + 1;
    }

    /**
     * The week asked for with ?week=Y-m-d (a download of one week only), as
     * the start of that 7-day week - or null for the whole form.
     */
    public static function requested(Internship $internship): ?Carbon
    {
        $week = request()->query('week');
        // ?period=week:Y-m-d (the instructor's View/Download picker)
        $period = request()->query('period');
        if (is_string($period) && str_starts_with($period, 'week:')) {
            $week = substr($period, 5);
        }
        if (! is_string($week) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
            return null;
        }
        $anchor = self::anchorForInternship($internship);

        return $anchor ? self::weekStart(Carbon::parse($week), $anchor) : null;
    }

    /**
     * The month asked for with ?month=Y-m or ?period=month:Y-m (a view or
     * download of one month only), as its first day - or null.
     */
    public static function requestedMonth(): ?Carbon
    {
        $month = request()->query('month');
        $period = request()->query('period');
        if (is_string($period) && str_starts_with($period, 'month:')) {
            $month = substr($period, 6);
        }
        if (! is_string($month) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
    }

    /** The months that have days in them: [['start' => Carbon, 'count' => int], ...] */
    public static function months(iterable $dates): array
    {
        $months = [];
        foreach ($dates as $date) {
            $start = Carbon::parse($date)->startOfMonth();
            $key = $start->format('Y-m');
            $months[$key] ??= ['start' => $start, 'count' => 0];
            $months[$key]['count']++;
        }
        ksort($months);

        return array_values($months);
    }

    /** " - Week 3 (Oct 05 - Oct 11, 2026)" / " - October 2026" for a one-week or one-month file name, else "". */
    public static function downloadSuffix(Internship $internship): string
    {
        if ($month = self::requestedMonth()) {
            return ' - '.$month->format('F Y');
        }
        $start = self::requested($internship);
        if (! $start) {
            return '';
        }
        $anchor = self::anchorForInternship($internship);

        return ' - Week '.self::number($start, $anchor).' ('.$start->format('M d').' to '.$start->copy()->addDays(6)->format('M d, Y').')';
    }

    /**
     * The weeks that have days in them, for "download by week" buttons:
     * [['start' => Carbon, 'end' => Carbon, 'number' => int, 'count' => int], ...]
     */
    public static function list(iterable $dates, ?Carbon $anchor): array
    {
        if (! $anchor) {
            return [];
        }
        $weeks = [];
        foreach ($dates as $date) {
            $start = self::weekStart(Carbon::parse($date), $anchor);
            $key = $start->toDateString();
            $weeks[$key] ??= ['start' => $start, 'end' => $start->copy()->addDays(6), 'number' => self::number($start, $anchor), 'count' => 0];
            $weeks[$key]['count']++;
        }
        ksort($weeks);

        return array_values($weeks);
    }
}
