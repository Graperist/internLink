<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\User;
use App\Support\AccountDeleter;
use App\Support\AuditLogger;
use App\Support\CampusManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin only: delete a campus, or an account (coordinator,
 * instructor, HTE, student). Deleting can't be undone, so a campus needs
 * its code typed to confirm.
 */
class DeleteController extends Controller
{
    private const ROLE_NAMES = [
        'coordinator' => 'OJT Coordinator',
        'instructor' => 'Subject Instructor',
        'hte_representative' => 'HTE account',
        'student' => 'Student Intern',
    ];

    public function campus(Request $request, Campus $campus): RedirectResponse
    {
        if (strtoupper(trim((string) $request->input('confirm_code'))) !== strtoupper($campus->code)) {
            throw ValidationException::withMessages(['confirm_code' => "To delete {$campus->name}, type its code {$campus->code} to confirm."]);
        }

        $name = $campus->name;
        $accounts = $campus->users()->count();
        AccountDeleter::campus($campus);
        AuditLogger::record('admin.campus_deleted', "Deleted campus {$campus->code} - {$name} with its {$accounts} ".\Illuminate\Support\Str::plural('account', $accounts).' and all its records.', $request->user(), $request);

        return redirect()->route('admin.campuses.index')->with('status', "{$name} and everything on it were deleted.");
    }

    /** From OJT Coordinators (Super Admin portal). */
    public function coordinator(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isCoordinator(), 404);

        return $this->deleteAccount($request, $user, $request->user(), route('admin.coordinators.index'));
    }

    /**
     * From a campus the Super Admin opened with Manage: an instructor,
     * HTE or student account of that campus (Instructors, HTE Accounts,
     * Students pages).
     */
    public function campusAccount(Request $request, User $user): RedirectResponse
    {
        $admin = CampusManager::superAdmin();
        abort_unless($admin, 403);
        abort_unless((int) $user->campus_id === (int) $request->user()->campus_id, 404);
        abort_unless(in_array($user->role?->code, ['instructor', 'hte_representative', 'student'], true), 404);

        return $this->deleteAccount($request, $user, $request->user(), url()->previous());
    }

    private function deleteAccount(Request $request, User $user, User $actor, string $back): RedirectResponse
    {
        $label = self::ROLE_NAMES[$user->role?->code] ?? 'account';
        $who = "{$user->name} ({$user->id_number})";
        $campusId = $user->campus_id;

        AccountDeleter::user($user);
        AuditLogger::record('admin.account_deleted', "Deleted the {$label} {$who} and their records.", $actor, $request, campusId: $campusId);

        return redirect()->to($back)->with('status', "The {$label} {$who} was deleted.");
    }
}
