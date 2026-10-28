<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AccountResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        // Codes that identify a campus inside an ID (ACC, BC, AC, ...) so the
        // page can show the detected campus as the user types. This only
        // reveals campus names, never whether an account exists.
        $campusCodes = AccountResolver::campusCodeMap()
            ->map(fn ($campus) => $this->shortCampusName($campus->name));
        // In a student number (23-AC-0092) these codes win: "AC" = Alaminos City there.
        $studentCodes = AccountResolver::studentCodeMap()
            ->map(fn ($campus) => $this->shortCampusName($campus->name));

        return view('auth.login', compact('campusCodes', 'studentCodes'));
    }

    /**
     * The sign-in page as everyone sees it, for the coordinator to check the
     * login photos while signed in (the real page sends signed-in users to
     * their dashboard). Signing in is turned off here.
     */
    public function preview()
    {
        return $this->show()->with('preview', true);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'id_number' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ], [
            'id_number.required' => 'Enter your Student or Employee ID.',
        ]);

        // Only the ID and password are asked for. The campus is read from
        // the ID and the role comes from the account itself - the database
        // is the source of truth for both.
        $user = AccountResolver::findUser($credentials['id_number']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            // The screen shows one generic message (so it doesn't reveal which
            // part was wrong); the audit trail records the real reason.
            AuditLogger::record(
                'auth.login_failed',
                'Failed sign-in for ID "'.AuditLogger::clean($credentials['id_number'], 60).'" - '
                    .($user ? 'wrong password' : 'no account with that ID'),
                $user,
                $request,
                campusId: $user?->campus_id ?? AccountResolver::campusFromId($credentials['id_number'])?->id,
            );

            throw ValidationException::withMessages([
                'id_number' => 'The ID or password you entered is incorrect.',
            ]);
        }

        if ($user->status !== 'active') {
            AuditLogger::record('auth.login_failed', 'Sign-in blocked - account is not active', $user, $request);

            throw ValidationException::withMessages([
                'id_number' => 'This account is not active. Please contact your OJT Coordinator.',
            ]);
        }

        if ($user->campus && $user->campus->status !== 'active') {
            AuditLogger::record('auth.login_failed', 'Sign-in blocked - their campus is inactive', $user, $request);

            throw ValidationException::withMessages([
                'id_number' => \App\Http\Middleware\EnsureCampusIsActive::MESSAGE,
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

    /** "Pangasinan State University - Alaminos City Campus" -> "Alaminos City Campus" */
    private function shortCampusName(string $name): string
    {
        return trim(preg_replace('/^.*?\s[-–]\s/u', '', $name));
    }
}
