<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The editing deadline the Subject Instructor set for one partner agency's evaluations (per program). */
class EvaluationDeadline extends Model
{
    protected $fillable = ['hte_id', 'program_id', 'due_at', 'set_by_user_id'];

    protected $casts = ['due_at' => 'datetime'];
}
