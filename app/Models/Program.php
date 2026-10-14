<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = ['campus_id', 'code', 'name', 'required_hours'];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
