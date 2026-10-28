<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Dtr;
use App\Models\Notification;
use App\Models\Student;
use App\Support\TrainingWeeks;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Form 19 (Weekly Report) reminders for students. Deadline for both is
 * 11:59 PM of the day in question.
 *
 *   php artisan internlink:send-reminders             evening run (8:00 PM)
 *     - "add today's training day" - clocked in today but no Form 19 entry
 *     - "submit your weekly report" - today is the last day of a 7-day week
 *   php artisan internlink:send-reminders --overdue   morning run (8:00 AM)
 *     - overdue notices for up to 3 days after a week ended unsubmitted
 *
 * Weeks start on the day the student first logged their DTR, so they match
 * the weeks shown on the Weekly Report page.
 */
class SendWeeklyReportReminders extends Command
{
    protected $signature = 'internlink:send-reminders {--overdue : Send overdue-submission notices instead of the evening reminders}';

    protected $description = 'Remind students to add their day to the Form 19 weekly report and to submit it (by each week due date)';

    /** Form 19 states where the student can no longer add/submit. */
    private const LOCKED = ['printed', 'completed', 'approved'];

    /** States where Form 19 is already with the instructor / done. */
    private const SUBMITTED = ['submitted', 'for_review', 'printed', 'completed', 'approved'];

    private const OVERDUE_DAYS = 3;

    public function handle(): int
    {
        $today = Carbon::today();
        $sent = 0;

        Student::with(['user', 'currentInternship.deployment'])
            ->whereHas('currentInternship.deployment')
            ->chunkById(100, function ($students) use ($today, &$sent) {
                foreach ($students as $student) {
                    $internship = $student->currentInternship;
                    $deployment = $internship?->deployment;

                    if (! $student->user || ! $deployment || $deployment->status === 'terminated') {
                        continue;
                    }

                    $requirement = DocumentRequirement::where('campus_id', $internship->campus_id)
                        ->where('code', 'WEEKLY_REPORT')
                        ->where('is_active', true)
                        ->first();

                    if (! $requirement) {
                        continue;
                    }

                    // One Form 19 document per week (packet_number = week number).
                    $weeks = $internship->documents()
                        ->where('document_requirement_id', $requirement->id)
                        ->with('entries')
                        ->get()
                        ->keyBy('packet_number');

                    $sent += $this->option('overdue')
                        ? $this->overdueNotice($student, $internship, $weeks, $today)
                        : $this->eveningReminders($student, $internship, $weeks, $today);
                }
            });

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }

    private function eveningReminders(Student $student, $internship, $weeks, Carbon $today): int
    {
        $sent = 0;
        $deploymentId = $internship->deployment->id;
        $anchor = TrainingWeeks::anchorForInternship($internship, $weeks->flatMap(fn ($d) => $d->entries)->pluck('entry_date'));
        $todayWeek = $anchor && $today->gte($anchor) ? TrainingWeeks::number(TrainingWeeks::weekStart($today, $anchor), $anchor) : null;
        $document = $todayWeek ? $weeks->get($todayWeek) : null;   // this week's Form 19
        $locked = $document && in_array($document->status, self::SUBMITTED, true);

        // 1) Clocked in today but no Form 19 entry for today.
        $clockedInToday = Dtr::where('deployment_id', $deploymentId)
            ->whereDate('log_date', $today)
            ->exists();

        $hasTodayEntry = $document
            ? $document->entries->contains(fn ($entry) => Carbon::parse($entry->entry_date)->isSameDay($today))
            : false;

        if ($clockedInToday && ! $hasTodayEntry && ! $locked) {   // (this week not yet sent)
            $sent += $this->notifyOnce(
                $student,
                'Add today to your weekly report',
                'You clocked in today ('.$today->format('M d, Y').') but haven\'t added it to your Form 19 weekly report yet. '
                .'Please add today\'s training day before 11:59 PM.',
                false,
                'logentry:'.$student->id.':'.$today->toDateString(),
                $this->logUrl($internship)
            );
        }

        // 2) Today is the last day of a 7-day week and that week isn't submitted.
        $alreadySubmitted = $locked;

        if ($anchor && ! $alreadySubmitted) {
            $dayIndex = (int) $anchor->diffInDays($today, false);

            if ($dayIndex >= 6 && ($dayIndex + 1) % 7 === 0) {
                $weekNumber = intdiv($dayIndex + 1, 7);
                $weekStart = $anchor->copy()->addDays(($weekNumber - 1) * 7);

                if ($this->trainedInWeek($deploymentId, $document, $weekStart)) {
                    $hasEntries = $document && $this->hasEntriesInWeek($document, $weekStart);

                    $due = TrainingWeeks::dueDate($weekStart, $internship);
                    $dueText = $due->isSameDay($today) ? 'today at '.$due->format('g:i A') : $due->format('M d, Y g:i A');
                    $sent += $this->notifyOnce(
                        $student,
                        "Submit your Week {$weekNumber} report",
                        "Week {$weekNumber} ({$weekStart->format('M d')} – {$today->format('M d, Y')}) ends today. "
                        .($hasEntries
                            ? "Submit your Form 19 weekly report for instructor review. It is due {$dueText}."
                            : "It has no weekly report entries yet — add your training days and submit your Form 19. It is due {$dueText}."),
                        false,
                        'logsubmit:'.$student->id,
                        $this->logUrl($internship)
                    );
                }
            }
        }

        // 3) A week due later today or tomorrow (its own due date, or the default) and not sent yet.
        if ($anchor) {
            $current = $today->gte($anchor) ? TrainingWeeks::number(TrainingWeeks::weekStart($today, $anchor), $anchor) : 0;
            for ($weekNumber = max(1, $current - 3); $weekNumber <= $current + 1; $weekNumber++) {
                $weekStart = $anchor->copy()->addDays(($weekNumber - 1) * 7);
                $due = TrainingWeeks::dueDate($weekStart, $internship);
                $dueSoon = ($due->isSameDay($today) && $due->gt(now())) || $due->isSameDay($today->copy()->addDay());
                $weekDoc = $weeks->get($weekNumber);
                if (! $dueSoon || ($weekDoc && in_array($weekDoc->status, self::SUBMITTED, true))
                    || ! $this->trainedInWeek($deploymentId, $weekDoc, $weekStart)) {
                    continue;
                }
                $when = ($due->isSameDay($today) ? 'today' : 'tomorrow').' at '.$due->format('g:i A');
                $sent += $this->notifyOnce(
                    $student,
                    "Week {$weekNumber} report is due {$when}",
                    "Your Form 19 weekly report for Week {$weekNumber} ({$weekStart->format('M d')} – {$weekStart->copy()->addDays(6)->format('M d, Y')}) is due {$when}. "
                    .'After that it is marked Late.',
                    false,
                    'logsubmit:'.$student->id,
                    $this->logUrl($internship)
                );
            }
        }

        return $sent;
    }

    private function overdueNotice(Student $student, $internship, $weeks, Carbon $today): int
    {
        $anchor = TrainingWeeks::anchorForInternship($internship, $weeks->flatMap(fn ($d) => $d->entries)->pluck('entry_date'));

        if (! $anchor) {
            return 0;
        }

        // Recent weeks that are past their due date and still not sent.
        $dayIndex = (int) $anchor->diffInDays($today, false);
        $completed = intdiv($dayIndex, 7); // weeks finished so far
        $sent = 0;

        for ($weekNumber = max(1, $completed - 3); $weekNumber <= $completed; $weekNumber++) {
            $weekStart = $anchor->copy()->addDays(($weekNumber - 1) * 7);
            $due = TrainingWeeks::dueDate($weekStart, $internship);
            $document = $weeks->get($weekNumber);   // that week's Form 19
            if ($document && in_array($document->status, self::SUBMITTED, true)) {
                continue;
            }
            if ($today->lte($due)) {
                continue;   // not due yet
            }
            $daysLate = (int) $due->copy()->startOfDay()->diffInDays($today);
            if ($daysLate < 1 || $daysLate > self::OVERDUE_DAYS) {
                continue;
            }
            if (! $this->trainedInWeek($internship->deployment->id, $document, $weekStart)) {
                continue;
            }

            $sent += $this->notifyOnce(
                $student,
                "Week {$weekNumber} report is overdue",
                "Your Form 19 weekly report for Week {$weekNumber} was due {$due->format('M d, Y g:i A')} and hasn't been submitted. "
                .'Please submit it as soon as possible. It will be marked Late.',
                true,
                'logsubmit:'.$student->id,
                $this->logUrl($internship)
            );
        }

        return $sent;
    }

    private function trainedInWeek(int $deploymentId, ?Document $document, Carbon $weekStart): bool
    {
        $weekEnd = $weekStart->copy()->addDays(6);

        $hasDtr = Dtr::where('deployment_id', $deploymentId)
            ->whereBetween('log_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->exists();

        return $hasDtr || ($document && $this->hasEntriesInWeek($document, $weekStart));
    }

    private function hasEntriesInWeek(Document $document, Carbon $weekStart): bool
    {
        $weekEnd = $weekStart->copy()->addDays(6);

        return $document->entries->contains(function ($entry) use ($weekStart, $weekEnd) {
            $date = Carbon::parse($entry->entry_date);

            return $date->betweenIncluded($weekStart, $weekEnd);
        });
    }

    /**
     * Create the notification unless this student already got the same one
     * (same text); overdue notices may repeat on a different day.
     */
    private function notifyOnce(Student $student, string $title, string $message, bool $oncePerDay = false, ?string $resolveKey = null, ?string $url = null): int
    {
        $existing = Notification::where('user_id', $student->user_id)
            ->where('type', 'weekly_report_reminder')
            ->where('title', $title)
            ->where('message', $message);

        if ($oncePerDay) {
            $existing->whereDate('created_at', Carbon::today());
        }

        if ($existing->exists()) {
            return 0;
        }

        Notification::create([
            'user_id' => $student->user_id,
            'type' => 'weekly_report_reminder',
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'resolve_key' => $resolveKey,
        ]);

        return 1;
    }

    /** Link straight to the student's Form 19 weekly report page. */
    private function logUrl($internship): ?string
    {
        $requirement = DocumentRequirement::where('campus_id', $internship->campus_id)
            ->where('code', 'WEEKLY_REPORT')
            ->first();

        return $requirement ? route('student.documents.log', $requirement, false) : null;
    }
}
