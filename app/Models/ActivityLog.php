<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of the audit trail.
 *
 * Append-only by design: an audit trail that can be edited or deleted from
 * inside the app isn't worth much, so Eloquent updates/deletes are refused.
 * (Old rows can still be pruned deliberately with a bulk query builder
 * delete, e.g. a scheduled retention job.)
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'campus_id', 'role_code', 'action',
        'subject_type', 'subject_id', 'description',
        'route_name', 'method', 'path', 'ip_address', 'user_agent',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Coarse category used for the icon and the filter tabs in the pop-up:
     * click | page | action | security
     */
    public function getKindAttribute(): string
    {
        return match (true) {
            str_starts_with($this->action, 'auth.'),
            str_starts_with($this->action, 'security.') => 'security',
            $this->action === 'ui.click' => 'click',
            str_starts_with($this->action, 'page.') => 'page',
            default => 'action',
        };
    }

    /**
     * True only for events worth a second look (failed sign-in, blocked
     * access). Routine sign-ins/outs are still filed under "security" but
     * shouldn't be drawn like alarms.
     */
    public function getIsAlertAttribute(): bool
    {
        return in_array($this->action, ['auth.login_failed', 'security.denied'], true);
    }

    /**
     * What a given viewer is allowed to see in the audit pop-up. This is the
     * single place that decides visibility - the feed endpoint relies on it.
     *
     *  - Coordinator: everything on their own campus.
     *  - Instructor:  their own activity + the students they advise.
     *  - Anyone else (students, HTE reps): nothing.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return match ($viewer->role?->code) {
            'coordinator' => $query->where('campus_id', $viewer->campus_id),

            'instructor' => $query->where(function (Builder $q) use ($viewer) {
                $q->where('user_id', $viewer->id);

                if ($viewer->instructor) {
                    $q->orWhereIn('user_id', $viewer->instructor->advisees()->select('user_id'));
                }
            }),

            default => $query->whereRaw('1 = 0'),
        };
    }
}
