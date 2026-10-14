<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;

class NotificationOpenController extends Controller
{
    /** Open only the signed-in user's notice; accept internal paths only. */
    public function __invoke(Notification $notification): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && (int) $notification->user_id === (int) $user->id, 403);

        $target = $notification->targetUrl();
        // URLs are normally generated with route(..., false). If an old or
        // manually edited row contains an external/protocol-relative URL,
        // never turn the notification endpoint into an open redirect.
        if (! is_string($target) || ! str_starts_with($target, '/')
            || str_starts_with($target, '//') || str_contains($target, "\\")
            || preg_match('/[\r\n]/', $target) || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) {
            $target = route($user->dashboardRouteName(), [], false);
        }

        if ($notification->removedWhenOpened()) {
            $notification->delete();
        } else {
            $notification->markAsRead();
        }

        return redirect($target);
    }
}
