<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A campus archived by the Super Admin is closed: its accounts can't
 * sign in, and anyone still signed in is signed out on their next page.
 * The Super Admin (no campus) is never affected, and can still open an
 * archived campus by restoring it.
 */
class EnsureCampusIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // The Super Admin is never closed out (they restore archived campuses).
        $status = $user?->campus_id && ! $user->isSuperAdmin() ? \App\Models\Campus::whereKey($user->campus_id)->value('status') : null;

        if ($status !== null && $status !== 'active') {
            AuditLogger::record('auth.campus_inactive', 'Signed out - their campus is archived', $user, $request);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['id_number' => self::MESSAGE]);
        }

        return $next($request);
    }

    public const MESSAGE = 'Your campus is archived on PSU Intern-Link right now. Please contact the PSU Intern-Link Super Admin.';
}
