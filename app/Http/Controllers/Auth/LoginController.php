<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        $campuses = Campus::where('status', 'active')->orderBy('name')->get();

        return view('auth.login', compact('campuses'));
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'role' => ['required', 'in:coordinator,instructor,hte_representative,student'],
            'id_number' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // The campus and role selected on the form are NOT trusted as identity.
        // We look the user up by id_number + campus_id, then confirm their
        // actual role in the database matches what they selected before
        // authenticating them - the database is the source of truth.
        $user = \App\Models\User::where('campus_id', $credentials['campus_id'])
            ->where('id_number', $credentials['id_number'])
            ->first();

        if (
            ! $user
            || ! \Illuminate\Support\Facades\Hash::check($credentials['password'], $user->password)
            || $user->role->code !== $credentials['role']
        ) {
            // The screen shows one generic message (so it doesn't reveal which
            // part was wrong); the audit trail records the real reason.
            $reason = match (true) {
                ! $user => 'no account with that ID on this campus',
                // Role matched, so the password check is what failed.
                $user->role->code === $credentials['role'] => 'wrong password',
                default => 'selected role does not match the account (or wrong password)',
            };

            AuditLogger::record(
                'auth.login_failed',
                'Failed sign-in for ID "'.AuditLogger::clean($credentials['id_number'], 60).'" as '.$credentials['role'].' - '.$reason,
                $user,
                $request,
                campusId: (int) $credentials['campus_id'],
            );

            throw ValidationException::withMessages([
                'id_number' => 'The provided credentials do not match our records for the selected campus and role.',
            ]);
        }

        if ($user->status !== 'active') {
            AuditLogger::record('auth.login_failed', 'Sign-in blocked - account is not active', $user, $request);

            throw ValidationException::withMessages([
                'id_number' => 'This account is not active. Please contact your OJT Coordinator.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        AuditLogger::record('auth.login', 'Signed in', $user, $request);

        return redirect()->intended(route($user->dashboardRouteName()));
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditLogger::record('auth.logout', 'Signed out', $request->user(), $request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
