<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coordinator\StoreInstructorRequest;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InstructorController extends Controller
{
    /**
     * Manage Instructors - this is the real fix for "why is a program_id
     * null" bugs: an instructor account created here always has its
     * program set at creation time (required field), so this specific
     * class of bug becomes impossible going forward. Only accounts
     * created outside this flow (tinker, seeders) can end up with a null
     * program_id.
     */
    public function index(Request $request): View
    {
        $campusId = auth()->user()->campus_id;

        $query = Instructor::with(['user', 'program'])
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

        $instructors = $query->latest('id')->paginate(20)->withQueryString();

        $programs = Program::where('campus_id', $campusId)->orderBy('name')->get();

        $coveredProgramIds = Instructor::where('campus_id', $campusId)
            ->whereNotNull('program_id')
            ->pluck('program_id');
        $uncoveredPrograms = $programs->whereNotIn('id', $coveredProgramIds);

        return view('coordinator.instructors.index', compact('instructors', 'programs', 'uncoveredPrograms'));
    }

    public function create(): View
    {
        $programs = Program::where('campus_id', auth()->user()->campus_id)->orderBy('name')->get();

        return view('coordinator.instructors.create', compact('programs'));
    }

    /**
     * Same shape as the student provisioning flow: create the user with a
     * generated temporary password, flash it once, force a password change
     * on first login. program_id is required here, unlike the student
     * flow's old instructor dropdown - there is no "assign later" option.
     */
    public function store(StoreInstructorRequest $request): RedirectResponse
    {
        $coordinator = auth()->user();
        $instructorRole = Role::where('code', 'instructor')->firstOrFail();

        $temporaryPassword = Str::password(10, symbols: false);

        $instructor = DB::transaction(function () use ($request, $coordinator, $instructorRole, $temporaryPassword) {
            $user = User::create([
                'campus_id' => $coordinator->campus_id,
                'role_id' => $instructorRole->id,
                'id_number' => $request->validated('id_number'),
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => Hash::make($temporaryPassword),
                'status' => 'active',
                'must_change_password' => true,
            ]);

            return Instructor::create([
                'user_id' => $user->id,
                'campus_id' => $coordinator->campus_id,
                'program_id' => $request->validated('program_id'),
            ]);
        });

        return redirect()
            ->route('coordinator.instructors.index')
            ->with('provisioned_account', [
                'name' => $instructor->user->name,
                'id_number' => $instructor->user->id_number,
                'temporary_password' => $temporaryPassword,
            ]);
    }

    public function edit(Instructor $instructor): View
    {
        abort_unless($instructor->campus_id === auth()->user()->campus_id, 403);

        $programs = Program::where('campus_id', auth()->user()->campus_id)->orderBy('name')->get();

        return view('coordinator.instructors.edit', compact('instructor', 'programs'));
    }

    public function update(Request $request, Instructor $instructor): RedirectResponse
    {
        abort_unless($instructor->campus_id === auth()->user()->campus_id, 403);

        $validated = $request->validate([
            'program_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('programs', 'id')->where('campus_id', $instructor->campus_id),
            ],
        ]);

        $instructor->update($validated);

        return redirect()
            ->route('coordinator.instructors.index')
            ->with('status', $instructor->user->name.' was reassigned.');
    }

    public function resetPassword(Instructor $instructor): RedirectResponse
    {
        $coordinator = auth()->user();

        abort_unless($instructor->campus_id === $coordinator->campus_id, 403);

        $temporaryPassword = Str::password(10, symbols: false);

        $instructor->user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        return redirect()
            ->route('coordinator.instructors.index')
            ->with('provisioned_account', [
                'name' => $instructor->user->name,
                'id_number' => $instructor->user->id_number,
                'temporary_password' => $temporaryPassword,
            ]);
    }
}
