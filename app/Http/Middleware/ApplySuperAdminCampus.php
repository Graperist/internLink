<?php

namespace App\Http\Middleware;

use App\Models\Campus;
use App\Support\CampusManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the Super Admin works in a campus ("Manage"), the coordinator pages
 * treat them as that campus's coordinator (see CampusManager). The Super
 * Admin's own pages (/admin) are left as they are.
 */
class ApplySuperAdminCampus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $campusId = CampusManager::campusId();
        if ($user) {
            CampusManager::restore($user);   // start from the real account every request
        }

        if ($user && $campusId && $user->role?->code === 'super_admin'
            && ! $request->routeIs('admin.*', 'logout', 'password.*')) {
            if (Campus::whereKey($campusId)->exists()) {
                // Their own account is edited in the Super Admin's My Account, not here.
                if ($request->routeIs('instructor.profile*')) {
                    return redirect()->route('admin.account');
                }
                CampusManager::apply($user, $campusId);

                if ($request->routeIs(...CampusManager::NOT_FOR_SUPER_ADMIN)) {
                    abort(403, "Document review, evaluations and announcements are the campus's instructors' and coordinator's. The Super Admin can view students' forms and files in the Repository.");
                }
            } else {
                $request->session()->forget(CampusManager::SESSION_KEY);   // the campus was deleted
            }
        }

        return $next($request);
    }
}
