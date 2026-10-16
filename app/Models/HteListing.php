<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of the Partner Agency repository: this HTE is a partner of this
 * program for this academic year + semester.
 */
class HteListing extends Model
{
    protected $fillable = [
        'campus_id', 'program_id', 'hte_id', 'academic_year', 'semester', 'slots', 'remarks', 'added_by_user_id',
    ];

    protected $casts = ['slots' => 'integer'];

    public function hte()
    {
        return $this->belongsTo(Hte::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function scopeForTerm(Builder $query, string $academicYear, string $semester): Builder
    {
        return $query->where('academic_year', $academicYear)->where('semester', $semester);
    }
}
