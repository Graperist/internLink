<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = auth()->user()->notifications_()->latest()->paginate(20);

        return view('coordinator.notifications.index', compact('notifications'));
    }
}
