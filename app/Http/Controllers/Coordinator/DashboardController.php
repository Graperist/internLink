<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\Student;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Figure 5 - Dashboard Overview: enrollment/deployment stat cards,
     * recent intern activity, and pending actions - all computed from
     * real data rather than the earlier placeholder copy.
     */
    public function index(): View
    {
        $coordinator = auth()->user();
        $campusId = $coordinator->campus_id;

        // Pre-deployment requirements only (live, required, pre-deployment stage).
        $preDeploymentIds = \App\Support\PreDeployment::requirementIds($campusId);
        $requiredDocsCount = $preDeploymentIds->count();

        // Same "incomplete = fewer approved docs than required" rule used on
        // the Intern List page - kept in one place would be cleaner, but
        // duplicated here for now since the two controllers don't share a base.
        $incompleteDocsSql = \App\Support\PreDeployment::incompleteSql($preDeploymentIds);

        $base = fn () => Student::where('campus_id', $campusId);

        $counts = [
            'total' => $base()->count(),
            'deployed' => $base()->whereHas(
                'currentInternship.deployment',
                fn ($q) => $q->where('status', 'active')
            )->count(),
            'pending' => $base()->where(fn ($q) => $q
                ->whereDoesntHave('currentInternship.deployment')
                ->orWhereHas('currentInternship.deployment', fn ($qq) => $qq->where('status', 'pending'))
            )->count(),
            'incomplete' => $requiredDocsCount > 0
                ? $base()->whereRaw($incompleteDocsSql)->count()
                : 0,
        ];

        $recentActivity = Student::with(['user', 'program', 'currentInternship.deployment.hte'])
            ->where('campus_id', $campusId)
            ->latest('id')
            ->take(5)
            ->get();

        $pendingActions = collect();

        // MOA Pending - real, from the htes table. Each action links straight
        // to that HTE's edit page so the coordinator can act on it immediately.
        $pendingMoaHtes = Hte::where('campus_id', $campusId)
            ->where('moa_status', 'pending')
            ->withCount(['deployments as active_deployments_count' => fn ($q) => $q->where('status', 'active')])
            ->get();

        foreach ($pendingMoaHtes as $hte) {
            $pendingActions->push([
                'title' => "MOA Pending — {$hte->name}",
                'subtitle' => $hte->active_deployments_count > 0
                    ? "Awaiting approval — {$hte->active_deployments_count} student(s) affected"
                    : 'Awaiting approval',
                'route' => route('coordinator.htes.edit', $hte),
            ]);
        }

        // New document submissions in the last 7 days - real, from the documents table.
        $newSubmissions = Document::whereHas('internship', fn ($q) => $q->where('campus_id', $campusId))
            ->where('status', 'submitted')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($newSubmissions > 0) {
            $pendingActions->push([
                'title' => $newSubmissions.' New Document Submission'.($newSubmissions === 1 ? '' : 's'),
                'subtitle' => 'Awaiting instructor review',
                'route' => route('coordinator.documents'),
            ]);
        }

        // Note: "Missing DTR" and "HTE Evaluation Received" from the prototype
        // aren't included yet - both depend on the Monitoring module, which
        // doesn't exist as a real feature yet (dtrs/evaluations tables exist,
        // but nothing currently writes to them).

        $pendingActions = $pendingActions->take(10);

        // Pre-OJT Forms Compliance % - approved docs vs. every currently
        // enrolled student's full requirement list.
        $approvedTotal = Document::whereHas('internship', fn ($q) => $q->where('campus_id', $campusId))
            ->where('status', 'approved')
            ->count();
        $expectedTotal = $counts['total'] * $requiredDocsCount;
        $complianceRate = $expectedTotal > 0 ? (int) round(($approvedTotal / $expectedTotal) * 100) : 0;

        return view('coordinator.dashboard', compact(
            'counts', 'recentActivity', 'pendingActions', 'complianceRate'
        ));
    }
}
