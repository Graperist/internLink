<?php

namespace App\Http\Controllers\Hte;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * My Notifications. Reads the same notifications table used by the
     * other portals. HTE representatives are notified when: a DTR entry
     * becomes ready for their verification (student clocks out - see
     * Student\DtrController::clockOut), and when a student resolves a
     * flagged DTR entry (Student\DtrController::resolveFlag).
     *
     * Opening this page is what clears the topbar bell's unread count -
     * the "New" pill on each item still reflects whatever was unread
     * when the page was requested, but every item is marked read right
     * after, so the badge is gone the next time the layout renders.
     */
    public function index(): View
    {
        $notifications = auth()->user()->notifications_()->latest()->paginate(20);

        // Keep unread state until the user opens a specific notification.
        return view('hte.notifications', compact('notifications'));
    }
}
