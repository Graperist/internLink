<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $announcements = Announcement::where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        $advisee_count = $user->instructor->advisees()->count();

        return view('instructor.announcements.index', [
            'announcements' => $announcements,
            'categories' => Announcement::CATEGORIES,
            'adviseeCount' => $advisee_count,
        ]);
    }

    /**
     * Post an announcement and notify every advisee (same campus and
     * program as this instructor) in their Notifications page.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $instructor = $user->instructor;

        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(Announcement::CATEGORIES))],
            'title' => ['required', 'string', 'max:150'],
            'event_date' => ['nullable', 'date', 'required_if:category,pre_ojt_seminar,post_ojt_seminar'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'event_date.required_if' => 'Please set the seminar date.',
        ]);

        $eventAt = null;
        if (! empty($data['event_date'])) {
            $eventAt = Carbon::parse($data['event_date'].' '.($data['event_time'] ?? '00:00'));
        }

        // Build the text students will read.
        $lines = [];
        if ($eventAt) {
            $when = $eventAt->format('l, F j, Y');
            if (! empty($data['event_time'])) {
                $when .= ' at '.$eventAt->format('g:i A');
            }
            $lines[] = 'When: '.$when;
        }
        if (! empty($data['venue'])) {
            $lines[] = 'Where: '.$data['venue'];
        }
        if (! empty($data['message'])) {
            $lines[] = $data['message'];
        }
        $body = implode("\n", $lines) ?: $data['title'];

        $userIds = $instructor->advisees()->pluck('user_id')->filter()->unique();

        $now = now();
        Notification::insert($userIds->map(fn ($id) => [
            'user_id' => $id,
            'type' => 'announcement',
            'url' => route('student.notifications', [], false),
            'expires_at' => $eventAt ? $eventAt->copy()->endOfDay() : null,
            'title' => $data['title'],
            'message' => $body,
            'resolve_key' => null,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        Announcement::create([
            'campus_id' => $instructor->campus_id,
            'program_id' => $instructor->program_id,
            'user_id' => $user->id,
            'category' => $data['category'],
            'title' => $data['title'],
            'event_at' => $eventAt,
            'venue' => $data['venue'] ?? null,
            'message' => $data['message'] ?? null,
            'recipients_count' => $userIds->count(),
        ]);

        return redirect()
            ->route('instructor.announcements')
            ->with('status', 'Announcement sent to '.$userIds->count().' student(s).');
    }
}
