<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CampusManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Super Admin: open a campus as its OJT Coordinator, and come back.
 * Everything a coordinator can do is then available for that campus.
 */
class ManageCampusController extends Controller
{
    public function start(Request $request, Campus $campus): RedirectResponse
    {
        $admin = $request->user();
        $coordinator = User::where('campus_id', $campus->id)
            ->where('role_id', Role::where('code', 'coordinator')->value('id'))
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (! $coordinator) {
            return back()->with('status', "{$campus->name} has no active OJT Coordinator account yet. Add one (or reactivate one) first, then open the campus.");
        }

        AuditLogger::record('admin.campus_opened', "Opened {$campus->name} as its OJT Coordinator ({$coordinator->name}).", $admin, $request, campusId: $campus->id);

        Auth::login($coordinator);
        $request->session()->put(CampusManager::SESSION_KEY, $admin->id);

        return redirect()->route('instructor.dashboard');
    }

    public function leave(Request $request): RedirectResponse
    {
        $admin = CampusManager::superAdmin();
        abort_unless($admin, 403);

        $campus = $request->user()?->campus;
        $request->session()->forget(CampusManager::SESSION_KEY);
        Auth::login($admin);
        AuditLogger::record('admin.campus_closed', 'Back to the Super Admin portal'.($campus ? " from {$campus->name}" : '').'.', $admin, $request, campusId: $campus?->id);

        return redirect()->route('admin.dashboard');
    }
}
