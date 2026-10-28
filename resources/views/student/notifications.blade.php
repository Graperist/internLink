@extends('layouts.student')

@section('title', 'Announcements - PSU Intern-Link')

@section('content')
@php
    $roleOf = fn ($author) => $author?->isCoordinator() ? 'OJT Coordinator' : 'Subject Instructor';
    $initials = fn ($name) => collect(explode(' ', (string) $name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join('');
    $categoryStyle = [
        'pre_ojt_seminar' => ['ti-school', '#1c3f66', '#e8eef7'],
        'post_ojt_seminar' => ['ti-certificate', '#7a4b00', '#fff3d6'],
        'general' => ['ti-speakerphone', '#335', '#eef0f4'],
    ];
@endphp
<style>
    .ann-tabs .nav-link { color: #5d6b80; font-weight: 600; font-size: .88rem; }
    .ann-tabs .nav-link.active { color: #1c3f66; border-bottom: 2px solid #1c3f66; background: none; }
    .ann-card { border: 1px solid #e6eaf0; border-radius: 12px; padding: 1rem 1.1rem; background: #fff; }
    .ann-card + .ann-card { margin-top: .8rem; }
    .ann-avatar { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: .8rem; color: #fff; background: #1c3f66; flex: none; }
    .ann-avatar.coord { background: #b7791f; }
    .ann-chip { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
    .ann-event { display: flex; flex-wrap: wrap; gap: .4rem 1.2rem; padding: .6rem .8rem; border-radius: 10px; background: #f6f8fb; font-size: .85rem; }
    .ann-event i { color: #1c3f66; }
    .notification-link:hover { background: #f6f8fb; }
</style>

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Announcements</h1>
        <p class="text-secondary small mb-0">From your subject instructor and the OJT Coordinator.</p>
    </div>
</div>

<ul class="nav ann-tabs border-bottom mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'announcements' ? 'active' : '' }}" href="{{ route('student.announcements') }}">
            <i class="ti ti-speakerphone"></i> Announcements
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'updates' ? 'active' : '' }}" href="{{ route('student.announcements', ['tab' => 'updates']) }}">
            <i class="ti ti-bell"></i> Other updates
            @if ($unreadUpdates)<span class="badge rounded-pill text-bg-primary ms-1" style="font-size:.62rem;" id="unreadUpdatesCount">{{ $unreadUpdates }}</span>@endif
        </a>
    </li>
</ul>

@if ($tab === 'announcements')
    @forelse ($announcements as $announcement)
        @php
            [$icon, $color, $bg] = $categoryStyle[$announcement->category] ?? $categoryStyle['general'];
            $author = $announcement->author;
            $isCoord = $author?->isCoordinator();
            $upcoming = $announcement->event_at && $announcement->event_at->endOfDay()->isFuture();
        @endphp
        <article class="ann-card">
            <div class="d-flex align-items-start gap-3">
                <div class="ann-avatar {{ $isCoord ? 'coord' : '' }}">{{ $initials($author?->name) }}</div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <span class="fw-semibold">{{ $author?->name ?? 'PSU Intern-Link' }}</span>
                        <span class="text-secondary small">{{ $roleOf($author) }}</span>
                        <span class="text-secondary small">&middot; {{ $announcement->created_at->format('M d, Y g:i A') }}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="ann-chip" style="color: {{ $color }}; background: {{ $bg }};"><i class="ti {{ $icon }}"></i> {{ $announcement->categoryLabel() }}</span>
                        <span class="ann-chip" style="color:#41506a;background:#eef1f6;">{{ $announcement->program ? 'For '.$announcement->program->code : 'For all programs' }}</span>
                        @if ($upcoming)
                            <span class="ann-chip" style="color:#1d6b3a;background:#e7f6ec;">Upcoming</span>
                        @elseif ($announcement->event_at)
                            <span class="ann-chip" style="color:#6b7280;background:#f1f2f4;">Done</span>
                        @endif
                    </div>
                    <h2 class="h6 fw-bold mb-2">{{ $announcement->title }}</h2>

                    @if ($announcement->event_at || $announcement->venue)
                        <div class="ann-event mb-2">
                            @if ($announcement->event_at)
                                <span><i class="ti ti-calendar"></i> {{ $announcement->event_at->format('l, F j, Y') }}</span>
                                @if ($announcement->event_at->format('H:i') !== '00:00')
                                    <span><i class="ti ti-clock"></i> {{ $announcement->event_at->format('g:i A') }}</span>
                                @endif
                            @endif
                            @if ($announcement->venue)
                                <span><i class="ti ti-map-pin"></i> {{ $announcement->venue }}</span>
                            @endif
                        </div>
                    @endif

                    @if ($announcement->message)
                        <p class="mb-0 small" style="white-space: pre-line;">{{ $announcement->message }}</p>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="il-card text-center py-5">
            <i class="ti ti-speakerphone text-secondary" style="font-size: 2rem;"></i>
            <p class="text-secondary small mb-0 mt-2">No announcements yet. Seminar schedules and reminders from your instructor and the OJT Coordinator appear here.</p>
        </div>
    @endforelse

    <div class="mt-3">{{ $announcements->links() }}</div>
@else
    @if (session('status'))<div class="alert alert-success small">{{ session('status') }}</div>@endif
    @if ($unreadUpdates)
        <form method="POST" action="{{ route('student.announcements.read-all') }}" class="d-flex justify-content-end mb-2">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="ti ti-checks"></i> Mark all as read</button>
        </form>
    @endif
    <div class="il-card">
        @forelse ($updates as $notification)
            <a href="{{ route('notifications.open', $notification) }}" class="py-3 border-bottom d-flex justify-content-between align-items-start gap-3 text-decoration-none text-reset notification-link {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                <span class="upd-dot" aria-hidden="true"></span>
                <div>
                    <div class="fw-semibold">
                        {{ $notification->title }}
                        @if ($notification->type === 'weekly_report_reminder')
                            <span class="badge text-bg-warning ms-1" style="font-size:.62rem;">Reminder</span>
                        @endif
                    </div>
                    <div class="text-secondary small mt-1" style="white-space: pre-line;">{{ $notification->message }}</div>
                    <div class="text-secondary" style="font-size:.72rem;">{{ $notification->created_at->format('M d, Y g:i A') }}</div>
                    <div class="small text-primary mt-2">Open <i class="ti ti-arrow-right"></i></div>
                </div>
                @unless ($notification->read_at)
                    <span class="badge rounded-pill text-bg-primary upd-new" style="font-size: 0.65rem;">New</span>
                @endunless
            </a>
        @empty
            <p class="text-secondary small mb-0">No updates yet. You'll see them here when your instructor reviews your documents.</p>
        @endforelse
    </div>

    <div class="mt-3">{{ $updates->links() }}</div>
    <style>
        .notification-link { padding-left: .25rem; padding-right: .25rem; border-radius: 10px; }
        .notification-link:hover { background: #f8f9fb; }
        .notification-link > div { flex: 1; min-width: 0; }
        .upd-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: .45rem; flex: none; background: transparent; }
        .notification-link.is-unread .upd-dot { background: #1546b8; }
        .notification-link.is-unread .fw-semibold { color: #14213a; }
        .notification-link.is-read { opacity: .78; }
    </style>
    <script>
    (function () {
        // Clicking an update marks it read: the "New" goes away right away
        // (the link itself records it), and the count in the tab goes down.
        var count = document.getElementById('unreadUpdatesCount');
        document.querySelectorAll('.notification-link.is-unread').forEach(function (link) {
            link.addEventListener('click', function () {
                var badge = link.querySelector('.upd-new');
                if (badge) badge.remove();
                link.classList.replace('is-unread', 'is-read');
                if (count) {
                    var n = parseInt(count.textContent, 10) - 1;
                    if (n > 0) { count.textContent = n; } else { count.remove(); count = null; }
                }
            });
        });
        // Coming back with the browser's Back button: show the fresh list, not a saved copy.
        window.addEventListener('pageshow', function (e) { if (e.persisted) location.reload(); });
    })();
    </script>
@endif
@endsection
