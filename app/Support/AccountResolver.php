<?php

namespace App\Support;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Works out who is signing in from the Student / Employee ID alone.
 *
 * The login and forgot-password screens no longer ask for a campus or a
 * role. Instead:
 *
 *   - CAMPUS is read from the code inside the ID.
 *       Student IDs:  23-AC-0092     -> "AC"  -> Alaminos City Campus
 *       Employee IDs: ACC-COORD-001  -> "ACC" -> Alaminos City Campus
 *                     BC-INST-004    -> "BC"  -> Bayambang Campus
 *     A segment counts if it equals a campus's own code (ACC, BC, LC, UC)
 *     or one of the short student-number aliases in ALIASES below.
 *
 *   - ROLE always comes from the database (users.role_id). It is never
 *     guessed from the ID's shape, because instructors, coordinators and
 *     HTE supervisors all share the same employee-style format.
 *
 * The account itself is found by its ID. IDs are kept unique across all
 * campuses now, but older data was only unique per campus, so if the same
 * ID exists on two campuses the campus code inside the ID decides which.
 */
class AccountResolver
{
    /**
     * Short campus codes used inside student numbers that differ from the
     * campus's own code. Key = code as it appears in the ID, value =
     * campuses.code. Add a line here if another campus's student numbers
     * use a different abbreviation from its employee IDs.
     */
    public const ALIASES = [
        'AC' => 'ACC', // Alaminos City Campus student numbers: 23-AC-0092
    ];

    public static function normalize(string $idNumber): string
    {
        return strtoupper(trim($idNumber));
    }

    /**
     * Every code that identifies a campus inside an ID, mapped to the
     * campus. Used by the server and passed to the login page so it can
     * show the detected campus while the user types.
     *
     * @return Collection<string, Campus>
     */
    public static function campusCodeMap(): Collection
    {
        $campuses = Campus::where('status', 'active')->get()->keyBy(fn ($c) => strtoupper($c->code));

        $map = $campuses->all();
        foreach (self::ALIASES as $alias => $code) {
            if (isset($campuses[$code]) && ! isset($map[$alias])) {
                $map[$alias] = $campuses[$code];
            }
        }

        return collect($map);
    }

    /** The campus named by the code inside the ID, or null if none matches. */
    public static function campusFromId(string $idNumber): ?Campus
    {
        $map = self::campusCodeMap();

        foreach (preg_split('/[^A-Z0-9]+/', self::normalize($idNumber)) as $segment) {
            if ($segment !== '' && isset($map[$segment])) {
                return $map[$segment];
            }
        }

        return null;
    }

    /** The account this ID belongs to, or null if there isn't exactly one. */
    public static function findUser(string $idNumber): ?User
    {
        $normalized = self::normalize($idNumber);
        if ($normalized === '') {
            return null;
        }

        $matches = User::with(['role', 'campus'])
            ->whereRaw('UPPER(TRIM(id_number)) = ?', [$normalized])
            ->get();

        if ($matches->count() <= 1) {
            return $matches->first();
        }

        // Same ID on more than one campus (legacy data) - let the ID's own
        // campus code decide. If that still doesn't settle it, refuse
        // rather than sign someone into the wrong account.
        $campus = self::campusFromId($normalized);
        $onCampus = $campus ? $matches->where('campus_id', $campus->id) : collect();

        return $onCampus->count() === 1 ? $onCampus->first() : null;
    }
}
