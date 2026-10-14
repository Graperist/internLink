<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coordinator\StoreStudentRequest;
use App\Models\DocumentRequirement;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StudentController extends Controller
{
    /**
     * Figure 6 - Intern Master List: search, program/status filters,
     * and the All / Deployed / Pending / Incomplete Docs tabs.
     */
    public function index(Request $request): View
    {
        $coordinator = auth()->user();
        $campusId = $coordinator->campus_id;

        // Pre-deployment requirements only (live, required, pre-deployment stage).
        $preDeploymentIds = \App\Support\PreDeployment::requirementIds($campusId);
        $requiredDocsCount = $preDeploymentIds->count();

        // Correlated subquery reused for both the "Incomplete Docs" tab count
        // and the filter itself - a student is "incomplete" if they have
        // fewer approved documents than the campus requires.
        $incompleteDocsSql = \App\Support\PreDeployment::incompleteSql($preDeploymentIds);

        $base = fn () => Student::where('campus_id', $campusId);

        $counts = [
            'all' => $base()->count(),
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

         $query = Student::with(['user', 'program', 'currentInternship.deployment.hte'])
            ->withCount(['documents as approved_documents_count' => fn ($q) => $q->where('documents.status', 'approved')->whereIn('documents.document_requirement_id', $preDeploymentIds)])
            ->withCount(['documents as pending_review_documents_count' => fn ($q) => $q->whereIn('documents.status', ['submitted', 'for_review'])->whereIn('documents.document_requirement_id', $preDeploymentIds)])
            ->where('campus_id', $campusId);

        if ($search = $request->query('search')) {
            $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('id_number', 'like', "%{$search}%")
            );
        }

        if ($programId = $request->query('program_id')) {
            $query->where('program_id', $programId);
        }

        if ($status = $request->query('status')) {
            $query->whereHas('currentInternship.deployment', fn ($q) => $q->where('status', $status));
        }

        $tab = $request->query('tab', 'all');
        match ($tab) {
            'deployed' => $query->whereHas('currentInternship.deployment', fn ($q) => $q->where('status', 'active')),
            'pending' => $query->where(fn ($q) => $q
                ->whereDoesntHave('currentInternship.deployment')
                ->orWhereHas('currentInternship.deployment', fn ($qq) => $qq->where('status', 'pending'))
            ),
            'incomplete' => $requiredDocsCount > 0
                ? $query->whereRaw($incompleteDocsSql)
                : $query->whereRaw('1 = 0'),
            default => null,
        };

        $students = $query->latest('id')->paginate(20)->withQueryString();
        $programs = Program::where('campus_id', $campusId)->orderBy('name')->get();

        return view('coordinator.students.index', compact(
            'students', 'programs', 'counts', 'requiredDocsCount', 'tab'
        ));
    }

    /**
     * Add Intern - the provisioning form.
     */
    public function create(): View
    {
        $coordinator = auth()->user();

        $programs = Program::where('campus_id', $coordinator->campus_id)->orderBy('name')->get();

        return view('coordinator.students.create', compact('programs'));
    }

    /**
     * Provision the account: creates the users row + the students row together,
     * scoped to the coordinator's own campus, with a generated temporary password.
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $coordinator = auth()->user();
        $studentRole = Role::where('code', 'student')->firstOrFail();

        $temporaryPassword = Str::password(10, symbols: false);

        $student = DB::transaction(function () use ($request, $coordinator, $studentRole, $temporaryPassword) {
            $user = User::create([
                'campus_id' => $coordinator->campus_id,
                'role_id' => $studentRole->id,
                'id_number' => $request->validated('id_number'),
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => Hash::make($temporaryPassword),
                'status' => 'active',
                'must_change_password' => true,
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'campus_id' => $coordinator->campus_id,
                'program_id' => $request->validated('program_id'),
                // instructor_id intentionally left null - Subject Instructors
                // are scoped by program now (see Instructor::advisees()),
                // not assigned per student.
                'hours_rendered' => 0,
            ]);

            // Every student needs an Internship record before a Deployment
            // (HTE assignment) can be attached to them - see DeploymentController.
            $program = Program::findOrFail($request->validated('program_id'));

            Internship::create([
                'student_id' => $student->id,
                'campus_id' => $coordinator->campus_id,
                'program_id' => $program->id,
                'required_hours' => $program->required_hours,
                'status' => 'pre_deployment',
            ]);

            return $student;
        });

        return redirect()
            ->route('coordinator.students.index')
            ->with('provisioned_account', [
                'name' => $student->user->name,
                'id_number' => $student->user->id_number,
                'temporary_password' => $temporaryPassword,
            ]);
    }
        /**
     * Generates a new temporary password for a student who lost their
     * original one-time-shown credentials. Reuses the same banner as
     * the initial provisioning flow.
     */
    public function resetPassword(Student $student): RedirectResponse
    {
        $coordinator = auth()->user();

        abort_unless($student->campus_id === $coordinator->campus_id, 403);

        $temporaryPassword = Str::password(10, symbols: false);

        $student->user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        return redirect()
            ->route('coordinator.students.index')
            ->with('provisioned_account', [
                'name' => $student->user->name,
                'id_number' => $student->user->id_number,
                'temporary_password' => $temporaryPassword,
            ]);
    }
}
