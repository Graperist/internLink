<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountDeleter;
use App\Support\AuditLogger;
use App\Support\CampusManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Super Admin only (while managing a campus): delete an instructor, HTE or
 * student account. Campuses and coordinators are archived, never deleted.
 */
class DeleteController extends Controller
{
    private const ROLE_NAMES = [
        'coordinator' => 'OJT Coordinator',
        'instructor' => 'Subject Instructor',
        'hte_representative' => 'HTE account',
        'student' => 'Student Intern',
    ];

    /**
     * From a campus the Super Admin opened with Manage: an instructor,
     * HTE or student account of that campus (Instructors, HTE Accounts,
     * Students pages).
     */
    public function campusAccount(Request $request, User $user): RedirectResponse
    {
        abort_unless(CampusManager::active(), 403);
        abort_unless((int) $user->campus_id === CampusManager::campusId(), 404);
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
