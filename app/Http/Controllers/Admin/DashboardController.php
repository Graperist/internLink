<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Document;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\EvaluationRouting;
use Illuminate\View\View;

/**
 * Super Admin dashboard: university-wide totals and one row per campus -
 * students, deployed, completed and documents waiting for review.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $coordinatorRoleId = Role::where('code', 'coordinator')->value('id');

        $byStage = fn (string $status) => Student::whereHas('currentInternship.deployment', fn ($q) => $q->where('status', $status))
            ->selectRaw('campus_id, count(*) as total')->groupBy('campus_id')->pluck('total', 'campus_id');

        $students = Student::selectRaw('campus_id, count(*) as total')->groupBy('campus_id')->pluck('total', 'campus_id');
        $deployed = $byStage('active');
        $completed = $byStage('completed');

        // Waiting for review: the same documents Document Review counts
        // (live, required student forms; not the evaluations).
        $waiting = Document::whereIn('documents.status', ['submitted', 'for_review'])
            ->whereHas('requirement', fn ($q) => $q
                ->where('is_active', true)
                ->where('is_required', true)
                ->whereIn('audience', ['student', 'coordinator'])
                ->whereNotIn('code', EvaluationRouting::studentToHteCodes()))
            ->join('internships', 'internships.id', '=', 'documents.internship_id')
            ->selectRaw('internships.campus_id as campus_id, count(*) as total')
            ->groupBy('internships.campus_id')
            ->pluck('total', 'campus_id');

        $coordinators = User::where('role_id', $coordinatorRoleId)->where('status', 'active')
            ->orderBy('id')->get(['id', 'campus_id', 'name'])->groupBy('campus_id');

        $campuses = Campus::orderBy('name')->get()->map(fn (Campus $campus) => (object) [
            'campus' => $campus,
            'students' => (int) ($students[$campus->id] ?? 0),
            'deployed' => (int) ($deployed[$campus->id] ?? 0),
            'completed' => (int) ($completed[$campus->id] ?? 0),
            'waiting' => (int) ($waiting[$campus->id] ?? 0),
            'coordinator' => $coordinators->get($campus->id)?->first(),
        ]);

        $totals = [
            'campuses' => $campuses->where('campus.status', 'active')->count(),
            'students' => $campuses->sum('students'),
            'deployed' => $campuses->sum('deployed'),
            'completed' => $campuses->sum('completed'),
            'waiting' => $campuses->sum('waiting'),
        ];

        return view('admin.dashboard', compact('campuses', 'totals'));
    }
}
