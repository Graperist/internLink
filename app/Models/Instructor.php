<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    protected $fillable = ['user_id', 'campus_id', 'program_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    // Legacy direct assignment via students.instructor_id - still settable
    // from the Coordinator's Add Intern form, but no longer what determines
    // an instructor's advisee list in the portal. Kept for backward
    // compatibility; prefer advisees() below for anything portal-facing.
    public function students()
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Every student this instructor actually sees in the portal: same
     * campus, same program - not an Eloquent relation (no direct FK
     * between instructors and students on program_id), just a scoped
     * query builder, since "handles a program" means "sees everyone
     * enrolled in it," not a one-by-one assignment.
     */
    public function advisees()
    {
        return Student::where('campus_id', $this->campus_id)
            ->where('program_id', $this->program_id);
    }

    // ------------------------------------------------------------------
    // Portal scope. The OJT Coordinator is also a program instructor and
    // uses the same portal modules. The only difference is how far they
    // can SEE: an instructor sees their own program, the coordinator sees
    // the whole campus. Acting on a student (approve/return documents,
    // activate a deployment, confirm completion, add interns) is always
    // limited to the person's own program - for the coordinator too.
    // ------------------------------------------------------------------

    public function isCoordinator(): bool
    {
        return (bool) $this->user?->isCoordinator();
    }

    /** Students this person can see in the portal. */
    public function visibleStudents()
    {
        return $this->isCoordinator()
            ? Student::where('campus_id', $this->campus_id)
            : $this->advisees();
    }

    /**
     * Narrow an Internship query (or a whereHas('internship') closure) to
     * what this person can see.
     */
    public function limitInternships($query)
    {
        $query->where('campus_id', $this->campus_id);

        if (! $this->isCoordinator()) {
            $query->where('program_id', $this->program_id);
        }

        return $query;
    }

    /** Can see this student / internship (read-only). */
    public function canView(Student|Internship|null $record): bool
    {
        if (! $record || (int) $record->campus_id !== (int) $this->campus_id) {
            return false;
        }

        return $this->isCoordinator() || $this->ownsProgramOf($record);
    }

    /** Can act on this student / internship (own program only). */
    public function canActOn(Student|Internship|null $record): bool
    {
        return $record
            && (int) $record->campus_id === (int) $this->campus_id
            && $this->ownsProgramOf($record);
    }

    private function ownsProgramOf(Student|Internship $record): bool
    {
        return $this->program_id !== null && (int) $record->program_id === (int) $this->program_id;
    }

    public function internships()
    {
        return $this->hasMany(Internship::class);
    }

    // "reviews" - document reviews performed by this instructor
    public function documentReviews()
    {
        return $this->hasMany(DocumentReview::class);
    }
}
