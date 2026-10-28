<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Single entry point for writing to the audit trail.
 *
 * Logging must never break the feature being logged, so every failure is
 * swallowed (and reported to the normal Laravel log).
 *
 * Request bodies are deliberately never recorded - only what happened, who
 * did it and where from. That keeps passwords and form contents out of the log.
 */
class AuditLogger
{
    public static function record(
        string $action,
        string $description,
        ?User $user = null,
        ?Request $request = null,
        ?Model $subject = null,
        ?int $campusId = null,
    ): ?ActivityLog {
        try {
            $request ??= request();

            // A Super Admin working in a campus as its coordinator: say so.
            $manager = CampusManager::superAdmin();
            if ($manager && $user && $user->id !== $manager->id) {
                $description .= " (by Super Admin {$manager->name})";
            }

            return ActivityLog::create([
                'user_id' => $user?->id,
                'campus_id' => $user?->campus_id ?? $campusId,
                'role_code' => $user?->role?->code,
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'description' => self::clean($description, 500),
                'route_name' => $request?->route()?->getName(),
                'method' => $request?->method(),
                // Path only - query strings can carry tokens/filters we don't want stored.
                'path' => self::clean('/'.ltrim((string) $request?->path(), '/'), 500),
                'ip_address' => $request?->ip(),
                'user_agent' => self::clean((string) $request?->userAgent(), 255),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Strip control characters (log-forging via newlines etc.) and cap length. */
    public static function clean(?string $value, int $max): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value) ?? '';

        return Str::limit(trim($value), $max, '…');
    }
}
