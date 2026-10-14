<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Dtr;
use App\Models\Student;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    /**
     * Figure 5 (Monitoring Dashboard) - today's DTR status and cumulative
     * hours-rendered progress, scoped to actively deployed interns only.
     *
     * Note: every row will show "No DTR" until the Student portal exists,
     * since nothing currently writes to the dtrs table - this page reads
     * real data, it just has nothing to read yet.
     */
    public function index(): View
    {
        $coordinator = auth()->user();
        $campusId = $coordinator->campus_id;

        $deployedStudents = Student::with(['user', 'program', 'currentInternship.deployment.hte'])
            ->where('campus_id', $campusId)
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

        return view('coordinator.monitoring.index', compact('rows'));
    }
}
