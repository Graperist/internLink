<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Dtr;
use App\Models\Internship;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Form 19 weeks: see form19Weeks() - each week starts on the first day the
 * student logs after the previous one ended. The 7-day grid helpers below
 * (anchorForInternship, weekStart, number, list) are for Form 17 / DTR.
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

        // Nothing logged yet: once the deployment has started, Week 1 starts
        // on its start date (so the instructor can already set its due date).
        if ($dates->isEmpty()) {
            $deployment = $internship->deployment()->whereIn('status', ['active', 'completed'])->first();
            if ($deployment?->start_date) {
                $dates->push($deployment->start_date);
            }
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

    /**
     * The Form 19 weeks of an internship. A week starts on the first day the
     * student logs after the previous week ended - not on a fixed 7-day grid -
     * and covers 7 calendar days from there, so days with no training
     * (Sundays, holidays, days off) between weeks are skipped. Weeks already
     * sent to the instructor keep their first day and number.
     *
     * Keyed by week number, in order: ['number', 'start', 'end', 'dates' => [Y-m-d, ...]].
     * Week 0 holds days logged before a fixed Week 1 (old records).
     * $extraDates are counted as if logged (to see where a new day would go).
     */
    public static function form19Weeks(Internship $internship, iterable $extraDates = []): Collection
    {
        $extra = collect($extraDates)->filter()->map(fn ($d) => Carbon::parse($d)->toDateString())->values()->all();
        $key = $internship->id.'|'.implode(',', $extra);
        $cache = request()->attributes->get('form19_weeks', []);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $entries = \App\Models\DocumentEntry::whereHas('document', fn ($q) => $q->where('internship_id', $internship->id)
            ->whereHas('requirement', fn ($r) => $r->where('code', 'WEEKLY_REPORT')))
            ->with('document')->get()->toBase();

        // Weeks sent to the instructor: [first day => week number].
        $fixed = [];
        foreach ($entries->groupBy('document_id') as $docEntries) {
            $doc = $docEntries->first()->document;
            if ($doc && $doc->status !== 'draft' && $doc->packet_number) {
                $fixed[$docEntries->map(fn ($e) => Carbon::parse($e->entry_date)->toDateString())->min()] = (int) $doc->packet_number;
            }
        }

        $dates = $entries->map(fn ($e) => Carbon::parse($e->entry_date)->toDateString())
            ->merge($extra)->unique()->sort()->values();

        // Week 1 is fixed once a week was sent (or the instructor set a due date);
        // with nothing logged yet it starts on the deployment's start date.
        $pinned = $internship->getAttribute('week_one_date');
        $first = $pinned ? Carbon::parse($pinned)->startOfDay() : null;
        if (! $first && $dates->isEmpty()) {
            $deployment = $internship->deployment()->whereIn('status', ['active', 'completed'])->first();
            $first = $deployment?->start_date ? Carbon::parse($deployment->start_date)->startOfDay() : null;
        }

        $weeks = collect();
        $before = $first ? $dates->filter(fn ($d) => $d < $first->toDateString())->values() : collect();
        if ($before->isNotEmpty()) {
            $weeks->put(0, ['number' => 0, 'start' => Carbon::parse($before->first()), 'end' => $first->copy()->subDay(), 'dates' => $before->all()]);
        }

        $current = $first ? ['number' => 1, 'start' => $first, 'end' => $first->copy()->addDays(6), 'dates' => []] : null;
        $number = $first ? 1 : 0;
        foreach ($dates as $date) {
            if ($first && $date < $first->toDateString()) {
                continue;
            }
            $day = Carbon::parse($date)->startOfDay();
            if ($current && $day->lte($current['end']) && (! isset($fixed[$date]) || $fixed[$date] === $current['number'])) {
                $current['dates'][] = $date;

                continue;
            }
            if ($current) {
                if ($day->lte($current['end'])) {
                    $current['end'] = $day->copy()->subDay();   // a sent week starts inside it (old records)
                }
                $weeks->put($current['number'], $current);
            }
            $number = $fixed[$date] ?? $number + 1;
            $current = ['number' => $number, 'start' => $day, 'end' => $day->copy()->addDays(6), 'dates' => [$date]];
        }
        if ($current) {
            $weeks->put($current['number'], $current);
        }

        $cache[$key] = $weeks;
        request()->attributes->set('form19_weeks', $cache);

        return $weeks;
    }

    /** Forgets the remembered weeks (after days are added, moved or deleted). */
    public static function forgetWeeks(): void
    {
        request()->attributes->remove('form19_weeks');
    }

    /** The Form 19 week that $date is in (counting it as logged). */
    public static function form19WeekOf(Internship $internship, Carbon $date): ?array
    {
        $day = $date->toDateString();

        return self::form19Weeks($internship, [$day])->first(fn ($week) => in_array($day, $week['dates'], true));
    }

    /** The week starting on $start, or null. */
    public static function form19WeekStarting(Internship $internship, Carbon $start): ?array
    {
        return self::form19Weeks($internship)->first(fn ($week) => $week['start']->isSameDay($start));
    }

    /** The due date of a week from form19Weeks(). */
    public static function dueDateOfWeek(array $week, Internship $internship): Carbon
    {
        $set = self::setDueDates($internship)[$week['number']] ?? null;

        return $set ? $set->copy() : self::defaultDueDate($week['start'], $internship);
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
        $anchor = self::form19Weeks($internship)->first(fn ($week) => $week['number'] >= 1)['start'] ?? null;
        if ($anchor) {
            $internship->forceFill(['week_one_date' => $anchor->toDateString()])->save();
            self::forgetWeeks();
        }

        return $anchor;
    }

    /**
     * Puts every day of a week not sent yet into the Form 19 document of the
     * week it now falls in (weeks move when days are added or deleted), so
     * the week numbers on the documents always match what is shown. Weeks
     * already sent are left as they are.
     */
    public static function syncDraftWeeks(Internship $internship): void
    {
        self::forgetWeeks();
        $docs = self::form19Documents($internship);
        if ($docs->isEmpty()) {
            return;
        }
        $numberOf = [];
        foreach (self::form19Weeks($internship) as $week) {
            foreach ($week['dates'] as $date) {
                $numberOf[$date] = $week['number'];
            }
        }

        $byNumber = $docs->keyBy('packet_number');
        $template = $docs->first();
        $drafts = $docs->where('status', 'draft');
        foreach (\App\Models\DocumentEntry::whereIn('document_id', $drafts->pluck('id'))->get() as $entry) {
            $number = $numberOf[Carbon::parse($entry->entry_date)->toDateString()] ?? null;
            if (! $number) {
                continue;   // before Week 1: left where it is
            }
            $target = $byNumber->get($number);
            if ($target && $target->status !== 'draft') {
                continue;
            }
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
            if ($doc->status === 'draft' && ! $doc->entries()->exists()) {
                $doc->delete();
            }
        }
        self::forgetWeeks();
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

    /** Days after a week ends that its Form 19 is still on time - set by the Subject Instructor for the program. */
    public static function dueDays(Internship $internship): int
    {
        $days = $internship->program?->weekly_report_due_days ?? $internship->student?->program?->weekly_report_due_days;

        return $days === null ? 2 : max(0, (int) $days);
    }

    /** Clears the remembered due dates (after the instructor changes one). */
    public static function forgetDueDates(): void
    {
        request()->attributes->remove('form19_due_dates');
    }

    /** The due dates the instructor picked for this intern's weeks: [week number => Carbon]. */
    public static function setDueDates(Internship $internship): array
    {
        // Remembered for the current request only.
        $all = request()->attributes->get('form19_due_dates', []);
        if (! array_key_exists($internship->id, $all)) {
            // Until `php artisan migrate` adds the table, every week uses the default.
            $all[$internship->id] = \Illuminate\Support\Facades\Schema::hasTable('form19_due_dates')
                ? \App\Models\Form19DueDate::where('internship_id', $internship->id)
                    ->get()->mapWithKeys(fn ($d) => [(int) $d->week_number => $d->due_at->copy()])->all()
                : [];
            request()->attributes->set('form19_due_dates', $all);
        }

        return $all[$internship->id];
    }

    /** The default due date of a week: 11:59 PM, dueDays() after its last day. */
    public static function defaultDueDate(Carbon $weekStart, Internship $internship): Carbon
    {
        return $weekStart->copy()->addDays(6 + self::dueDays($internship))->endOfDay();
    }

    /**
     * When the week starting on $weekStart is due: the date and time the
     * instructor picked for that week, or the default.
     */
    public static function dueDate(Carbon $weekStart, Internship $internship): Carbon
    {
        $week = self::form19WeekStarting($internship, $weekStart);

        return $week ? self::dueDateOfWeek($week, $internship) : self::defaultDueDate($weekStart, $internship);
    }

    /** Was this week's due date picked by the instructor (not the default)? */
    public static function dueDateIsSet(Carbon $weekStart, Internship $internship): bool
    {
        $week = self::form19WeekStarting($internship, $weekStart);

        return $week && isset(self::setDueDates($internship)[$week['number']]);
    }

    /** The due date of one week's Form 19 document, or null if it has no week. */
    public static function dueDateFor(Document $document): ?Carbon
    {
        $internship = $document->internship;
        $week = $internship && $document->packet_number ? self::form19Weeks($internship)->get((int) $document->packet_number) : null;

        return $week ? self::dueDateOfWeek($week, $internship) : null;
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
