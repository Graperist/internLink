<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Dtr;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    /**
     * Time Frame monitoring (Form 17). The instructor sees only the days the
     * HTE supervisor approved; waiting and declined days stay between the
     * student and the supervisor (the supervisor is the one who returns them).
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
        $allDays = Dtr::whereIn('deployment_id', $deploymentIds)->where('status', 'approved')->get()->groupBy('deployment_id');
        // Only counts of the days not approved yet - so an intern with an empty
        // Time Frame shows why (still with the supervisor, or returned).
        $otherDays = Dtr::whereIn('deployment_id', $deploymentIds)->where('status', '!=', 'approved')
            ->selectRaw('deployment_id, status, count(*) as n')->groupBy('deployment_id', 'status')->get()->groupBy('deployment_id');

        $rows = $deployedStudents->map(function ($student) use ($allDays, $otherDays) {
            $internship = $student->currentInternship;
            $days = $allDays->get($internship?->deployment?->id, collect());
            $other = $otherDays->get($internship?->deployment?->id, collect());

            return [
                'student' => $student,
                'deployment' => $internship?->deployment,
                'required' => (float) ($internship?->required_hours ?? $student->program?->required_hours ?? 0),
                'approved' => (float) $days->sum('hours_logged'),
                'count' => $days->count(),
                'last' => $days->max('log_date'),
                'waiting' => (int) $other->where('status', '!=', 'flagged')->sum('n'),
                'declined' => (int) $other->where('status', 'flagged')->sum('n'),
            ];
        })->sortBy(fn ($row) => $row['student']->user->name)->values();

        $selected = $request->filled('student') ? $rows->firstWhere('student.id', (int) $request->query('student')) : null;
        $days = $selected
            ? Dtr::where('deployment_id', $selected['deployment']->id)->where('status', 'approved')->orderByDesc('log_date')->get()
            : collect();

        // Advisees who don't have a Time Frame yet because they aren't deployed.
        $notDeployed = $instructor->visibleStudents()->with(['user', 'currentInternship.deployment'])
            ->whereNotIn('id', $deployedStudents->pluck('id'))->get()
            ->sortBy(fn ($s) => $s->user?->name)->values();

        return view('instructor.monitoring.index', compact('rows', 'selected', 'days', 'notDeployed'));
    }

    /** Form 17 of one intern, from the approved days: view or download (?download=1, ?week=). */
    public function form(\App\Models\Student $student)
    {
        abort_unless(auth()->user()->instructor->visibleStudents()->whereKey($student->id)->exists(), 403);
        $internship = $student->currentInternship;
        abort_unless($internship?->deployment, 404, 'This intern has no deployment yet.');

        return \App\Support\TimeFrameForm::response($internship, printable: false);
    }
}
