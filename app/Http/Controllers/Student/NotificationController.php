<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's Announcements page: what their subject instructor and the
 * OJT Coordinator announced to their program (or to every program), newest
 * first. The student's other updates (forms returned, reminders, ...) are
 * on the second tab.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $student = $user->student;
        $tab = $request->query('tab') === 'updates' ? 'updates' : 'announcements';

        $announcements = Announcement::query()
            ->with('author.instructor', 'program')
            ->where('campus_id', $student?->campus_id ?? 0)
            ->where(fn ($q) => $q->whereNull('program_id')->orWhere('program_id', $student?->program_id ?? 0))
            ->latest()
            ->paginate(15, ['*'], 'page')
            ->withQueryString();

        $updates = $user->notifications_()->where('type', '!=', 'announcement')->latest()->paginate(20, ['*'], 'updates_page')->withQueryString();

        // Opening the page counts as reading the announcements.
        $unreadUpdates = $user->notifications_()->where('type', '!=', 'announcement')->whereNull('read_at')->count();
        $user->notifications_()->where('type', 'announcement')->whereNull('read_at')->update(['read_at' => now()]);

        return view('student.notifications', compact('announcements', 'updates', 'tab', 'unreadUpdates'));
    }

    /** Other updates: mark every one as read ("New" goes away). */
    public function readAll(): \Illuminate\Http\RedirectResponse
    {
        auth()->user()->notifications_()->where('type', '!=', 'announcement')->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('student.announcements', ['tab' => 'updates'])->with('status', 'All updates marked as read.');
    }
}
