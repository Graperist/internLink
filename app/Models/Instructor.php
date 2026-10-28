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

    /**
     * Narrow an Internship query to this person's own program - for what is
     * theirs as a program instructor (evaluations), the coordinator included.
     */
    public function limitToOwnProgram($query)
    {
        return $query->where('campus_id', $this->campus_id)->where('program_id', $this->program_id);
    }

    /**
     * Evaluations scope: the OJT Coordinator sees every program of the
     * campus; a subject instructor only their own program.
     */
    public function limitToEvaluationScope($query)
    {
        return $this->isCoordinator()
            ? $query->where('campus_id', $this->campus_id)
            : $this->limitToOwnProgram($query);
    }

    /** Is this student / internship in this person's own program? */
    public function ownsProgram(Student|Internship|null $record): bool
    {
        return $record !== null && (int) $record->campus_id === (int) $this->campus_id && $this->ownsProgramOf($record);
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

    /**
     * Partner Agency repository entries are kept per program: only that
     * program's instructor (the coordinator included, for the program they
     * teach) may add, edit or remove them. The coordinator views all.
     */
    public function canManageListing(HteListing $listing): bool
    {
        if ((int) $listing->campus_id !== (int) $this->campus_id) {
            return false;
        }
        // The OJT Coordinator manages every program's partner agencies on the campus.
        if ($this->isCoordinator()) {
            return true;
        }

        return $this->program_id !== null && (int) $listing->program_id === (int) $this->program_id;
    }

    /**
     * The subject instructor a new student of this program is placed under:
     * this person when it is their own program, otherwise the program's
     * active instructor on the campus (null when it has none yet).
     */
    public function handlerFor(Program $program): ?Instructor
    {
        if ((int) $this->program_id === (int) $program->id) {
            return $this;
        }

        return self::where('campus_id', $this->campus_id)
            ->where('program_id', $program->id)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->orderBy('id')
            ->get()
            ->sortBy(fn ($i) => $i->isCoordinator() ? 1 : 0)   // the program's own instructor first
            ->first();
    }

    /** Can add partner agencies / student accounts: the coordinator, or an instructor with a program. */
    public function canAddForPrograms(): bool
    {
        return $this->isCoordinator() || $this->program_id !== null;
    }

    /**
     * The program a new record is added under: the coordinator picks any
     * program on the campus; an instructor always uses their own.
     */
    public function programForNewRecord($requested): ?int
    {
        if ($this->isCoordinator() && $requested !== null && $requested !== '') {
            return Program::where('campus_id', $this->campus_id)->whereKey($requested)->exists() ? (int) $requested : null;
        }

        return $this->program_id;
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
