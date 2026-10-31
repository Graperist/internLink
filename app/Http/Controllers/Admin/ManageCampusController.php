<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Support\AuditLogger;
use App\Support\CampusManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Super Admin: work in a campus with the OJT Coordinator's tools, under
 * the Super Admin's own account (the coordinator's account is never used).
 */
class ManageCampusController extends Controller
{
    public function start(Request $request, Campus $campus): RedirectResponse
    {
        abort_unless($campus->status === 'active', 404);   // restore an archived campus first

        $request->session()->put(CampusManager::SESSION_KEY, $campus->id);
        AuditLogger::record('admin.campus_opened', "Started managing {$campus->name}.", $request->user(), $request, campusId: $campus->id);

        return redirect()->route('instructor.dashboard');
    }

    public function leave(Request $request): RedirectResponse
    {
        abort_unless(CampusManager::active(), 403);

        $campus = CampusManager::campus();
        AuditLogger::record('admin.campus_closed', 'Back to the Super Admin portal'.($campus ? " from {$campus->name}" : '').'.', $request->user(), $request, campusId: $campus?->id);
        $request->session()->forget(CampusManager::SESSION_KEY);

        return redirect()->route('admin.dashboard');
    }
}
