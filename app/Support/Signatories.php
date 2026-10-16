<?php

namespace App\Support;

use App\Models\Instructor;
use App\Models\Internship;
use App\Models\User;

/**
 * The names printed on the signature lines of every student form, taken
 * from the records so nobody has to type them:
 *
 *   instructor        Internship / Practicum Subject Instructor of the
 *                     student's program (the one assigned to the
 *                     internship, else the program's instructor)
 *   coordinator       Campus Internship Coordinator (the campus's OJT
 *                     Coordinator account)
 *   department_chair  Department Chair of the student's program (Programs page)
 *   college_dean      College Dean                     (Campus Settings)
 *   ced               Campus Executive Director        (Campus Settings)
 *   registrar         Registrar                        (Campus Settings)
 *   supervisor        HTE contact person of the student's deployment
 *
 * A name that isn't set comes back as '' so the form prints its blank line.
 */
class Signatories
{
    /** @return array<string,string> */
    public static function for(?Internship $internship): array
    {
        $blank = array_fill_keys(['instructor', 'coordinator', 'department_chair', 'college_dean', 'ced', 'registrar', 'supervisor'], '');

        if (! $internship) {
            return $blank;
        }

        $internship->loadMissing('campus', 'program', 'instructor.user', 'deployment.hte', 'student');
        $campus = $internship->campus;
        $programId = $internship->program_id ?: $internship->student?->program_id;

        $instructor = $internship->instructor?->program_id === $programId ? $internship->instructor : null;
        if (! $instructor?->user && $programId) {
            // Prefer a regular instructor account; the coordinator only if
            // they are the one teaching the program.
            $instructor = Instructor::with('user.role')
                ->where('campus_id', $internship->campus_id)
                ->where('program_id', $programId)
                ->whereHas('user', fn ($q) => $q->where('status', 'active'))
                ->get()
                ->sortBy(fn ($i) => $i->user?->role?->code === 'instructor' ? 0 : 1)
                ->first();
        }

        $coordinator = User::where('campus_id', $internship->campus_id)
            ->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->where('code', 'coordinator'))
            ->orderBy('id')
            ->first();

        return [
            'instructor' => trim((string) $instructor?->user?->name),
            'coordinator' => trim((string) $coordinator?->name),
            'department_chair' => trim((string) $internship->program?->department_chair_name),
            'college_dean' => trim((string) $campus?->college_dean_name),
            'ced' => trim((string) $campus?->ced_name),
            'registrar' => trim((string) $campus?->registrar_name),
            'supervisor' => trim((string) $internship->deployment?->hte?->contact_person),
        ] + $blank;
    }
}
