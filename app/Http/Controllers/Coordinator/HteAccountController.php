<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Hte;
use App\Models\HteRepresentative;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HteAccountController extends Controller
{
    /**
     * List HTE portal accounts belonging to HTEs designated for the
     * authenticated coordinator's campus.
     */
    public function index(Request $request): View
    {
        $campusId = auth()->user()->campus_id;

        $query = HteRepresentative::with(['user', 'hte'])
            ->whereHas('hte', fn ($q) => $this->scopeHtes($q));

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                )->orWhereHas('hte', fn ($hte) => $hte
                    ->where('name', 'like', "%{$search}%")
                );
            });
        }

        // Academic year: accounts added for that year, or whose agency is on
        // a partner list that year. Defaults to the current year.
        $currentYear = \App\Support\Term::current()[0];
        // Typed, so any year works (next year's too); "all" shows every year.
        // (The box left empty while showing all years keeps all years.)
        $year = in_array($request->query('academic_year'), ['all', ''], true) ? 'all'
            : \App\Support\Term::normalizeAcademicYear($request->query('academic_year', $currentYear));
        if ($year !== 'all' && ! \App\Support\Term::isValidAcademicYear($year)) {
            $year = $currentYear;
        }
        // 1st or 2nd Semester: accounts added for that semester, or whose
        // agency is on that semester's partner list.
        $semester = \App\Support\Term::isValidSemester($s = $request->query('semester')) ? $s : null;
        if ($year !== 'all' || $semester) {
            $inTerm = fn ($q, $table = '') => $q
                ->when($year !== 'all', fn ($q) => $q->where($table.'academic_year', $year))
                ->when($semester, fn ($q) => $q->where($table.'semester', $semester));
            $query->where(fn ($q) => $q
                ->where(fn ($own) => $inTerm($own, 'hte_representatives.'))
                ->orWhereHas('hte.listings', fn ($l) => $inTerm($l->withoutGlobalScopes(), 'hte_listings.')));
        }

        $archived = (clone $query)->whereHas('user', fn ($u) => $u->where('status', 'archived'))->get()
            ->sortBy(fn ($a) => mb_strtolower($a->user->name))->values();
        $accounts = $query->whereHas('user', fn ($u) => $u->where('status', '!=', 'archived'))
            ->latest('id')->paginate(20)->withQueryString();

        // Catch up accounts whose agency email was changed before emails
        // were kept in step, so Reset password shows (and uses) the new one.
        foreach ($accounts as $account) {
            if ($account->hte && filled($account->hte->contact_email)
                && strcasecmp((string) $account->user?->email, $account->hte->contact_email) !== 0) {
                $account->hte->syncRepresentativeEmails();
                $account->load('user');
            }
        }

        return view('coordinator.hte-accounts.index', compact('accounts', 'archived', 'year', 'currentYear', 'semester'));
    }

    /**
     * Show the provisioning form. The coordinator can only choose an HTE
     * that belongs to the coordinator's own campus.
     */
    public function create(): View
    {
        $htes = $this->scopeHtes(Hte::query())
            ->with('representatives.user')
            ->orderBy('name')
            ->get();

        $suggestedId = \App\Support\HteAccounts::nextIdNumber(auth()->user()->campus);

        [$academicYear, $semester] = \App\Support\Term::current();

        return view('coordinator.hte-accounts.create', compact('htes', 'suggestedId', 'academicYear', 'semester'));
    }

    /**
     * Provision an HTE representative account and link it to one of the
     * coordinator's designated HTEs. A generated password is shown once and
     * the new account is forced to change it after first login.
     */
    public function store(Request $request): RedirectResponse
    {
        $coordinator = auth()->user();
        $campusId = $coordinator->campus_id;

        // The term the account is for: the current one unless chosen.
        $request->merge([
            'academic_year' => \App\Support\Term::normalizeAcademicYear($request->input('academic_year')) ?? \App\Support\Term::current()[0],
            'semester' => $request->input('semester') ?: \App\Support\Term::current()[1],
        ]);

        $validated = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', \Illuminate\Validation\Rule::in(\App\Support\Term::SEMESTERS)],
            'hte_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('htes', 'id')
                    ->where(fn ($q) => $q->where('campus_id', $campusId)),
                function ($attribute, $value, $fail) {
                    if (! $this->scopeHtes(Hte::query())->whereKey($value)->exists()) {
                        $fail('Choose an agency on your program\'s partner list.');
                    }
                },
            ],
            'id_number' => [
                'required',
                'string',
                'max:100',
                // Unique across ALL campuses - sign-in is by ID alone.
                \Illuminate\Validation\Rule::unique('users', 'id_number'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $alreadyHasAccount = HteRepresentative::where('hte_id', $validated['hte_id'])->exists();

        if ($alreadyHasAccount) {
            return back()
                ->withInput()
                ->withErrors(['hte_id' => 'This HTE already has a portal account (it may be under Archived HTE accounts - restore it there). Use edit or reset password on the HTE Accounts page instead.']);
        }

        $hteRole = Role::where('code', 'hte_representative')->firstOrFail();
        $temporaryPassword = Str::password(10, symbols: false);

        $representative = DB::transaction(function () use ($validated, $coordinator, $hteRole, $temporaryPassword) {
            $user = User::create([
                'campus_id' => $coordinator->campus_id,
                'role_id' => $hteRole->id,
                'id_number' => $validated['id_number'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($temporaryPassword),
                'status' => 'active',
                'must_change_password' => false,   // HTE accounts keep this password (evaluations only)
            ]);

            return HteRepresentative::create([
                'user_id' => $user->id,
                'hte_id' => $validated['hte_id'],
                'academic_year' => $validated['academic_year'],
                'semester' => $validated['semester'],
            ]);
        });

        $representative->load(['user', 'hte']);

        // Nothing is emailed now: the sign-in details are sent with the
        // mail icon once the agency's interns have finished (HteAccounts).
        $emailed = null;

        return redirect()
            ->route('instructor.hte-accounts.index')
            ->with('provisioned_account', [
                'name' => $representative->user->name,
                'id_number' => $representative->user->id_number,
                'temporary_password' => $temporaryPassword,
                'hte_name' => $representative->hte->name,
                'email' => $representative->user->email,
                'emailed' => $emailed,
                'mail_error' => null,
                'email_warning' => $representative->hte->contactEmailWarning(),
                'reset' => false,
            ]);
    }

    /**
     * Reset an HTE account's password, scoped to the coordinator's campus.
     */
    public function resetPassword(HteRepresentative $representative): RedirectResponse
    {
        $coordinator = auth()->user();
        $representative->load(['user', 'hte']);

        $this->authorizeCampus($representative);

        // Send to the agency's current contact email (it may have been
        // changed on Partner Agencies after the account was made).
        $representative->hte->syncRepresentativeEmails();
        $representative->load('user');

        $temporaryPassword = Str::password(10, symbols: false);

        $representative->user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => false,   // HTE accounts keep this password (evaluations only)
        ]);

        // Shown once here; emailing is the mail icon's job (HteAccounts).
        $emailed = null;

        return redirect()
            ->route('instructor.hte-accounts.index')
            ->with('provisioned_account', [
                'name' => $representative->user->name,
                'id_number' => $representative->user->id_number,
                'temporary_password' => $temporaryPassword,
                'hte_name' => $representative->hte->name,
                'email' => $representative->user->email,
                'emailed' => $emailed,
                'mail_error' => null,
                'email_warning' => $representative->hte->contactEmailWarning(),
                'reset' => true,
            ]);
    }

    private function authorizeCampus(HteRepresentative $representative): void
    {
        $representative->load(['user', 'hte']);
        abort_unless($representative->hte && $representative->user
            && $this->scopeHtes(Hte::query())->whereKey($representative->hte_id)->exists(), 403);
    }

    /** Edit the account: name, ID number and email. */
    public function edit(HteRepresentative $representative): View
    {
        $this->authorizeCampus($representative);

        return view('coordinator.hte-accounts.edit', ['account' => $representative]);
    }

    public function update(Request $request, HteRepresentative $representative): RedirectResponse
    {
        $this->authorizeCampus($representative);
        $user = $representative->user;

        $request->merge([
            'academic_year' => \App\Support\Term::normalizeAcademicYear($request->input('academic_year')) ?? ($representative->academic_year ?: \App\Support\Term::current()[0]),
            'semester' => $request->input('semester') ?: ($representative->semester ?: \App\Support\Term::current()[1]),
        ]);
        $term = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', \Illuminate\Validation\Rule::in(\App\Support\Term::SEMESTERS)],
        ]);
        $data = $request->validate([
            'id_number' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('users', 'id_number')->ignore($user->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'id_number.unique' => 'This ID number is already used by another account.',
            'email.unique' => 'This email is already used by another PSU Intern-Link account.',
        ]);

        DB::transaction(function () use ($user, $representative, $data, $term) {
            $user->update($data);
            $representative->update($term);
            // The account and the agency share one email: keep them in step
            // (otherwise the agency's old email would be put back).
            $representative->hte->updateQuietly(['contact_email' => $data['email']]);
        });

        \App\Support\AuditLogger::record('hte_account.updated', "Edited the HTE account {$user->id_number} ({$representative->hte->name}).", auth()->user(), $request, $representative, $representative->hte->campus_id);

        return redirect()->route('instructor.hte-accounts.index')
            ->with('status', "{$user->name}'s account for {$representative->hte->name} was updated. The agency's contact email is now {$user->email}.");
    }

    /**
     * Archive instead of delete (like the Super Admin's campuses and
     * coordinators): the account and the evaluations it filed are kept;
     * it just can't sign in until it is restored.
     */
    public function archive(Request $request, HteRepresentative $representative): RedirectResponse
    {
        $this->authorizeCampus($representative);
        DB::transaction(function () use ($representative) {
            $representative->user->update(['status' => 'archived']);
            // The agency leaves the partner lists of this and later terms too
            // (past terms keep it for their records), unless another of its
            // accounts is still active; restoring brings it back.
            $otherActive = HteRepresentative::where('hte_id', $representative->hte_id)
                ->whereKeyNot($representative->id)
                ->whereHas('user', fn ($u) => $u->where('status', '!=', 'archived'))
                ->exists();
            if ($otherActive) {
                return;
            }
            \App\Models\HteListing::withoutGlobalScopes()
                ->where('hte_id', $representative->hte_id)
                ->whereNull('archived_at')
                ->where('academic_year', '>=', \App\Support\Term::current()[0])
                ->update(['archived_at' => now(), 'archived_with_account' => true]);
        });
        \App\Support\AuditLogger::record('hte_account.archived', "Archived the HTE account {$representative->user->id_number} ({$representative->hte->name}).", auth()->user(), $request, $representative, $representative->hte->campus_id);

        return back()
            ->with('status', "{$representative->user->name}'s account for {$representative->hte->name} was archived and can no longer sign in, and {$representative->hte->name} was taken off the partner list. Its records are kept; restore it from Archived HTE accounts.");
    }

    public function restore(Request $request, HteRepresentative $representative): RedirectResponse
    {
        $this->authorizeCampus($representative);
        DB::transaction(function () use ($representative) {
            $representative->user->update(['status' => 'active']);
            // Back on the partner lists it left when the account was archived.
            \App\Models\HteListing::withoutGlobalScopes()
                ->where('hte_id', $representative->hte_id)
                ->where('archived_with_account', true)
                ->update(['archived_at' => null, 'archived_with_account' => false]);
        });
        \App\Support\AuditLogger::record('hte_account.restored', "Restored the HTE account {$representative->user->id_number} ({$representative->hte->name}).", auth()->user(), $request, $representative, $representative->hte->campus_id);

        return back()
            ->with('status', "{$representative->user->name}'s account for {$representative->hte->name} was restored and can sign in again; {$representative->hte->name} is back on the partner list.");
    }

    private function isCoordinator(): bool
    {
        return (bool) auth()->user()->instructor?->isCoordinator();
    }

    /**
     * The agencies whose HTE accounts this user manages: every agency on
     * the campus for the OJT Coordinator; for a Subject Instructor, the
     * agencies on their own program's partner list (any year).
     */
    private function scopeHtes($query)
    {
        $user = auth()->user();
        $query->where('htes.campus_id', $user->campus_id);

        if (! $this->isCoordinator()) {
            $programId = $user->instructor?->program_id;
            $query->whereHas('listings', fn ($l) => $l->where('program_id', $programId ?? 0));
        }

        return $query;
    }
}
