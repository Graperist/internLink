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
            ->whereHas('hte', fn ($q) => $q->where('campus_id', $campusId));

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

        $accounts = $query->latest('id')->paginate(20)->withQueryString();

        return view('coordinator.hte-accounts.index', compact('accounts'));
    }

    /**
     * Show the provisioning form. The coordinator can only choose an HTE
     * that belongs to the coordinator's own campus.
     */
    public function create(): View
    {
        $campusId = auth()->user()->campus_id;

        $htes = Hte::where('campus_id', $campusId)
            ->with('representatives.user')
            ->orderBy('name')
            ->get();

        return view('coordinator.hte-accounts.create', compact('htes'));
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

        $validated = $request->validate([
            'hte_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('htes', 'id')
                    ->where(fn ($q) => $q->where('campus_id', $campusId)),
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
                ->withErrors(['hte_id' => 'This HTE already has a portal account. Use the reset password action on the HTE Accounts page instead.']);
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
                'must_change_password' => true,
            ]);

            return HteRepresentative::create([
                'user_id' => $user->id,
                'hte_id' => $validated['hte_id'],
            ]);
        });

        $representative->load(['user', 'hte']);

        return redirect()
            ->route('coordinator.hte-accounts.index')
            ->with('provisioned_account', [
                'name' => $representative->user->name,
                'id_number' => $representative->user->id_number,
                'temporary_password' => $temporaryPassword,
                'hte_name' => $representative->hte->name,
            ]);
    }

    /**
     * Reset an HTE account's password, scoped to the coordinator's campus.
     */
    public function resetPassword(HteRepresentative $representative): RedirectResponse
    {
        $coordinator = auth()->user();
        $representative->load(['user', 'hte']);

        abort_unless(
            $representative->hte && $representative->hte->campus_id === $coordinator->campus_id,
            403
        );

        $temporaryPassword = Str::password(10, symbols: false);

        $representative->user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        return redirect()
            ->route('coordinator.hte-accounts.index')
            ->with('provisioned_account', [
                'name' => $representative->user->name,
                'id_number' => $representative->user->id_number,
                'temporary_password' => $temporaryPassword,
                'hte_name' => $representative->hte->name,
            ]);
    }

    /**
     * Delete an HTE portal account, scoped to the coordinator's campus.
     *
     * The company (HTE) record itself is kept - only the representative's
     * login is removed. Evaluations the representative already filed are
     * kept too (the ratings are part of the student's record); they simply
     * lose their link to the removed account. DTR verifications/flags are
     * cleared automatically by the database (nullOnDelete).
     */
    public function destroy(HteRepresentative $representative): RedirectResponse
    {
        $coordinator = auth()->user();
        $representative->load(['user', 'hte']);

        abort_unless(
            $representative->hte && $representative->hte->campus_id === $coordinator->campus_id,
            403
        );

        $name = $representative->user?->name ?? 'The representative';
        $hteName = $representative->hte->name;

        DB::transaction(function () use ($representative) {
            $user = $representative->user;

            $representative->evaluations()->update(['hte_representative_id' => null]);
            $representative->delete();
            $user?->delete(); // also removes their notifications (cascade)
        });

        return redirect()
            ->route('coordinator.hte-accounts.index')
            ->with('status', "{$name}'s account for {$hteName} was deleted. You can now create a new account for this HTE.");
    }
}
