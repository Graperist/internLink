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
        return view('auth.login');
    }

    /**
     * The campus shown under the ID box as the user types. It comes from the
     * account itself - the campus the student was enrolled in (or the staff
     * member was added to) - not from the letters in the ID, so a student
     * number like 23-AC-0093 shows the campus they actually belong to.
     * Rate-limited, and it only ever returns a campus name and role label.
     */
    public function campusLookup(Request $request): \Illuminate\Http\JsonResponse
    {
        $id = trim((string) $request->query('id', ''));
        $user = mb_strlen($id) >= 4 ? AccountResolver::findUser($id) : null;
        $campus = $user?->campus;

        return response()->json([
            'campus' => $campus && $campus->status === 'active' ? $this->shortCampusName($campus->name) : null,
            'kind' => match ($user?->role?->code) {
                'student' => 'Student Intern',
                'coordinator' => 'OJT Coordinator',
                'instructor' => 'Subject Instructor',
                'hte_representative' => 'HTE Supervisor',
                'super_admin' => 'Super Admin',
                default => null,
            },
            'all' => $user && $user->isSuperAdmin(),
        ])->header('Cache-Control', 'no-store');
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
                'id_number' => $user->isCoordinator()
                    ? 'This account is archived. Please contact the PSU Intern-Link Super Admin.'
                    : 'This account is not active. Please contact your OJT Coordinator.',
            ]);
        }

        if ($user->campus && $user->campus->status !== 'active') {
            AuditLogger::record('auth.login_failed', 'Sign-in blocked - their campus is archived', $user, $request);

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
