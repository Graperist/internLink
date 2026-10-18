<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Dtr;
use App\Models\Notification;
use App\Support\TimeFrame;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    /**
     * Time Frame monitoring (Form 17). Each intern's days with the hours the
     * HTE supervisor approved - they count right away. The instructor can
     * still return a day to the student, which takes its hours back off.
     */
    public function index(Request $request): View
    {
        // Shared module: the coordinator sees the whole campus, an
        // instructor sees their own program.
        $instructor = auth()->user()->instructor;

        $deployedStudents = $instructor->visibleStudents()
            ->with(['user', 'program', 'currentInternship.deployment.hte'])
            ->whereHas('currentInternship.deployment', fn ($q) => $q->whereIn('status', ['active', 'completed']))
            ->get();

        $deploymentIds = $deployedStudents->map(fn ($s) => $s->currentInternship?->deployment?->id)->filter();
        $allDays = Dtr::whereIn('deployment_id', $deploymentIds)->get()->groupBy('deployment_id');

        $rows = $deployedStudents->map(function ($student) use ($allDays) {
            $internship = $student->currentInternship;
            $days = $allDays->get($internship?->deployment?->id, collect());

            return [
                'student' => $student,
                'deployment' => $internship?->deployment,
                'required' => (float) ($internship?->required_hours ?? $student->program?->required_hours ?? 0),
                'approved' => (float) $days->where('status', 'approved')->sum('hours_logged'),
                'waiting' => $days->where('status', 'pending')->count(),
                'declined' => $days->where('status', 'flagged')->count(),
                'last' => $days->max('log_date'),
            ];
        })->sortBy(fn ($row) => $row['student']->user->name)->values();

        $selected = $request->filled('student') ? $rows->firstWhere('student.id', (int) $request->query('student')) : null;
        $days = $selected
            ? Dtr::where('deployment_id', $selected['deployment']->id)->with('decliner')->orderByDesc('log_date')->get()
            : collect();

        return view('instructor.monitoring.index', compact('rows', 'selected', 'days'));
    }

    /** Return a day to the student to be corrected (takes approved hours back off). */
    public function returnDay(Request $request, Dtr $dtr): RedirectResponse
    {
        $student = $dtr->deployment?->internship?->student;
        abort_unless($student && auth()->user()->instructor->visibleStudents()->whereKey($student->id)->exists(), 403);
        abort_unless($dtr->status === 'approved', 422, 'Only an approved day can be returned.');

        $validated = $request->validate(['flag_reason' => ['required', 'string', 'max:255']],
            ['flag_reason.required' => 'Tell the student why the day is returned.']);

        DB::transaction(function () use ($dtr, $student, $validated) {
            $dtr = Dtr::whereKey($dtr->id)->lockForUpdate()->firstOrFail();
            if ($dtr->status === 'approved') {   // re-checked under the lock
                $student->decrement('hours_rendered', min((float) $student->hours_rendered, (float) $dtr->hours_logged));
            }
            $dtr->update([
                'status' => 'flagged',
                'verified_by' => null,
                'flagged_at' => now(),
                'flag_reason' => $validated['flag_reason'],
                'flag_note' => null,
                'flag_target' => 'both',
                'declined_by_user_id' => auth()->id(),
                'resolved_by' => null,
                'resolved_at' => null,
            ]);
            Notification::create([
                'user_id' => $student->user_id,
                'type' => 'dtr',
                'resolve_key' => 'dtr:'.$dtr->id,
                'title' => 'Time returned by your instructor: '.$dtr->log_date->format('M d, Y'),
                'message' => $validated['flag_reason'].'. Correct the time and send it again to your supervisor.',
                'url' => route('student.dtr', ['edit' => $dtr->id], false),
            ]);
        });

        return redirect()->route('instructor.monitoring', ['student' => $student->id])
            ->with('status', 'Returned '.$dtr->log_date->format('M d, Y').' ('.TimeFrame::format($dtr->hours_logged).' hours) to '.$student->user->name.'.');
    }
}
