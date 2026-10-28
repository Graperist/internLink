<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The due date and time the instructor set for one Form 19 week of an intern. */
class Form19DueDate extends Model
{
    protected $table = 'form19_due_dates';

    protected $fillable = ['internship_id', 'week_number', 'due_at', 'set_by_user_id'];

    protected $casts = ['due_at' => 'datetime'];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }
}
