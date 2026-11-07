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
    public function index(\Illuminate\Http\Request $request): View
    {
        $instructor = auth()->user()->instructor;
        // Counts for one academic year (the current one) and, if picked, one semester.
        $term = \App\Support\TermFilter::fromRequest($request);
        $students = fn () => $term->applyToStudents($instructor->visibleStudents());

        // The Super Admin managing a campus sees the campus's administration
        // (accounts, programs, partners, activity) - not the coordinator's to-do.
        if (\App\Support\CampusManager::active()) {
            return $this->superAdminCampus(\App\Support\CampusManager::campus(), $term);
        }

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
        $documentScope = fn () => Document::whereHas('internship', fn ($q) => $term->applyThrough($instructor->limitToOwnProgram($q), 'student')
        )->whereHas('requirement', fn ($q) => $q
            ->where('is_active', true)
            ->where('is_required', true)
            ->whereIn('audience', ['student', 'coordinator'])
            ->whereNotIn('code', \App\Support\EvaluationRouting::studentToHteCodes())
        );

        $counts = [
            'advisees' => $students()->count(),
            'deployed' => $students()->whereHas(
                'currentInternship.deployment',
                fn ($q) => $q->where('status', 'active')
            )->count(),
            // Placed (deployment created automatically from the accepted
            // Application for OJT) but not yet started by the instructor.
            'awaiting_approval' => $term->applyToStudents($instructor->advisees())->whereHas(
                'currentInternship.deployment',
                fn ($q) => $q->where('status', 'pending')
            )->count(),
            // Forms to review, and signed copies whose signatures need confirming.
            'pending_review' => $documentScope()->where(fn ($q) => $q->whereIn('status', ['submitted', 'for_review'])
                ->orWhere(fn ($c) => $c->where('status', 'completed')->whereNotNull('file_path')))->count(),
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
            ->where(fn ($q) => $q->whereIn('status', ['submitted', 'for_review'])
                ->orWhere(fn ($c) => $c->where('status', 'completed')->whereNotNull('file_path')))
            ->orderByRaw('COALESCE(completed_at, submitted_at) DESC')
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
                'not_placed' => $students()->whereDoesntHave('currentInternship.deployment')->count(),
            ];
        }

        return view('instructor.dashboard', compact('counts', 'recentSubmissions', 'campus', 'term'));
    }

    /** Campus dashboard for the Super Admin: the campus's set-up and accounts. */
    private function superAdminCampus(\App\Models\Campus $campus, \App\Support\TermFilter $term): View
    {
        $year = $term->year === 'all' ? null : $term->year;
        $roleIds = \App\Models\Role::pluck('id', 'code');
        $users = fn (string $role) => \App\Models\User::where('campus_id', $campus->id)->where('role_id', $roleIds[$role] ?? 0);

        $programs = \App\Models\Program::where('campus_id', $campus->id)->orderBy('code')->get();
        $teachingProgramIds = \App\Models\Instructor::where('campus_id', $campus->id)
            ->whereHas('user', fn ($u) => $u->where('status', 'active'))->whereNotNull('program_id')->pluck('program_id');
        $students = \App\Models\Student::where('campus_id', $campus->id);

        $stats = [
            'coordinators' => $users('coordinator')->where('status', 'active')->with('instructor.program')->get(),
            'instructors' => $users('instructor')->where('status', 'active')->count(),
            'programs' => $programs->count(),
            'uncovered' => $programs->whereNotIn('id', $teachingProgramIds)->values(),
            'students' => $term->applyToStudents(clone $students)->whereHas('user', fn ($u) => $u->where('status', '!=', 'archived'))->count(),
            'archived_students' => (clone $students)->whereHas('user', fn ($u) => $u->where('status', 'archived'))->count(),
            'agencies' => \App\Models\HteListing::where('campus_id', $campus->id)
                ->when($year, fn ($q) => $q->where('academic_year', $year))
                ->when($term->semester, fn ($q) => $q->where('semester', $term->semester))
                ->distinct('hte_id')->count('hte_id'),
            'hte_accounts' => $users('hte_representative')->where('status', 'active')->count(),
        ];
        $activity = \App\Models\ActivityLog::with('user')->where('campus_id', $campus->id)
            ->whereNotNull('description')
            ->whereNotIn('action', ['page.view', 'ui.click', 'action.download'])   // changes, not page visits
            ->where('action', 'not like', 'auth.%')                                  // or sign-ins
            ->latest('id')->take(6)->get();

        return view('admin.campus-dashboard', compact('campus', 'year', 'stats', 'activity', 'term'));
    }
}
