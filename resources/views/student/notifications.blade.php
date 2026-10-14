@extends('layouts.student')

@section('title', 'Notifications - PSU Intern-Link')

@section('content')
<style>.notification-link:hover { background: #f6f8fb; }</style>
<div class="il-page-header">
    <h1 class="il-page-title">My notifications</h1>
</div>

<div class="il-card">
    @forelse ($notifications as $notification)
        <a href="{{ route('notifications.open', $notification) }}" class="py-3 border-bottom d-flex justify-content-between align-items-start gap-3 text-decoration-none text-reset notification-link">
            <div>
                <div class="fw-semibold">
                    {{ $notification->title }}
                    @if($notification->type === 'announcement')
                        <span class="badge text-bg-info ms-1" style="font-size:.62rem;">Announcement</span>
                    @endif
                    @if($notification->type === 'weekly_report_reminder')
                        <span class="badge text-bg-warning ms-1" style="font-size:.62rem;">Reminder</span>
                    @endif
                </div>
                <div class="text-secondary small mt-1" style="white-space: pre-line;">{{ $notification->message }}</div>
                <div class="text-secondary" style="font-size:.72rem;">{{ $notification->created_at->format('M d, Y g:i A') }}</div>
                <div class="small text-primary mt-2">Open <i class="ti ti-arrow-right"></i></div>
            </div>
            @unless ($notification->read_at)
                <span class="badge rounded-pill text-bg-primary" style="font-size: 0.65rem;">New</span>
            @endunless
        </a>
    @empty
        <p class="text-secondary small mb-0">
            No notifications yet. You'll see updates here when instructors review your documents
            or your HTE Supervisor reviews your DTR entries.
        </p>
    @endforelse
</div>

<div class="mt-3">
    {{ $notifications->links() }}
</div>
@endsection
