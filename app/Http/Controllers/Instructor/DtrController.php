<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Dtr;
use App\Models\HteRepresentative;
use App\Models\Instructor;
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
 * When the days are approved, the student submits Form 17 for printing and
 * it follows the usual print-and-sign steps.
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

        $editing = $request->filled('edit')
            ? $days->first(fn ($d) => $d->id === (int) $request->query('edit') && $d->status !== 'approved')
            : null;

        $requirement = $internship ? DocumentRequirement::where('campus_id', $internship->campus_id)->where('code', 'INTERNSHIP_TIME_FRAME')->first() : null;
        $form17 = $requirement ? $internship->documents()->where('document_requirement_id', $requirement->id)->first() : null;

        $totals = [
            'approved' => (float) $days->where('status', 'approved')->sum('hours_logged'),
            'pending' => (float) $days->where('status', 'pending')->sum('hours_logged'),
            'required' => (float) ($internship?->required_hours ?? $student?->program?->required_hours ?? 0),
            'declined' => $days->where('status', 'flagged')->count(),
            'waiting' => $days->where('status', 'pending')->count(),
        ];

        return view('student.dtr', compact('deployment', 'days', 'editing', 'totals', 'requirement', 'form17', 'internship'));
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

        $this->notifySupervisor($dtr, $student, $wasDeclined);

        return redirect()->route('student.dtr')->with('status', ($wasDeclined ? 'Corrected and sent back' : 'Saved and sent').' to your HTE supervisor: '
            .$date->format('M d, Y').' · '.TimeFrame::format($result['hours']).' hours.');
    }

    /** Remove a day that is still waiting or was declined. */
    public function destroy(Dtr $dtr): RedirectResponse
    {
        $student = auth()->user()->student;
        abort_unless($dtr->deployment?->internship?->student_id === $student?->id, 403);
        abort_if($dtr->status === 'approved', 422, 'An approved day can no longer be removed.');

        $date = $dtr->log_date->format('M d, Y');
        Notification::resolve('dtr:'.$dtr->id);
        $dtr->delete();

        return redirect()->route('student.dtr')->with('status', "{$date} removed.");
    }

    /**
     * Send Form 17 for printing once the days are approved: it then goes
     * through the usual steps (instructor approves for printing, student
     * downloads and has it signed, uploads the signed copy).
     */
    public function submitForm(): RedirectResponse
    {
        $student = auth()->user()->student;
        $internship = $student?->currentInternship;
        $deployment = $internship?->deployment;
        abort_unless($deployment, 422, 'You have no deployment yet.');

        $requirement = DocumentRequirement::where('campus_id', $internship->campus_id)->where('code', 'INTERNSHIP_TIME_FRAME')->where('is_active', true)->firstOrFail();
        $days = Dtr::where('deployment_id', $deployment->id)->get();

        if ($days->where('status', 'approved')->isEmpty()) {
            return back()->withErrors(['form17' => 'Your supervisor has to approve at least one day first.']);
        }
        if ($days->where('status', '!=', 'approved')->isNotEmpty()) {
            return back()->withErrors(['form17' => 'Some days are still waiting for your supervisor or were declined. Settle them first, so the printed Form 17 shows every day.']);
        }

        $document = Document::firstOrCreate(
            ['internship_id' => $internship->id, 'document_requirement_id' => $requirement->id],
            ['uploaded_by_user_id' => auth()->id(), 'status' => 'draft']
        );
        abort_unless(in_array($document->status, ['draft', 'returned'], true), 422, 'Form 17 is already with your instructor.');
        $document->update(['status' => 'submitted', 'submitted_at' => now(), 'uploaded_by_user_id' => auth()->id()]);

        Instructor::where('campus_id', $internship->campus_id)->where('program_id', $internship->program_id)->get()
            ->each(fn ($instructor) => Notification::create([
                'user_id' => $instructor->user_id,
                'type' => 'document_status',
                'title' => "{$student->user->name} submitted Form 17 – Internship Time Frame",
                'message' => 'Check it and approve it for printing.',
                'url' => route('instructor.documents', ['student' => $student->id, 'form' => $requirement->id], false),
                'resolve_key' => 'document:'.$document->id,
            ]));

        return redirect()->route('student.dtr')->with('status', 'Form 17 sent to your instructor. Once it is approved for printing, download it from My Documents to have it signed.');
    }

    private function notifySupervisor(Dtr $dtr, $student, bool $corrected): void
    {
        $hteId = $dtr->deployment?->hte_id;
        Notification::resolve('dtr:'.$dtr->id);   // replace an earlier notice for the same day
        HteRepresentative::where('hte_id', $hteId)->pluck('user_id')->each(fn ($userId) => Notification::create([
            'user_id' => $userId,
            'type' => 'dtr',
            'title' => ($corrected ? 'Corrected time: ' : 'Time to approve: ')."{$student->user->name} · ".$dtr->log_date->format('M d, Y'),
            'message' => TimeFrame::format($dtr->hours_logged).' hours ('.trim($dtr->period('am').' / '.$dtr->period('pm'), ' /').'). Approve or decline it in Time Frame Approval.',
            'url' => route('hte.dtr', [], false),
            'resolve_key' => 'dtr:'.$dtr->id,
        ]));
    }
}
