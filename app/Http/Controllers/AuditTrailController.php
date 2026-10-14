<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class AuditTrailController extends Controller
{
    private const PAGE_SIZE = 40;

    /**
     * JSON feed behind the audit pop-up. Restricted to coordinators and
     * instructors by the route middleware (students and HTE reps get a 403 here - hiding the widget alone
     * would not be enough), and scoped per viewer by ActivityLog::visibleTo().
     *
     *   ?after_id=N   entries newer than N   (live refresh)
     *   ?before_id=N  entries older than N   ("load older")
     */
    public function feed(Request $request): JsonResponse
    {
        $viewer = $request->user();

        $query = ActivityLog::query()
            ->visibleTo($viewer)
            ->with('user:id,name')
            ->orderByDesc('id');

        if ($request->filled('after_id')) {
            $query->where('id', '>', (int) $request->query('after_id'));
        }

        if ($request->filled('before_id')) {
            $query->where('id', '<', (int) $request->query('before_id'));
        }

        $rows = $query->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $rows->count() > self::PAGE_SIZE;
        $rows = $rows->take(self::PAGE_SIZE);

        $roleLabels = config('audit.role_labels', []);

        return response()
            ->json([
                'items' => $rows->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'user' => $log->user?->name ?? 'Unknown user',
                    'self' => $log->user_id === $viewer->id,
                    'role' => $roleLabels[$log->role_code] ?? null,
                    'kind' => $log->kind,
                    'alert' => $log->is_alert,
                    'description' => $log->description,
                    'ip' => $log->ip_address,
                    'at' => $log->created_at->toIso8601String(),
                ])->values(),
                'latest_id' => $rows->max('id'),
                'has_more' => $hasMore,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Beacon endpoint for the click tracker (public/js/audit-tracker.js).
     *
     * The client can only ever add rows for ITSELF (the user comes from the
     * session, never from the request) and only under the "ui.click" action,
     * so a tampered request can't impersonate anyone or fake a server-side
     * event like a login or an approval. Server-recorded rows remain the
     * authoritative trail; clicks are extra context.
     */
    public function click(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'label' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'in:link,button,submit,other'],
            'href' => ['nullable', 'string', 'max:300'],
            'page' => ['nullable', 'string', 'max:300'],
        ]);

        // Manual validator on purpose: a failed Validator in a normal web
        // request would flash errors into the user's session.
        if ($validator->fails()) {
            return response()->noContent(422);
        }

        $data = $validator->validated();
        $label = AuditLogger::clean($data['label'], 120);
        $type = $data['type'] ?? 'other';

        $noun = match ($type) {
            'link' => 'link',
            'button', 'submit' => 'button',
            default => 'item',
        };

        $description = "Clicked the \"{$label}\" {$noun}";

        if ($type === 'link' && ! empty($data['href'])) {
            $description .= ' → '.$this->pathOnly($data['href']);
        }

        if (! empty($data['page'])) {
            $description .= ' (on '.$this->pathOnly($data['page']).')';
        }

        AuditLogger::record('ui.click', $description, $request->user(), $request);

        return response()->noContent();
    }

    /** Drop query string / fragment (they can hold tokens) and cap the length. */
    private function pathOnly(string $url): string
    {
        $parts = parse_url($url);
        $path = ($parts['host'] ?? null) && ! str_starts_with($url, '/')
            ? ($parts['host'].($parts['path'] ?? ''))
            : ($parts['path'] ?? '/');

        return AuditLogger::clean($path, 120);
    }
}
