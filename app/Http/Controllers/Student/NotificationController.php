<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Figure 25 - My Notifications. Reads the real notifications table,
     * which will be empty for now - nothing writes to it yet, since that
     * requires instructor document-review and HTE DTR-verification actions
     * that don't exist until those two portals are built.
     */
    public function index(): View
    {
        $notifications = auth()->user()->notifications_()->latest()->paginate(20);

        return view('student.notifications', compact('notifications'));
    }
}
