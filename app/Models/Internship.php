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
     * The hours this student must render: the "Number of Hours" they entered
     * in Form 1 (Application for Internship). Until Form 1 is filled in, the
     * program's required hours are used.
     */
    public function requiredHours(): ?int
    {
        if ($this->requiredHoursCache !== null) {
            return $this->requiredHoursCache;
        }

        $typed = self::form1Hours($this);
        if ($typed) {
            return $this->requiredHoursCache = $typed;
        }

        $fallback = (int) ($this->program?->required_hours ?? $this->student?->program?->required_hours ?? $this->getAttribute('required_hours') ?? 0);

        return $fallback > 0 ? ($this->requiredHoursCache = $fallback) : null;
    }

    /** The whole number of hours typed in this internship's Form 1, or null. */
    public static function form1Hours(self $internship): ?int
    {
        $value = DocumentFieldValue::query()
            ->where('field_key', 'hte_number_of_hours')
            ->whereHas('document', fn ($q) => $q->where('internship_id', $internship->id)
                ->whereHas('requirement', fn ($r) => $r->where('code', 'APPLICATION_FOR_OJT')))
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->value('value');

        $hours = (int) round((float) preg_replace('/[^0-9.]/', '', (string) $value));

        return $hours > 0 ? $hours : null;
    }

    /** Keeps internships.required_hours equal to what Form 1 says (for lists and exports). */
    public function syncRequiredHoursFromForm1(): void
    {
        $this->requiredHoursCache = null;
        if ($hours = self::form1Hours($this)) {
            $this->forceFill(['required_hours' => $hours])->save();
            $this->requiredHoursCache = $hours;
        }
    }
}
