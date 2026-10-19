<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Dtr;
use App\Models\HteRepresentative;
use App\Models\Notification;
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
 * The student types the morning and afternoon time in/out for each date;
 * the system totals the hours (the lunch break is not counted). The HTE
 * supervisor approves or declines each day: approved hours count right
 * away, a declined day comes back here to be corrected. No location check.
 * Form 17 is just this record - nothing is submitted to the instructor, who
 * views it for monitoring. It can be downloaded from the approved days.
 */
class DtrController extends Controller
{
    public function index(Request $request): View
    {
        $student = auth()->user()->student;
        $internship = $student?->currentInternship;
        $deployment = $internship?->deployment;

        $days = $deployment
            ? Dtr::where('deployment_id', $deployment->id)->with('decliner')->orderByDesc('log_date')->get()
            : collect();

        // The date shown in the Morning / Afternoon cards: ?date=, the day being
        // corrected (?edit=), else today. Its saved halves are filled in.
        $editDay = $request->filled('edit') ? $days->firstWhere('id', (int) $request->query('edit')) : null;
        $selectedDate = $editDay?->log_date?->toDateString() ?? $request->query('date', today()->toDateString());
        try {
            $selectedDate = Carbon::parse($selectedDate)->toDateString();
        } catch (\Throwable) {
            $selectedDate = today()->toDateString();
        }
        if ($selectedDate > today()->toDateString()) {
            $selectedDate = today()->toDateString();
        }
        $editing = $days->first(fn ($d) => $d->log_date->toDateString() === $selectedDate);


        $totals = [
            'approved' => (float) $days->where('status', 'approved')->sum('hours_logged'),
            'pending' => (float) $days->where('status', 'pending')->sum('hours_logged'),
            'required' => (float) ($internship?->required_hours ?? $student?->program?->required_hours ?? 0),
            'declined' => $days->where('status', 'flagged')->count(),
            'waiting' => $days->where('status', 'pending')->count(),
        ];

        return view('student.dtr', compact('deployment', 'days', 'editing', 'selectedDate', 'totals', 'internship'));
    }

    /** Add a day, or correct one that is waiting or was declined. */
    public function store(Request $request): RedirectResponse
    {
        $student = auth()->user()->student;
        $deployment = $student?->currentInternship?->deployment;
        abort_unless($deployment && $deployment->status === 'active', 422, 'You are not currently deployed.');

        $time = ['nullable', 'date_format:H:i'];
        $data = $request->validate([
            'dtr_id' => ['nullable', 'integer'],
            'part' => ['nullable', 'in:am,pm'],   // the Morning or the Afternoon card
            'log_date' => ['required', 'date', 'before_or_equal:today'],
            'am_in' => $time, 'am_out' => $time, 'pm_in' => $time, 'pm_out' => $time,
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'log_date.before_or_equal' => 'You can only enter a date that has already happened.',
            '*.date_format' => 'Enter the time like 08:00.',
        ]);

        $date = Carbon::parse($data['log_date'])->startOfDay();
        if ($deployment->start_date && $date->lt($deployment->start_date->copy()->startOfDay())) {
            throw ValidationException::withMessages(['log_date' => 'This date is before your deployment started ('.$deployment->start_date->format('M d, Y').').']);
        }
        if ($deployment->end_date && $date->gt($deployment->end_date->copy()->endOfDay())) {
            throw ValidationException::withMessages(['log_date' => 'This date is after your deployment ends ('.$deployment->end_date->format('M d, Y').').']);
        }

        // Saving only the morning or only the afternoon: the other half of that
        // date stays as it was saved.
        $part = $data['part'] ?? null;
        if ($part) {
            $sameDay = Dtr::where('deployment_id', $deployment->id)->whereDate('log_date', $date)->first();
            if ($sameDay?->status === 'approved') {
                throw ValidationException::withMessages([$part.'_in' => 'This day is already approved by your supervisor and can no longer be changed.']);
            }
            if (blank($data[$part.'_in'] ?? null) && blank($data[$part.'_out'] ?? null)) {
                throw ValidationException::withMessages([$part.'_in' => 'Enter the time in and time out.']);
            }
            $other = $part === 'am' ? 'pm' : 'am';
            foreach (['_in', '_out'] as $suffix) {
                $data[$other.$suffix] = $sameDay?->{$other.$suffix} ? Carbon::parse($sameDay->{$other.$suffix})->format('H:i') : null;
            }
            $data['dtr_id'] = $sameDay?->id;
            if (! $request->has('notes')) {
                $data['notes'] = $sameDay?->notes;
            }
        }

        $result = TimeFrame::compute($data['am_in'] ?? null, $data['am_out'] ?? null, $data['pm_in'] ?? null, $data['pm_out'] ?? null);
        if ($result['errors']) {
            throw ValidationException::withMessages($result['errors']);
        }

        $existing = Dtr::where('deployment_id', $deployment->id)->whereDate('log_date', $date)->first();
        if ($existing && (int) ($data['dtr_id'] ?? 0) !== $existing->id) {
            throw ValidationException::withMessages(['log_date' => 'You already entered '.$date->format('M d, Y').'. Edit that day instead.']);
        }
        if ($existing && $existing->status === 'approved') {
            throw ValidationException::withMessages(['log_date' => 'This day is already approved by your supervisor and can no longer be changed.']);
        }
        if (! $existing && filled($data['dtr_id'] ?? null)) {
            // Editing a day but the date was changed to a free date: move it.
            $existing = Dtr::where('deployment_id', $deployment->id)->whereKey($data['dtr_id'])->where('status', '!=', 'approved')->first();
        }

        $wasDeclined = $existing?->status === 'flagged';
        $values = [
            'log_date' => $date->toDateString(),
            'am_in' => $data['am_in'] ?? null, 'am_out' => $data['am_out'] ?? null,
            'pm_in' => $data['pm_in'] ?? null, 'pm_out' => $data['pm_out'] ?? null,
            // First time in / last time out of the day, for the pages that show one pair.
            'time_in' => $data['am_in'] ?? $data['pm_in'] ?? null,
            'time_out' => $data['pm_out'] ?? $data['am_out'] ?? null,
            'hours_logged' => $result['hours'],
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
            'verified_by' => null,
        ];
        if ($wasDeclined) {
            $values += ['resolved_by' => auth()->id(), 'resolved_at' => now()];
        }

        $dtr = DB::transaction(function () use ($existing, $values, $deployment) {
            if ($existing) {
                $existing->update($values);

                return $existing;
            }

            return Dtr::create($values + ['deployment_id' => $deployment->id]);
        });

        $this->notifySupervisor($dtr, $student, $wasDeclined, $part);

        $what = ['am' => 'Morning saved', 'pm' => 'Afternoon saved'][$part] ?? ($wasDeclined ? 'Corrected' : 'Saved');

        return redirect()->route('student.dtr', ['date' => $date->toDateString()])->with('status', $what.' and sent to your HTE supervisor: '
            .$date->format('M d, Y').' · '.TimeFrame::format($result['hours']).' hours for the day.');
    }

    /** Remove a day that is still waiting or was declined. */
    public function destroy(Dtr $dtr): RedirectResponse
    {
        $student = auth()->user()->student;
        abort_unless($dtr->deployment?->internship?->student_id === $student?->id, 403);
        abort_if($dtr->status === 'approved', 422, 'An approved day can no longer be removed.');

        $date = $dtr->log_date->format('M d, Y');
        Notification::resolveDtr($dtr->id);
        $dtr->delete();

        return redirect()->route('student.dtr')->with('status', "{$date} removed.");
    }

    /**
     * Form 17 – Internship Time Frame: the official printout of the days the
     * supervisor approved. Nothing to submit - the student downloads or
     * prints it whenever they need it (whole form, or one week).
     */
    public function form()
    {
        $internship = auth()->user()->student?->currentInternship;
        abort_unless($internship?->deployment, 404, 'You have no deployment yet.');

        return \App\Support\TimeFrameForm::response($internship, printable: true);
    }

    /**
     * The HTE supervisor is notified every time the student saves a time in /
     * time out (the morning, the afternoon, or a correction) - one notice per
     * save, so earlier ones are kept. They all clear once the day is approved
     * or declined.
     */
    private function notifySupervisor(Dtr $dtr, $student, bool $corrected, ?string $part = null): void
    {
        $date = $dtr->log_date->format('M d, Y');
        [$what, $times] = match ($part) {
            'am' => ['Morning time in/out', $dtr->period('am')],
            'pm' => ['Afternoon time in/out', $dtr->period('pm')],
            default => ['Time in/out', trim($dtr->period('am').' / '.$dtr->period('pm'), ' /')],
        };
        $stamp = now()->format('YmdHisv').\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4));
        $title = ($corrected ? 'Corrected ' : '').($corrected ? lcfirst($what) : $what).": {$student->user->name} · {$date}";

        HteRepresentative::where('hte_id', $dtr->deployment?->hte_id)->pluck('user_id')->each(fn ($userId) => Notification::create([
            'user_id' => $userId,
            'type' => 'dtr',
            'title' => $title,
            'message' => "{$times}. Total for the day: ".TimeFrame::format($dtr->hours_logged).' hours. Approve or decline it in Time Frame Approval.',
            'url' => route('hte.dtr', [], false),
            // A key of its own per save (so this notice doesn't replace the
            // earlier ones); resolveDtr() clears them all together.
            'resolve_key' => 'dtr:'.$dtr->id.':'.$stamp,
        ]));
    }
}
