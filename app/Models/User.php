<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'campus_id', 'role_id', 'id_number', 'name', 'email', 'avatar_path', 'password', 'status', 'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function instructor()
    {
        return $this->hasOne(Instructor::class);
    }

    public function hteRepresentative()
    {
        return $this->hasOne(HteRepresentative::class);
    }

    public function notifications_()
    {
        return $this->hasMany(Notification::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function passwordResetCode()
    {
        return $this->hasOne(PasswordResetCode::class);
    }

    /**
     * Public URL for the profile picture, or null if the user hasn't
     * uploaded one - views fall back to the initials avatar in that case.
     */
    /**
     * Two-letter initials for the avatar circle: first and last name
     * ("Mark Harley V. Bristol" -> "MB"). Words without letters (like "-")
     * and middle initials are skipped.
     */
    public static function initialsOf(?string $name): string
    {
        $words = collect(preg_split('/\s+/', trim((string) $name)))
            ->map(fn ($w) => preg_replace('/[^\pL]/u', '', $w))
            ->filter(fn ($w) => mb_strlen($w) > 1 || $w === '')->filter()->values();
        if ($words->isEmpty()) {
            return '?';
        }
        $first = mb_substr($words->first(), 0, 1);
        $last = $words->count() > 1 ? mb_substr($words->last(), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    public function getInitialsAttribute(): string
    {
        return self::initialsOf($this->name);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    // --- Role helpers (based on roles.code) ---

    public function hasRole(string $code): bool
    {
        return $this->role && $this->role->code === $code;
    }

    /** University-wide: adds campuses and their coordinators, manages the login photos. */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isCoordinator(): bool
    {
        return $this->hasRole('coordinator');
    }

    /**
     * The named route for this user's own portal dashboard, based on their
     * role. Single source of truth - both LoginController (post-login
     * redirect) and PasswordChangeController (post-password-change
     * redirect) use this instead of each keeping their own copy, which is
     * exactly how those two drifted out of sync with each other before.
     */
    public function dashboardRouteName(): string
    {
        return match ($this->role?->code) {
            // The coordinator is also a program instructor and uses the
            // same shared portal (with campus-wide view + admin pages).
            'coordinator', 'instructor' => 'instructor.dashboard',
            'hte_representative' => 'hte.evaluations',   // HTE accounts are for evaluations only
            'super_admin' => 'admin.dashboard',
            'student' => 'student.dashboard',
            default => 'login',
        };
    }

    public function isInstructor(): bool
    {
        return $this->hasRole('instructor');
    }

    public function isHteRepresentative(): bool
    {
        return $this->hasRole('hte_representative');
    }

    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }
}
