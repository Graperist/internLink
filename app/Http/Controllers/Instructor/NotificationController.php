<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Notifications. Currently the only trigger that actually writes here
     * is a student submitting or resubmitting a structured form for
     * review (see Student\DocumentController::storeFields). Other
     * mockup-style triggers - HTE evaluations received, MOA signature
     * status, missing PhilHealth/insurance documents - aren't wired up
     * yet, since they depend on the HTE Portal and on document
     * requirements/fields that don't exist in the system today.
     */
    public function index(): View
    {
        $notifications = auth()->user()->notifications_()->latest()->paginate(20);

        return view('instructor.notifications', compact('notifications'));
    }
}
