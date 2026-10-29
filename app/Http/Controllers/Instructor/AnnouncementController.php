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
            ->with('program')
            ->latest()
            ->paginate(10);

        // Announcements go to the interns of the sender's own program - the
        // coordinator's too (they are also a program instructor).
        $instructor = $user->instructor;   // (the Super Admin's comes from CampusManager)
        $instructor?->loadMissing('program');

        // The OJT Coordinator can send to one program or to every program.
        $programs = $instructor->isCoordinator()
            ? \App\Models\Program::where('campus_id', $instructor->campus_id)->orderBy('code')
                ->withCount(['students' => fn ($q) => $q->where('campus_id', $instructor->campus_id)])->get()
            : collect();

        return view('instructor.announcements.index', [
            'announcements' => $announcements,
            'categories' => Announcement::CATEGORIES,
            'adviseeCount' => $instructor->advisees()->count(),
            'program' => $instructor->program,
            'isCoordinator' => $instructor->isCoordinator(),
            'programs' => $programs,
            'campusCount' => \App\Models\Student::where('campus_id', $instructor->campus_id)->count(),
        ]);
    }

    /**
     * Post an announcement and notify the interns of this instructor's
     * program (same campus and program) in their Notifications page.
     * Interns of other programs do not receive it.
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
            // Coordinator only: "all" or a program of the campus.
            'audience' => ['nullable', 'max:20'],
        ], [
            'event_date.required_if' => 'Please set the seminar date.',
        ]);

        // Who receives it: an instructor's own program; the coordinator picks
        // one program or every program of the campus.
        $audienceProgram = $instructor->program;
        $toAllPrograms = false;
        if ($instructor->isCoordinator()) {
            $audience = (string) ($data['audience'] ?? '');
            if ($audience === 'all') {
                $toAllPrograms = true;
                $audienceProgram = null;
            } elseif ($audience !== '') {
                $audienceProgram = \App\Models\Program::where('campus_id', $instructor->campus_id)->find($audience);
                if (! $audienceProgram) {
                    return back()->withInput()->withErrors(['audience' => 'Choose who receives this announcement.']);
                }
            }
        }

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

        $recipients = \App\Models\Student::where('campus_id', $instructor->campus_id);
        if (! $toAllPrograms) {
            $recipients->where('program_id', $audienceProgram?->id ?? 0);
        }
        $userIds = $recipients->pluck('user_id')->filter()->unique();

        $now = now();
        Notification::insert($userIds->map(fn ($id) => [
            'user_id' => $id,
            'type' => 'announcement',
            'url' => route('student.announcements', [], false),
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
            'program_id' => $toAllPrograms ? null : $audienceProgram?->id,
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
            ->with('status', 'Announcement sent to '.$userIds->count().' '
                .($toAllPrograms ? '' : ($audienceProgram?->code ?? 'program').' ')
                .\Illuminate\Support\Str::plural('intern', $userIds->count())
                .($toAllPrograms ? ' of every program' : '').'.');
    }
}
