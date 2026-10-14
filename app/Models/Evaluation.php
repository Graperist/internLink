<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    protected $fillable = [
        'deployment_id', 'hte_representative_id', 'rating', 'work_performance', 'professional_conduct', 'technical_skill', 'remarks', 'submitted_at',
        'instructor_id', 'instructor_rating', 'instructor_remarks', 'instructor_submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'rating' => 'decimal:2',
        'instructor_submitted_at' => 'datetime',
        'instructor_rating' => 'decimal:2',
    ];

    public function deployment()
    {
        return $this->belongsTo(Deployment::class);
    }

    public function representative()
    {
        return $this->belongsTo(HteRepresentative::class, 'hte_representative_id');
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * Simple average of the HTE and instructor ratings - only meaningful
     * once both sides have actually rated. No weighting scheme is specified
     * anywhere in the manuscript, so this is a reasonable default, not a
     * cited formula - flag if your paper's methodology says otherwise.
     */
    public function finalGrade(): ?float
    {
        if ($this->rating === null || $this->instructor_rating === null) {
            return null;
        }

        return round(((float) $this->rating + (float) $this->instructor_rating) / 2, 2);
    }

    public function status(): string
    {
        return match (true) {
            $this->rating !== null && $this->instructor_rating !== null => 'completed',
            $this->rating !== null => 'awaiting_instructor',
            $this->instructor_rating !== null => 'awaiting_hte',
            default => 'not_started',
        };
    }
}
