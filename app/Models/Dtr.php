<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dtr extends Model
{
    protected static function booted(): void
    {
        // Verifying, flagging or resolving a DTR settles the earlier
        // "awaiting verification" / "flagged" / "re-verification" notices.
        static::updated(function (Dtr $dtr) {
            if ($dtr->wasChanged('status')) {
                \App\Models\Notification::resolveDtr($dtr->id);
            }
        });
    }

    protected $table = 'dtrs';

    protected $fillable = [
        'deployment_id', 'verified_by', 'log_date',
        'time_in', 'time_out', 'original_time_in', 'original_time_out',
        'am_in', 'am_out', 'pm_in', 'pm_out', 'declined_by_user_id',
        'hours_logged', 'notes', 'am_notes', 'status', 'photos',
        'am_status', 'pm_status', 'am_decline_reason', 'am_decline_note', 'pm_decline_reason', 'pm_decline_note', 'approved_hours',
        'flagged_by', 'flagged_at', 'flag_reason', 'flag_note', 'flag_target',
        'resolved_by', 'resolved_at',
        'clock_in_verified_by', 'clock_in_verified_at',
        'clock_out_verified_by', 'clock_out_verified_at',
        'clock_in_latitude', 'clock_in_longitude', 'clock_in_accuracy', 'clock_in_distance_m',
        'clock_in_location_status', 'clock_in_location_flags', 'clock_in_captured_at',
        'clock_out_latitude', 'clock_out_longitude', 'clock_out_accuracy', 'clock_out_distance_m',
        'clock_out_location_status', 'clock_out_location_flags', 'clock_out_captured_at',
    ];

    protected $casts = [
        'photos' => 'array',
        'approved_hours' => 'float',
        'log_date' => 'date',
        'flagged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'clock_in_verified_at' => 'datetime',
        'clock_out_verified_at' => 'datetime',
        'clock_in_captured_at' => 'datetime',
        'clock_out_captured_at' => 'datetime',
        'clock_in_accuracy' => 'float',
        'clock_out_accuracy' => 'float',
        'clock_in_distance_m' => 'float',
        'clock_out_distance_m' => 'float',
        'clock_in_location_flags' => 'array',
        'clock_out_location_flags' => 'array',
    ];

    public function deployment() { return $this->belongsTo(Deployment::class); }
    public function verifier() { return $this->belongsTo(HteRepresentative::class, 'verified_by'); }
    public function flagger() { return $this->belongsTo(HteRepresentative::class, 'flagged_by'); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
    public function decliner() { return $this->belongsTo(User::class, 'declined_by_user_id'); }

    /** Form 17 wording for the student, HTE and instructor. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Approved',
            'flagged' => 'Declined',
            default => 'Waiting for supervisor',
        };
    }

    public const HALVES = ['am' => 'Morning', 'pm' => 'Afternoon'];

    /** Days whose morning or afternoon is still waiting for the supervisor. */
    public function scopeWaiting($query)
    {
        return $query->where(fn ($q) => $q->where('am_status', 'pending')->orWhere('pm_status', 'pending')
            ->orWhere(fn ($old) => $old->whereNull('am_status')->whereNull('pm_status')->where('status', 'pending')));
    }

    /** Days with at least one approved half - what counts and what the instructor sees. */
    public function scopeWithApprovedTime($query)
    {
        return $query->where(fn ($q) => $q->where('approved_hours', '>', 0)->orWhere('am_status', 'approved')->orWhere('pm_status', 'approved')
            ->orWhere(fn ($old) => $old->whereNull('am_status')->whereNull('pm_status')->where('status', 'approved')));
    }

    /** Hours that count: the approved halves (older rows: the whole day once approved). */
    public function countedHours(): float
    {
        if ($this->getAttribute('am_status') === null && $this->getAttribute('pm_status') === null) {
            return $this->status === 'approved' ? (float) $this->hours_logged : 0.0;
        }

        return (float) $this->approved_hours;
    }

    /** Was the morning ('am') or afternoon ('pm') entered? */
    public function hasHalf(string $part): bool
    {
        return $this->period($part) !== '';
    }

    /** 'pending' / 'approved' / 'flagged' (declined) for one half, or null if it wasn't entered. */
    public function halfStatus(string $part): ?string
    {
        if (! $this->hasHalf($part)) {
            return null;
        }

        return $this->getAttribute($part.'_status') ?: ($this->status ?: 'pending');
    }

    public function halfStatusLabel(string $part): string
    {
        return match ($this->halfStatus($part)) {
            'approved' => 'Approved',
            'flagged' => 'Declined',
            null => '',
            default => 'Waiting',
        };
    }

    /** The hours of one half (the lunch break is never counted). */
    public function halfHours(string $part): float
    {
        $in = $this->getAttribute($part.'_in');
        $out = $this->getAttribute($part.'_out');
        if (filled($in) && filled($out)) {
            return round(max(0, \App\Support\TimeFrame::minutes($out) - \App\Support\TimeFrame::minutes($in)) / 60, 2);
        }

        // An old single time in/out sits in one half and carries the day's hours.
        return $this->hasHalf($part) && blank($this->am_in) && blank($this->pm_in) ? (float) $this->hours_logged : 0.0;
    }

    /** The day's own status and approved hours, from its two halves. */
    public function dayStatusFromHalves(): array
    {
        $statuses = collect(array_keys(self::HALVES))->map(fn ($p) => $this->halfStatus($p))->filter()->values();
        $status = match (true) {
            $statuses->isEmpty() => 'pending',
            $statuses->contains('flagged') => 'flagged',
            $statuses->every(fn ($s) => $s === 'approved') => 'approved',
            default => 'pending',
        };
        $approved = collect(array_keys(self::HALVES))->filter(fn ($p) => $this->halfStatus($p) === 'approved')
            ->sum(fn ($p) => $this->halfHours($p));

        return ['status' => $status, 'approved_hours' => round($approved, 2)];
    }

    /** "8:00 AM - 12:00 PM" of a half, only once the supervisor approved it (Form 17, monitoring). */
    public function approvedPeriod(string $part): string
    {
        return $this->halfStatus($part) === 'approved' ? $this->period($part) : '';
    }

    /** The four times a photo is taken for. */
    public const PHOTO_STAMPS = ['am_in' => 'Morning time in', 'am_out' => 'Morning time out', 'pm_in' => 'Afternoon time in', 'pm_out' => 'Afternoon time out'];

    /** ['path' => ..., 'at' => ...] of the photo taken for am_in / am_out / pm_in / pm_out, or null. */
    public function photo(string $stamp): ?array
    {
        $photo = ($this->photos ?? [])[$stamp] ?? null;

        return is_array($photo) && filled($photo['path'] ?? null) ? $photo : null;
    }

    public function photoUrl(string $stamp): ?string
    {
        return $this->photo($stamp) ? route('dtr.photo', [$this, $stamp]) : null;
    }

    /** "8:00 AM - 12:00 PM" for the morning or afternoon, or "" */
    public function period(string $which): string
    {
        $in = $this->{$which.'_in'};
        $out = $this->{$which.'_out'};
        if (blank($in) && blank($out)) {
            $hasSplit = filled($this->am_in) || filled($this->am_out) || filled($this->pm_in) || filled($this->pm_out);
            if ($hasSplit || blank($this->time_in)) {
                return '';
            }
            // Entries from the old clock-in/clock-out DTR have only time_in/time_out:
            // shown under the morning or afternoon by when the clock-in was.
            $isMorning = \Carbon\Carbon::parse($this->time_in)->format('H') < 12;
            if (($which === 'am') !== $isMorning) {
                return '';
            }
            [$in, $out] = [$this->time_in, $this->time_out];
        }

        return \App\Support\TimeFrame::clock($in).' - '.(filled($out) ? \App\Support\TimeFrame::clock($out) : 'no time out');
    }

    public function isClockInVerified(): bool
    {
        return $this->clock_in_verified_at !== null;
    }

    public function isClockOutVerified(): bool
    {
        return $this->clock_out_verified_at !== null;
    }

    public function isFullyVerified(): bool
    {
        return $this->isClockInVerified() && $this->isClockOutVerified();
    }

    /** True when this event's own GPS check did not come back clean. */
    public function clockInHasLocationRisk(): bool
    {
        return $this->clock_in_location_status !== null && $this->clock_in_location_status !== 'verified';
    }

    public function clockOutHasLocationRisk(): bool
    {
        return $this->clock_out_location_status !== null && $this->clock_out_location_status !== 'verified';
    }

    public function hasLocationRisk(): bool
    {
        return ($this->clock_in_location_status !== null && $this->clock_in_location_status !== 'verified')
            || ($this->clock_out_location_status !== null && $this->clock_out_location_status !== 'verified');
    }

    public function flagTargetLabel(): ?string
    {
        return match ($this->flag_target) {
            'clock_in' => 'Clock-In',
            'clock_out' => 'Clock-Out',
            'both' => 'Clock-In and Clock-Out',
            default => null,
        };
    }

    public function locationFlagLabels(): array
    {
        $labels = [];
        $map = [
            'outside_geofence' => 'Outside HTE geofence',
            'low_accuracy' => 'Low GPS accuracy',
            'stale_location_timestamp' => 'Stale location timestamp',
            'hte_location_unconfigured' => 'HTE location is not configured',
            'invalid_location_timestamp' => 'Invalid location timestamp',
        ];

        foreach (array_merge($this->clock_in_location_flags ?? [], $this->clock_out_location_flags ?? []) as $flag) {
            $labels[$flag] = $map[$flag] ?? ucwords(str_replace('_', ' ', $flag));
        }

        return array_values($labels);
    }
}
