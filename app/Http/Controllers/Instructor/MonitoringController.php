<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Dtr;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    /**
     * Figure 5 (Monitoring Dashboard) - today's DTR status and cumulative
     * hours-rendered progress, scoped to actively deployed interns only.
     */
    public function index(): View
    {
        // Shared module: the coordinator sees the whole campus, an
        // instructor sees their own program.
        $instructor = auth()->user()->instructor;

        $deployedStudents = $instructor->visibleStudents()
            ->with(['user', 'program', 'currentInternship.deployment.hte'])
            ->whereHas('currentInternship.deployment', fn ($q) => $q->where('status', 'active'))
            ->get();

        $deploymentIds = $deployedStudents
            ->map(fn ($s) => $s->currentInternship?->deployment?->id)
            ->filter();

        $todaysDtrs = Dtr::whereIn('deployment_id', $deploymentIds)
            ->whereDate('log_date', today())
            ->get()
            ->keyBy('deployment_id');

        $rows = $deployedStudents->map(function ($student) use ($todaysDtrs) {
            $deploymentId = $student->currentInternship?->deployment?->id;

            return [
                'student' => $student,
                'dtr' => $deploymentId ? $todaysDtrs->get($deploymentId) : null,
            ];
        })->sortBy(fn ($row) => $row['student']->user->name);

        return view('instructor.monitoring.index', compact('rows'));
    }
}
