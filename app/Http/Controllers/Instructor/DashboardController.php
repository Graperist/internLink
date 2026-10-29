<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentReview;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Figure 13 - Instructor Dashboard: advisee/deployment counts, documents
     * awaiting this instructor's decision, and recent submission activity -
     * everything scoped to this instructor's own advisees only.
     */
    public function index(): View
    {
        $instructor = auth()->user()->instructor;

        // Documents scoped through the internship's own campus_id/program_id
        // rather than internship.instructor_id - keeps review capability
        // tied to "who teaches this program right now," not to whichever
        // instructor happened to be picked at student-provisioning time.
        //
        // Only documents the instructor can actually review in Document
        // Review: live (active + required) student/HTE requirements. This
        // keeps the dashboard counts and Recent Submissions in step with that
        // page - no legacy forms or seminars.
        // What to act on (documents to review, placements to start) is the
        // person's own program - the coordinator included, since they only
        // review their own program's advisees. Student counts stay campus-wide
        // for the coordinator.
        $documentScope = fn () => Document::whereHas('internship', fn ($q) => $instructor->limitToOwnProgram($q)
        )->whereHas('requirement', fn ($q) => $q
            ->where('is_active', true)
            ->where('is_required', true)
            ->whereIn('audience', ['student', 'coordinator'])
            ->whereNotIn('code', \App\Support\EvaluationRouting::studentToHteCodes())
        );

        $counts = [
            'advisees' => $instructor->visibleStudents()->count(),
            'deployed' => $instructor->visibleStudents()->whereHas(
                'currentInternship.deployment',
                fn ($q) => $q->where('status', 'active')
            )->count(),
            // Placed (deployment created automatically from the accepted
            // Application for OJT) but not yet started by the instructor.
            'awaiting_approval' => $instructor->advisees()->whereHas(
                'currentInternship.deployment',
                fn ($q) => $q->where('status', 'pending')
            )->count(),
            'pending_review' => $documentScope()->whereIn('status', ['submitted', 'for_review'])->count(),
            'reviewed_this_month' => DocumentReview::where('instructor_id', $instructor->id ?? 0)
                ->whereMonth('reviewed_at', now()->month)
                ->whereYear('reviewed_at', now()->year)
                ->count(),
        ];

        $counts['deployment_rate'] = $counts['advisees'] > 0
            ? (int) round(($counts['deployed'] / $counts['advisees']) * 100)
            : 0;

        $recentSubmissions = $documentScope()
            ->with(['requirement', 'internship.student.user', 'internship.student.program'])
            ->whereIn('status', ['submitted', 'for_review'])
            ->latest('submitted_at')
            ->take(5)
            ->get();

        // Coordinator only: campus-level items to keep an eye on.
        $campus = null;
        if ($instructor->isCoordinator()) {
            $campusId = $instructor->campus_id;
            $coveredProgramIds = \App\Models\Instructor::where('campus_id', $campusId)
                ->whereNotNull('program_id')->pluck('program_id');

            $campus = [
                'uncovered_programs' => \App\Models\Program::where('campus_id', $campusId)
                    ->whereNotIn('id', $coveredProgramIds)->count(),
                'not_placed' => $instructor->visibleStudents()->whereDoesntHave('currentInternship.deployment')->count(),
            ];
        }

        return view('instructor.dashboard', compact('counts', 'recentSubmissions', 'campus'));
    }
}
