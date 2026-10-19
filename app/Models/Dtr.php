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
        'hours_logged', 'notes', 'status',
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
