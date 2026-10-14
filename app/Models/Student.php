<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = ['user_id', 'campus_id', 'program_id', 'instructor_id', 'hours_rendered'];

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

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }

    public function internships()
    {
        return $this->hasMany(Internship::class);
    }

    // Convenience: the student's current internship, including the completed
    // record so post-OJT requirements remain accessible after training.
    public function currentInternship()
    {
        return $this->hasOne(Internship::class)->whereIn('status', ['pre_deployment', 'deployed', 'completed'])->latestOfMany();
    }
        // Reaches Document rows through Internship - powers the Documents column
    // on the Intern Master List without needing the upload UI built yet.
    public function documents()
    {
        return $this->hasManyThrough(Document::class, Internship::class, 'student_id', 'internship_id');
    }
}
