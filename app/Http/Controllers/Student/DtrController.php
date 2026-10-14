<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Dtr;
use App\Models\Instructor;
use App\Models\Notification;
use App\Services\DtrLocationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DtrController extends Controller
{
    public function index(): View
    {
        $student = auth()->user()->student;
        $deployment = $student?->currentInternship?->deployment;
        $todaysDtr = $deployment ? Dtr::where('deployment_id', $deployment->id)->whereDate('log_date', today())->first() : null;
        // Only show the Resolve Flag / Edit DTR action for the current day.
        // Past flagged entries remain part of the DTR/audit history and are not deleted,
        // but they are no longer presented as an outstanding student action.
        $flaggedEntries = $deployment ? Dtr::where('deployment_id', $deployment->id)
            ->where('status', 'flagged')
            ->whereDate('log_date', '>=', today())
            ->with('deployment.hte')
            ->latest('log_date')
            ->latest('id')
            ->get() : collect();
        // Full time in/out history for this student's current deployment, not just
        // the corrected (audit) entries — so the student can see every day they've
        // clocked in/out, not only the ones an HTE later flagged and corrected.
        $dtrHistory = $deployment ? Dtr::where('deployment_id', $deployment->id)
            ->with('deployment.hte')->latest('log_date')->latest('id')->get() : collect();

        return view('student.dtr', compact('deployment', 'todaysDtr', 'flaggedEntries', 'dtrHistory'));
    }

    public function clockIn(Request $request, DtrLocationService $locationService): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
            'captured_at' => ['required', 'integer', 'min:1'],
            'confirm_outside_geofence' => ['nullable', 'boolean'],
        ]);

        $deployment = auth()->user()->student->currentInternship?->deployment;
        abort_unless($deployment && $deployment->status === 'active', 422, 'You are not currently deployed.');

        $existing = Dtr::where('deployment_id', $deployment->id)
            ->whereDate('log_date', today())
            ->first();

        abort_if($existing && $existing->time_in, 422, 'You already clocked in today.');

        $hte = $deployment->hte;
        abort_unless(
            $hte && $hte->latitude !== null && $hte->longitude !== null,
            422,
            'Your HTE location has not been configured by the coordinator yet.'
        );

        $location = $locationService->evaluate(
            $hte,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $validated['accuracy'],
            (int) $validated['captured_at']
        );

        // A location mismatch is never silent. If the captured point is outside
        // the designated HTE geofence, the student must explicitly confirm that
        // they want to continue. Stale/invalid timestamps remain hard failures.
        abort_if(
            in_array('stale_location_timestamp', $location['flags'], true),
            422,
            'The captured location is too old. Please try again.'
        );
        abort_if(
            in_array('invalid_location_timestamp', $location['flags'], true),
            422,
            'The captured location timestamp is invalid. Please try again.'
        );

        if (in_array('outside_geofence', $location['flags'], true)) {
            abort_unless(
                $request->boolean('confirm_outside_geofence'),
                422,
                'Your location is outside your designated HTE. Please confirm that you want to continue.'
            );
        }

        Dtr::updateOrCreate(
            ['deployment_id' => $deployment->id, 'log_date' => today()],
            [
                'time_in' => now()->format('H:i:s'),
                'clock_in_latitude' => $validated['latitude'],
                'clock_in_longitude' => $validated['longitude'],
                'clock_in_accuracy' => $validated['accuracy'],
                'clock_in_distance_m' => $location['distance_m'],
                'clock_in_location_status' => $location['status'],
                'clock_in_location_flags' => $location['flags'],
                'clock_in_captured_at' => $location['captured_at'],
                'status' => 'pending',
            ]
        );

        if (in_array('outside_geofence', $location['flags'], true)) {
            $message = 'WARNING: Your captured location is outside your designated HTE geofence ('.number_format($location['distance_m'], 1).' m away; allowed radius is '.number_format((float) ($hte->geofence_radius_m ?? 100), 0).' m). Your attendance was recorded and sent for HTE review. Please make sure you are at your designated HTE when clocking in.';
        } elseif ($location['status'] === 'verified') {
            $message = 'Location verified within your HTE geofence.';
        } else {
            $message = 'Your location is within the HTE geofence, but GPS accuracy is above the normal threshold. Your attendance was recorded and requires HTE verification.';
        }

        return redirect()->route('student.dtr')
            ->with('status', 'Clocked in at '.now()->format('g:i A').'. '.$message);
    }

    public function clockOut(Request $request, DtrLocationService $locationService): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
            'captured_at' => ['required', 'integer', 'min:1'],
            'confirm_outside_geofence' => ['nullable', 'boolean'],
        ]);

        $deployment = auth()->user()->student->currentInternship?->deployment;
        abort_unless($deployment, 422, 'You are not currently deployed.');

        $hte = $deployment->hte;
        abort_unless(
            $hte && $hte->latitude !== null && $hte->longitude !== null,
            422,
            'Your HTE location has not been configured by the coordinator yet.'
        );

        $location = $locationService->evaluate(
            $hte,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            (float) $validated['accuracy'],
            (int) $validated['captured_at']
        );

        // An outside-geofence clock-out is also recorded so the student is
        // informed and the HTE can review the attendance rather than losing
        // the time-out entirely. Timestamp integrity remains a hard failure.
        abort_if(
            in_array('stale_location_timestamp', $location['flags'], true),
            422,
            'The captured location is too old. Please try again.'
        );
        abort_if(
            in_array('invalid_location_timestamp', $location['flags'], true),
            422,
            'The captured location timestamp is invalid. Please try again.'
        );

        if (in_array('outside_geofence', $location['flags'], true)) {
            abort_unless(
                $request->boolean('confirm_outside_geofence'),
                422,
                'Your location is outside your designated HTE. Please confirm that you want to continue.'
            );
        }

        $dtr = Dtr::where('deployment_id', $deployment->id)
            ->whereDate('log_date', today())
            ->first();

        abort_unless($dtr && $dtr->time_in, 422, 'Clock in before clocking out.');
        abort_if($dtr->time_out, 422, 'You already clocked out today.');

        $timeIn = Carbon::parse($dtr->time_in);
        $clockOutNow = now();
        $hours = round($timeIn->diffInMinutes($clockOutNow) / 60, 2);

        $dtr->update([
            'time_out' => $clockOutNow->format('H:i:s'),
            'clock_out_latitude' => $validated['latitude'],
            'clock_out_longitude' => $validated['longitude'],
            'clock_out_accuracy' => $validated['accuracy'],
            'clock_out_distance_m' => $location['distance_m'],
            'clock_out_location_status' => $location['status'],
            'clock_out_location_flags' => $location['flags'],
            'clock_out_captured_at' => $location['captured_at'],
            'hours_logged' => $hours,
            'status' => 'pending',
        ]);

        if (in_array('outside_geofence', $location['flags'], true)) {
            $message = 'WARNING: Your clock-out location is outside your designated HTE geofence ('.number_format($location['distance_m'], 1).' m away; allowed radius is '.number_format((float) ($hte->geofence_radius_m ?? 100), 0).' m). Your clock-out was recorded and sent for HTE review.';
        } elseif ($location['status'] === 'verified') {
            $message = 'GPS location is within the HTE geofence but the entry still needs HTE sign-off.';
        } else {
            $message = 'GPS location is within the HTE geofence, but accuracy is above the normal threshold. Clock-out was recorded and requires HTE verification.';
        }

        // The DTR entry is now complete (time-in and time-out both present)
        // and sitting in "pending" status, which is exactly what shows up
        // on the HTE's DTR Verification queue. Let every active
        // representative of that HTE know there's something to review.
        $studentUser = auth()->user();
        $hte->representatives()
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->with('user')
            ->get()
            ->each(fn ($rep) => Notification::create([
                'user_id' => $rep->user_id,
                'type' => 'dtr',
                'resolve_key' => 'dtr:'.$dtr->id,
                'title' => "DTR Awaiting Verification — {$studentUser->name}",
                'message' => "Student: {$studentUser->name}\nDTR Date: {$dtr->log_date->format('F d, Y')}\nHours logged: {$hours}\nStatus: Pending your verification.",
            ]));

        return redirect()->route('student.dtr')
            ->with('status', 'Clocked out. Logged '.$hours.' hours. '.$message);
    }

    public function resolveFlag(Request $request, Dtr $dtr): RedirectResponse
    {
        $student = auth()->user()->student;
        $dtr->load([
            'deployment.hte.representatives.user',
            'deployment.internship.student.user',
            'deployment.internship.instructor.user',
            'flagger.user',
        ]);

        abort_unless($dtr->deployment?->internship?->student_id === $student?->id, 403);
        abort_unless($dtr->status === 'flagged', 422, 'This DTR entry is not currently flagged.');
        abort_unless($dtr->time_in && $dtr->time_out, 422, 'Only completed DTR entries can be resolved.');

        $validated = $request->validate([
            'time_in' => ['required', 'date_format:H:i'],
            'time_out' => ['required', 'date_format:H:i', 'after:time_in'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $logDate = $dtr->log_date->format('Y-m-d');
        $correctedIn = Carbon::createFromFormat('Y-m-d H:i', $logDate.' '.$validated['time_in']);
        $correctedOut = Carbon::createFromFormat('Y-m-d H:i', $logDate.' '.$validated['time_out']);
        $hours = round($correctedIn->diffInMinutes($correctedOut) / 60, 2);
        $originalTimeIn = $dtr->original_time_in ?: $dtr->time_in;
        $originalTimeOut = $dtr->original_time_out ?: $dtr->time_out;

        DB::transaction(function () use ($dtr, $correctedIn, $correctedOut, $hours, $validated, $originalTimeIn, $originalTimeOut) {
            $dtr->update([
                'original_time_in' => $originalTimeIn,
                'original_time_out' => $originalTimeOut,
                'time_in' => $correctedIn->format('H:i:s'),
                'time_out' => $correctedOut->format('H:i:s'),
                'hours_logged' => $hours,
                'notes' => $validated['notes'] ?? $dtr->notes,
                'status' => 'pending',
                'verified_by' => null,
                // The student may have changed both times, so the HTE must
                // re-verify the clock-in and the clock-out.
                'clock_in_verified_by' => null,
                'clock_in_verified_at' => null,
                'clock_out_verified_by' => null,
                'clock_out_verified_at' => null,
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
            ]);
        });

        $studentName = $student?->user?->name ?? 'Student';
        $date = $dtr->log_date?->format('F d, Y') ?? $logDate;
        $hteName = $dtr->deployment?->hte?->name ?? 'Assigned HTE';
        $assignedInstructor = $dtr->deployment?->internship?->instructor ?: $student?->instructor;
        $instructorUserId = null;
        if ($assignedInstructor?->user?->status === 'active') {
            $instructorUserId = $assignedInstructor->user_id;
        } else {
            $instructorUserId = Instructor::where('campus_id', $student->campus_id)
                ->where('program_id', $student->program_id)
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->orderBy('id')
                ->value('user_id');
        }

        if ($instructorUserId) {
            Notification::create([
                'user_id' => $instructorUserId,
                'type' => 'dtr',
                'resolve_key' => 'dtr:'.$dtr->id,
                'title' => "DTR Flag Resolved — {$studentName}",
                'message' => "Student: {$studentName}\nDTR Date: {$date}\nHTE: {$hteName}\nStatus: Resolved — Pending HTE Re-verification.\nThe corrected DTR remains the same attendance record; original time-in/time-out and original GPS evidence are preserved for audit.",
            ]);
        }

        // The HTE must know that the flagged entry is ready to be reviewed
        // again. Notify the supervisor who originally flagged it; if that
        // account is no longer available, notify active representatives of
        // the assigned HTE.
        $hteRecipientIds = collect();
        if ($dtr->flagger?->user?->status === 'active') {
            $hteRecipientIds->push($dtr->flagger->user_id);
        } else {
            $hteRecipientIds = $dtr->deployment?->hte?->representatives
                ?->filter(fn ($rep) => $rep->user?->status === 'active')
                ?->pluck('user_id') ?? collect();
        }

        $hteRecipientIds->unique()->each(function ($userId) use ($studentName, $date, $hteName, $dtr) {
            Notification::create([
                'user_id' => $userId,
                'type' => 'dtr',
                'resolve_key' => 'dtr:'.$dtr->id,
                'title' => "DTR Ready for Re-verification — {$studentName}",
                'message' => "Student: {$studentName}\nDTR Date: {$date}\nHTE: {$hteName}\nStatus: Resolved — Pending HTE Re-verification.\nThe student corrected the flagged DTR. Review the original attendance and GPS evidence before verifying.",
            ]);
        });

        return redirect()->route('student.dtr')->with('status', 'DTR correction submitted. Your Subject Instructor and HTE Supervisor have been notified, and the entry is pending HTE re-verification.');
    }
}
