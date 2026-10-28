<?php

namespace App\Support;

use App\Models\User;

/**
 * The Super Admin can manage any campus: they step in as that campus's OJT
 * Coordinator (every coordinator page, scoped to that campus) and step back
 * out to the Super Admin portal. The Super Admin's own account id is kept in
 * the session while they're in, and the audit trail names them.
 */
class CampusManager
{
    public const SESSION_KEY = 'managing_super_admin_id';

    /** The Super Admin who is managing a campus right now, or null. */
    public static function superAdmin(): ?User
    {
        $id = session(self::SESSION_KEY);
        if (! $id) {
            return null;
        }

        $admin = User::with('role')->find($id);

        return $admin && $admin->isSuperAdmin() ? $admin : null;
    }

    public static function active(): bool
    {
        return (bool) session(self::SESSION_KEY);
    }
}
