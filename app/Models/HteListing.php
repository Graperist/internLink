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

    protected $casts = ['slots' => 'integer', 'archived_at' => 'datetime'];

    /**
     * Archived listings are left out everywhere (students can't choose them,
     * they don't count); HteListing::withArchived() includes them again.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('notArchived', function (Builder $query) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('hte_listings', 'archived_at')) {
                $query->whereNull($query->getModel()->getTable().'.archived_at');
            }
        });
    }

    public static function withArchived(): Builder
    {
        return static::withoutGlobalScope('notArchived');
    }

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
