<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The Form 19 weekly report deadline of the instructor's own program (days
 * after each week ends, 11:59 PM), set from Document Review. Every
 * instructor sets it for their program - the coordinator for theirs too.
 */
class Form19DeadlineController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $instructor = auth()->user()->instructor()->with('program')->first();
        abort_unless($instructor, 403);
        abort_unless($instructor->program, 422, 'Your account has no program yet - ask your coordinator.');

        $validated = $request->validate([
            'weekly_report_due_days' => ['required', 'integer', 'min:0', 'max:14'],
        ], [
            'weekly_report_due_days.*' => 'Enter a number of days from 0 to 14.',
        ]);
        $days = (int) $validated['weekly_report_due_days'];
        $instructor->program->forceFill(['weekly_report_due_days' => $days])->save();

        $text = $days === 0 ? 'due on the last day of each week' : "due {$days} ".\Illuminate\Support\Str::plural('day', $days).' after each week ends';

        return back()->with('status', "Form 19 default deadline for {$instructor->program->code} saved: {$text}. Weeks you gave their own due date keep it.");
    }

    /**
     * The due date and time of one week of an intern's Form 19, picked from a
     * calendar like an MS Teams assignment - or cleared, back to the default.
     */
    public function week(Request $request, \App\Models\Student $student): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor && $instructor->canActOn($student), 403);
        $internship = $student->currentInternship;
        abort_unless($internship, 404);
        $anchor = \App\Support\TrainingWeeks::anchorForInternship($internship);
        abort_unless($anchor, 422, 'This intern has no weeks yet - they start when the deployment starts.');

        $validated = $request->validate([
            'week_number' => ['required', 'integer', 'min:1', 'max:200'],
            'due_at' => ['nullable', 'date_format:Y-m-d\\TH:i', 'required_unless:clear,1'],
            'clear' => ['nullable', 'boolean'],
        ], [
            'due_at.required_unless' => 'Pick the due date and time.',
            'due_at.date_format' => 'Pick the due date and time.',
        ]);
        $week = (int) $validated['week_number'];
        // Setting a due date fixes Week 1, so later entries never move the weeks.
        if (! $internship->getAttribute('week_one_date')) {
            \App\Support\TrainingWeeks::pin($internship);
        }
        $weekStart = $anchor->copy()->addDays(7 * ($week - 1));
        $label = 'Week '.$week.' ('.$weekStart->format('M d').' – '.$weekStart->copy()->addDays(6)->format('M d').')';

        if ($request->boolean('clear')) {
            \App\Models\Form19DueDate::where('internship_id', $internship->id)->where('week_number', $week)->delete();
            \App\Support\TrainingWeeks::forgetDueDates();
            $due = \App\Support\TrainingWeeks::dueDate($weekStart, $internship);
            $message = "{$label} is back to the default due date: ".$due->format('M d, Y g:i A').'.';
        } else {
            $due = \Carbon\Carbon::createFromFormat('Y-m-d\\TH:i', $validated['due_at']);
            if ($due->lt($weekStart)) {
                return back()->withErrors(['due_at' => 'The due date cannot be before the week starts ('.$weekStart->format('M d, Y').').'])->withInput();
            }
            \App\Models\Form19DueDate::updateOrCreate(
                ['internship_id' => $internship->id, 'week_number' => $week],
                ['due_at' => $due, 'set_by_user_id' => auth()->id()]
            );
            \App\Support\TrainingWeeks::forgetDueDates();
            $message = "{$label} is due ".$due->format('M d, Y g:i A').'.';
        }

        // The intern is told, like an assignment's due date in MS Teams.
        $requirement = \App\Models\DocumentRequirement::where('campus_id', $internship->campus_id)->where('code', 'WEEKLY_REPORT')->first();
        \App\Models\Notification::create([
            'user_id' => $student->user_id,
            'type' => 'weekly_report_reminder',
            'title' => "Form 19 {$label} due ".$due->format('M d, g:i A'),
            'message' => "Your instructor set the due date of your Form 19 weekly report for {$label}: ".$due->format('l, M d, Y \\a\\t g:i A').'. A week submitted after that is marked Late.',
            'url' => $requirement ? route('student.documents.log', $requirement, false) : null,
        ]);

        return back()->with('status', $message);
    }
}
