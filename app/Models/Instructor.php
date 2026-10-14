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
