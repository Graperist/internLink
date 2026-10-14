<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Internship extends Model
{
    protected $fillable = [
        'student_id', 'campus_id', 'program_id', 'instructor_id', 'required_hours', 'status',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }

    // "hasOne" per the recommended design - one active deployment per internship
    public function deployment()
    {
        return $this->hasOne(Deployment::class);
    }

    /**
     * Has the student finished the deployment? Post-deployment requirements
     * (Forms 13, 17, 20, 21, 23a/b/c, Narrative Report...) unlock on this.
     *
     * Completion is valid only when both records were updated by the
     * instructor-confirmation transaction. Requiring both statuses prevents
     * post-OJT requirements from unlocking if the records ever drift apart.
     */
    public function hasCompletedDeployment(): bool
    {
        return $this->status === 'completed'
            && $this->deployment?->status === 'completed';
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
