<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    protected $fillable = ['code', 'name', 'ced_name', 'college_dean_name', 'address', 'status'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function instructors()
    {
        return $this->hasMany(Instructor::class);
    }

    public function htes()
    {
        return $this->hasMany(Hte::class);
    }

    public function internships()
    {
        return $this->hasMany(Internship::class);
    }
}
