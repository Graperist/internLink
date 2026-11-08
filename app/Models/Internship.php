<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Internship extends Model
{
    protected $fillable = [
        'student_id', 'campus_id', 'program_id', 'instructor_id', 'required_hours', 'status', 'week_one_date',
    ];

    protected $casts = [
        'week_one_date' => 'date',
        'evaluation_deadline_at' => 'datetime',
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

    private ?int $requiredHoursCache = null;

    /**
     * The hours this intern must render: the NUMBER OF HOURS the student put
     * on their Application for Internship (Time Frame, Form 17, progress and
     * the completion check all follow it). Before the application has it,
     * the program's Required Hours set by the coordinator in Programs.
     */
    public function requiredHours(): ?int
    {
        if ($this->requiredHoursCache !== null) {
            return $this->requiredHoursCache;
        }

        $fromApplication = (int) DocumentFieldValue::query()
            ->where('field_key', 'hte_number_of_hours')
            ->whereHas('document', fn ($d) => $d->where('internship_id', $this->id)
                ->whereHas('requirement', fn ($r) => $r->where('code', 'APPLICATION_FOR_OJT')))
            ->latest('id')
            ->value('value');

        $hours = $fromApplication > 0
            ? $fromApplication
            : (int) ($this->program?->required_hours ?? $this->student?->program?->required_hours ?? $this->getAttribute('required_hours') ?? 0);

        return $hours > 0 ? ($this->requiredHoursCache = $hours) : null;
    }
}
