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
        // page - no legacy forms or seminars. The MOA (coordinator-uploaded)
        // is included since instructors review it too.
        $documentScope = fn () => Document::whereHas('internship', fn ($q) => $q
            ->where('campus_id', $instructor->campus_id)
            ->where('program_id', $instructor->program_id)
        )->whereHas('requirement', fn ($q) => $q
            ->where('is_active', true)
            ->where('is_required', true)
            ->whereIn('audience', ['student', 'coordinator'])
            ->whereNotIn('code', \App\Support\EvaluationRouting::studentToHteCodes())
        );

        $counts = [
            'advisees' => $instructor->advisees()->count(),
            'deployed' => $instructor->advisees()->whereHas(
                'currentInternship.deployment',
                fn ($q) => $q->where('status', 'active')
            )->count(),
            // "Awaiting deployment approval" - the closest real signal for
            // this in the current schema is a deployment already assigned
            // by the Coordinator but still sitting in 'pending' status.
            'awaiting_approval' => $instructor->advisees()->whereHas(
                'currentInternship.deployment',
                fn ($q) => $q->where('status', 'pending')
            )->count(),
            'pending_review' => $documentScope()->whereIn('status', ['submitted', 'for_review'])->count(),
            'reviewed_this_month' => DocumentReview::where('instructor_id', $instructor->id)
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

        return view('instructor.dashboard', compact('counts', 'recentSubmissions'));
    }
}
