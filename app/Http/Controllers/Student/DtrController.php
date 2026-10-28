<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Dtr;
use App\Support\TimeFrame;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Form 17 - Internship Time Frame, which is also the student's DTR.
 *
 * The student enters, for each date, how many hours they finished that day
 * (no time in / time out). The hours count right away: they are added to the
 * student's rendered hours when saved, adjusted when the day is edited and
 * taken off when it is removed. The HTE supervisor and the instructor see the
 * days; Form 17 is printed from them.
 */
class DtrController extends Controller
{
    /** The most hours one day can have. */
    private const MAX_HOURS = 24;

    public function index(Request $request): View
    {
        $student = auth()->user()->student;
        $internship = $student?->currentInternship;
        $deployment = $internship?->deployment;

        $days = $deployment
            ? Dtr::where('deployment_id', $deployment->id)->orderByDesc('log_date')->get()
            : collect();

        // The day being edited (?edit=), or a new one for ?date= / today.
        $editing = $request->filled('edit') ? $days->firstWhere('id', (int) $request->query('edit')) : null;
        $selectedDate = $editing?->log_date?->toDateString() ?? $request->query('date', today()->toDateString());
        try {
            $selectedDate = Carbon::parse($selectedDate)->toDateString();
        } catch (\Throwable) {
            $selectedDate = today()->toDateString();
        }
        if ($selectedDate > today()->toDateString()) {
            $selectedDate = today()->toDateString();
        }
        $editing ??= $days->first(fn ($d) => $d->log_date->toDateString() === $selectedDate);

        $totals = [
            'rendered' => (float) $days->sum(fn ($d) => $d->countedHours()),
            'required' => (float) ($internship?->requiredHours() ?? 0),
            'days' => $days->count(),
        ];

        return view('student.dtr', compact('deployment', 'days', 'editing', 'selectedDate', 'totals', 'internship'));
    }

    /** Add a day's finished hours, or change a day already entered. */
    public function store(Request $request): RedirectResponse
    {
        $student = auth()->user()->student;
        $deployment = $student?->currentInternship?->deployment;
        abort_unless($deployment && $deployment->status === 'active', 422, 'You are not currently deployed.');

        $data = $request->validate([
            'dtr_id' => ['nullable', 'integer'],
            'log_date' => ['required', 'date', 'before_or_equal:today'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_HOURS],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'log_date.before_or_equal' => 'You can only enter a date that has already happened.',
            'hours.required' => 'Enter how many hours you finished on this day.',
            'hours.numeric' => 'Enter the hours as a number, e.g. 8 or 7.5.',
            'hours.gt' => 'Enter how many hours you finished on this day.',
            'hours.max' => 'A day can have at most '.self::MAX_HOURS.' hours.',
        ]);

        $date = Carbon::parse($data['log_date'])->startOfDay();
        if ($deployment->start_date && $date->lt($deployment->start_date->copy()->startOfDay())) {
            throw ValidationException::withMessages(['log_date' => 'This date is before your deployment started ('.$deployment->start_date->format('M d, Y').').']);
        }
        if ($deployment->end_date && $date->gt($deployment->end_date->copy()->endOfDay())) {
            throw ValidationException::withMessages(['log_date' => 'This date is after your deployment ends ('.$deployment->end_date->format('M d, Y').').']);
        }

        $hours = round((float) $data['hours'], 2);
        $existing = Dtr::where('deployment_id', $deployment->id)->whereDate('log_date', $date)->first();
        if ($existing && filled($data['dtr_id'] ?? null) && (int) $data['dtr_id'] !== $existing->id) {
            throw ValidationException::withMessages(['log_date' => 'You already entered '.$date->format('M d, Y').'. Edit that day instead.']);
        }
        if (! $existing && filled($data['dtr_id'] ?? null)) {
            // Editing a day and moving it to a free date.
            $existing = Dtr::where('deployment_id', $deployment->id)->whereKey($data['dtr_id'])->first();
        }

        $values = [
            'log_date' => $date->toDateString(),
            'hours_logged' => $hours,
            'approved_hours' => $hours,
            'notes' => $data['notes'] ?? null,
            'status' => 'approved',
            // No time in / time out any more.
            'am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null,
            'time_in' => null, 'time_out' => null,
            'am_status' => null, 'pm_status' => null,
        ];

        $before = $existing ? $existing->countedHours() : 0.0;
        DB::transaction(function () use ($existing, $values, $deployment, $student, $hours, $before) {
            if ($existing) {
                $existing->forceFill($values)->save();
            } else {
                Dtr::forceCreate($values + ['deployment_id' => $deployment->id]);
            }
            // The day's hours count right away toward the rendered hours.
            $change = round($hours - $before, 2);
            if ($change !== 0.0) {
                $student->forceFill(['hours_rendered' => max(0, round((float) $student->hours_rendered + $change, 2))])->save();
            }
        });

        return redirect()->route('student.dtr')->with('status', ($existing ? 'Updated ' : 'Saved ').$date->format('M d, Y').': '
            .TimeFrame::format($hours).' '.\Illuminate\Support\Str::plural('hour', $hours == 1 ? 1 : 2).' added to your rendered hours.');
    }

    /** Remove a day; its hours come off the rendered hours. */
    public function destroy(Dtr $dtr): RedirectResponse
    {
        $student = auth()->user()->student;
        abort_unless($dtr->deployment?->internship?->student_id === $student?->id, 403);
        abort_unless($dtr->deployment?->status === 'active', 422, 'Your deployment has ended, so days can no longer be removed.');

        $date = $dtr->log_date->format('M d, Y');
        DB::transaction(function () use ($dtr, $student) {
            $hours = $dtr->countedHours();
            $dtr->delete();
            if ($hours > 0) {
                $student->forceFill(['hours_rendered' => max(0, round((float) $student->hours_rendered - $hours, 2))])->save();
            }
        });

        return redirect()->route('student.dtr')->with('status', "{$date} removed.");
    }

    /**
     * Form 17 – Internship Time Frame: the official printout of the days.
     * Nothing to submit - the student downloads or prints it whenever they
     * need it (whole form, or one week).
     */
    public function form()
    {
        $internship = auth()->user()->student?->currentInternship;
        abort_unless($internship?->deployment, 404, 'You have no deployment yet.');

        return \App\Support\TimeFrameForm::response($internship, printable: true);
    }
}
