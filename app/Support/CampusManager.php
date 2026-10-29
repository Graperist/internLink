<?php

namespace App\Support;

use App\Models\Campus;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\User;

/**
 * The Super Admin has the OJT Coordinator's access on any campus, under
 * their OWN account - they never sign in as the coordinator. "Manage" on a
 * campus remembers that campus in the session; while it is set, every
 * coordinator page treats the Super Admin as that campus's coordinator
 * (campus-wide view, campus administration). Like a coordinator viewing an
 * instructor's class list: the access is theirs, the account stays theirs.
 *
 * Nothing is saved to the Super Admin's account: the campus and role are
 * only applied in memory for the request.
 */
class CampusManager
{
    public const SESSION_KEY = 'super_admin_campus_id';

    /**
     * What stays with the people of the campus: the Super Admin doesn't
     * review documents, evaluate, or send announcements. (Viewing a
     * student's forms and files from the Repository still works.)
     */
    public const NOT_FOR_SUPER_ADMIN = [
        'instructor.documents',
        'instructor.documents.review',
        'instructor.documents.complete',
        'instructor.documents.confirm-signatures',
        'instructor.documents.print',
        'instructor.documents.remind',
        'instructor.documents.deadline',
        'instructor.documents.week-due',
        'instructor.evaluations',
        'instructor.evaluations.*',
        'instructor.agency-evaluations.*',
        'instructor.announcements',
        'instructor.announcements.*',
    ];

    /** The campus the Super Admin is working in, or null. */
    public static function campusId(): ?int
    {
        $id = session(self::SESSION_KEY);

        return $id ? (int) $id : null;
    }

    /** Is a Super Admin working in a campus right now? */
    public static function active(): bool
    {
        $user = auth()->user();

        return $user && $user->isSuperAdmin() && self::campusId() !== null;
    }

    /** The Super Admin working in a campus, or null. */
    public static function superAdmin(): ?User
    {
        return self::active() ? auth()->user() : null;
    }

    public static function campus(): ?Campus
    {
        return self::active() ? Campus::find(self::campusId()) : null;
    }

    /** Undo apply() on a user object that is used again (e.g. in tests or queued work). */
    public static function restore(User $user): void
    {
        if ($user->realRoleCode === null) {
            return;
        }
        $user->realRoleCode = null;
        $user->campus_id = null;   // the Super Admin belongs to no campus
        $user->syncOriginalAttribute('campus_id');
        $user->unsetRelation('campus');
        $user->unsetRelation('instructor');
        $user->setRelation('role', Role::where('code', 'super_admin')->first());
    }

    /**
     * Gives the (real) Super Admin the coordinator's access for the chosen
     * campus, in memory only: their campus, the coordinator role, and an
     * instructor profile with no program (so they see the whole campus but,
     * like a coordinator with no program, don't act as a program's
     * instructor).
     */
    public static function apply(User $user, int $campusId): void
    {
        $user->realRoleCode = 'super_admin';

        $user->campus_id = $campusId;
        $user->syncOriginalAttribute('campus_id');   // never saved to their account
        $user->setRelation('campus', Campus::find($campusId));
        $user->setRelation('role', Role::where('code', 'coordinator')->first());

        $instructor = new Instructor(['campus_id' => $campusId, 'program_id' => null]);
        $instructor->setRelation('user', $user);
        $instructor->setRelation('program', null);
        $instructor->setRelation('campus', $user->campus);
        $user->setRelation('instructor', $instructor);
    }
}
