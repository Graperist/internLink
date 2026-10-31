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
        $all = $query->get();
        // Archived coordinators (never deleted) are listed apart, with Restore.
        $archived = $all->where('status', '!=', 'active')->sortBy('name')->values();
        $groups = $all->where('status', 'active')
            ->sortBy(fn ($u) => ($u->campus?->name ?? '').'|'.$u->name)
            ->groupBy(fn ($u) => \App\Support\Term::forDate($u->created_at)[0])
            ->sortKeysDesc();
        $campuses = Campus::orderBy('name')->get();
        $currentYear = \App\Support\Term::current()[0];

        return view('admin.coordinators.index', compact('groups', 'campuses', 'currentYear', 'archived'));
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

        // Any ID works (e.g. the real employee number): the campus comes from
        // the account itself, not from letters in the ID.
        $campus = Campus::findOrFail($data['campus_id']);

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

    /**
     * Archive instead of delete (like campuses): the account and everything
     * it did are kept; it just can't sign in. Restore brings it back.
     */
    public function archive(Request $request, User $user): RedirectResponse
    {
        abort_unless((int) $user->role_id === $this->roleId(), 404);

        $user->update(['status' => 'archived']);
        AuditLogger::record('admin.coordinator_archived', "Archived OJT Coordinator {$user->name} ({$user->id_number}).", auth()->user(), $request, campusId: $user->campus_id);

        return back()->with('status', "{$user->name} was archived and can no longer sign in. Their records are kept; restore them from Archived coordinators.");
    }

    public function restore(Request $request, User $user): RedirectResponse
    {
        abort_unless((int) $user->role_id === $this->roleId(), 404);

        $user->update(['status' => 'active']);
        AuditLogger::record('admin.coordinator_restored', "Restored OJT Coordinator {$user->name} ({$user->id_number}).", auth()->user(), $request, campusId: $user->campus_id);

        return back()->with('status', "{$user->name} was restored and can sign in again.");
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
