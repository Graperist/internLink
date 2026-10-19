<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'type', 'title', 'message', 'url', 'resolve_key', 'read_at', 'expires_at'];

    protected $casts = ['read_at' => 'datetime', 'expires_at' => 'datetime'];

    /** Types that stay in the list after being opened (they expire on their own). */
    private const KEEP_AFTER_OPEN = ['announcement'];

    protected static function booted(): void
    {
        // Expired notices (e.g. a seminar announcement after the event) are
        // simply never shown or counted.
        static::addGlobalScope('not_expired', function (Builder $query) {
            $query->where(function ($q) {
                $q->whereNull('notifications.expires_at')->orWhere('notifications.expires_at', '>', now());
            });
        });

        static::creating(function (Notification $notification) {
            // Re-sending the same open issue replaces the old notice instead of piling up.
            if ($notification->resolve_key) {
                static::withoutGlobalScopes()
                    ->where('user_id', $notification->user_id)
                    ->where('resolve_key', $notification->resolve_key)
                    ->delete();
            }

            if (blank($notification->url)) {
                $role = User::find($notification->user_id)?->role?->code;
                $notification->url = static::defaultUrl((string) $role, (string) $notification->type);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Remove every notification about an issue that has been resolved.
     *
     * @param  string|array<int,string>  $keys
     */
    public static function resolve(string|array $keys): void
    {
        static::withoutGlobalScopes()->whereIn('resolve_key', (array) $keys)->delete();
    }

    /**
     * Clear every notice about one DTR day: the main one ('dtr:ID') and the
     * supervisor's one-per-save time in/out notices ('dtr:ID:...').
     */
    public static function resolveDtr(int $dtrId): void
    {
        static::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('resolve_key', 'dtr:'.$dtrId)->orWhere('resolve_key', 'like', 'dtr:'.$dtrId.':%'))
            ->delete();
    }

    /**
     * Notices with no open issue behind them (an approval, a "ready for
     * upload" FYI, older notifications) are cleared when the user opens them.
     * Notices tied to an open issue stay until it is actually resolved.
     */
    public function removedWhenOpened(): bool
    {
        return blank($this->resolve_key) && ! in_array($this->type, self::KEEP_AFTER_OPEN, true);
    }

    /** Where clicking this notification should take the user. */
    public function targetUrl(): ?string
    {
        if (filled($this->url)) {
            return $this->url;
        }

        return static::defaultUrl((string) $this->user?->role?->code, (string) $this->type);
    }

    private static function defaultUrl(string $role, string $type): ?string
    {
        $path = fn (string $name) => Route::has($name) ? route($name, [], false) : null;

        return match ($role) {
            'student' => match ($type) {
                'document_status', 'weekly_report_reminder' => $path('student.documents'),
                'dtr' => $path('student.dtr'),
                default => $path('student.notifications'),
            },
            'instructor', 'coordinator' => match ($type) {
                'document_status' => $path('instructor.documents'),
                'pre_deployment_complete', 'deployment' => $path('instructor.advisees'),
                'dtr' => $path('instructor.monitoring'),
                default => $path('instructor.notifications'),
            },
            'hte_representative' => match ($type) {
                'dtr' => $path('hte.dtr'),
                'document_status' => $path('hte.evaluations'),
                default => $path('hte.notifications'),
            },
            default => null,
        };
    }
}
