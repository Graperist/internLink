<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffAccountMail;
use App\Models\Campus;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Super Admin: the OJT Coordinator account of each campus. The coordinator
 * then adds that campus's programs, instructors and HTE accounts.
 */
class CoordinatorController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::with(['campus', 'instructor.program'])
            ->where('role_id', $this->roleId());

        if ($campusId = $request->query('campus_id')) {
            $query->where('campus_id', $campusId);
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('id_number', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        // Grouped by academic year (the year the account was added), newest
        // first, so the list stays short as accounts pile up over the years.
        $groups = $query->get()
            ->sortBy(fn ($u) => ($u->campus?->name ?? '').'|'.$u->name)
            ->groupBy(fn ($u) => \App\Support\Term::forDate($u->created_at)[0])
            ->sortKeysDesc();
        $campuses = Campus::orderBy('name')->get();
        $currentYear = \App\Support\Term::current()[0];

        return view('admin.coordinators.index', compact('groups', 'campuses', 'currentYear'));
    }

    public function create(Request $request): View
    {
        return view('admin.coordinators.create', [
            'campuses' => Campus::where('status', 'active')->orderBy('name')->get(),
            'selectedCampus' => $request->query('campus_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['id_number' => strtoupper(trim((string) $request->input('id_number')))]);
        $data = $request->validate([
            'campus_id' => ['required', Rule::exists('campuses', 'id')->where('status', 'active')],
            'id_number' => ['required', 'string', 'max:100', Rule::unique('users', 'id_number')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'send_email' => ['nullable', 'boolean'],
        ], ['id_number.unique' => 'Another account already uses this ID number.']);

        $campus = Campus::findOrFail($data['campus_id']);
        // Sign-in finds the campus from the code inside the ID.
        $segments = preg_split('/[^A-Z0-9]+/', $data['id_number']);
        if (! in_array(strtoupper($campus->code), $segments, true)) {
            throw ValidationException::withMessages([
                'id_number' => "The ID must include the campus code {$campus->code}, e.g. {$campus->code}-COORD-001.",
            ]);
        }

        $password = Str::password(10, symbols: false);
        $user = DB::transaction(function () use ($data, $campus, $password) {
            $user = User::create([
                'campus_id' => $campus->id,
                'role_id' => $this->roleId(),
                'id_number' => $data['id_number'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($password),
                'status' => 'active',
                'must_change_password' => true,
            ]);
            // The coordinator is also a program instructor; they pick their program in My Profile.
            Instructor::create(['user_id' => $user->id, 'campus_id' => $campus->id]);

            return $user;
        });

        AuditLogger::record('admin.coordinator_added', "Added OJT Coordinator {$user->name} ({$user->id_number}).", auth()->user(), $request, campusId: $campus->id);
        $emailed = $request->boolean('send_email') ? $this->email($user, $password, false) : null;

        return redirect()->route('admin.coordinators.index')->with('provisioned_account', [
            'name' => $user->name, 'id_number' => $user->id_number, 'password' => $password,
            'campus' => $campus->name, 'email' => $user->email, 'emailed' => $emailed, 'reset' => false,
        ]);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless((int) $user->role_id === $this->roleId(), 404);

        $password = Str::password(10, symbols: false);
        $user->update(['password' => Hash::make($password), 'must_change_password' => true]);
        AuditLogger::record('admin.coordinator_password_reset', "Reset the password of OJT Coordinator {$user->name}.", auth()->user(), $request, campusId: $user->campus_id);
        $emailed = $this->email($user, $password, true);

        return redirect()->route('admin.coordinators.index')->with('provisioned_account', [
            'name' => $user->name, 'id_number' => $user->id_number, 'password' => $password,
            'campus' => $user->campus?->name, 'email' => $user->email, 'emailed' => $emailed, 'reset' => true,
        ]);
    }

    /** Deactivate (can't sign in) or reactivate a coordinator, e.g. when the coordinator changes. */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless((int) $user->role_id === $this->roleId(), 404);

        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);
        $active = $user->status === 'active';
        AuditLogger::record($active ? 'admin.coordinator_activated' : 'admin.coordinator_deactivated',
            ($active ? 'Reactivated' : 'Deactivated')." OJT Coordinator {$user->name}.", auth()->user(), $request, campusId: $user->campus_id);

        return back()->with('status', $active
            ? "{$user->name} can sign in again."
            : "{$user->name} is deactivated and can no longer sign in.");
    }

    private function email(User $user, string $password, bool $isReset): bool
    {
        try {
            Mail::to($user->email)->send(new StaffAccountMail($user, $password, 'OJT Coordinator', $user->campus?->name ?? 'Pangasinan State University', $isReset));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function roleId(): int
    {
        return (int) Role::where('code', 'coordinator')->value('id');
    }
}
