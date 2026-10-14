@extends('layouts.coordinator')

@section('title', 'Notifications - PSU Intern-Link')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Notifications</h1>
            <p class="text-muted mb-0">Recent updates and alerts from PSU Intern-Link.</p>
        </div>
        <span class="badge rounded-pill text-bg-light border px-3 py-2">
            {{ $notifications->total() }} {{ $notifications->total() === 1 ? 'notification' : 'notifications' }}
        </span>
    </div>

    @if ($notifications->count())
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="list-group list-group-flush">
                @foreach ($notifications as $notification)
                    @php
                        $title = $notification->title ?: 'Notification';
                        $message = $notification->message;
                        $isUnread = empty($notification->read_at);
                    @endphp

                    <a href="{{ route('notifications.open', $notification) }}" class="list-group-item list-group-item-action px-4 py-4 text-decoration-none {{ $isUnread ? 'bg-body-tertiary' : '' }}">
                        <div class="d-flex align-items-start gap-3">
                            <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:44px;height:44px;background:#e8f1ff;color:#1769e0;">
                                <i class="ti ti-bell fs-5"></i>
                            </div>

                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <h2 class="h6 fw-bold mb-0">{{ $title }}</h2>
                                    @if ($isUnread)
                                        <span class="badge rounded-pill text-bg-primary">New</span>
                                    @endif
                                </div>

                                @if ($message)
                                    <p class="mb-2 text-body" style="white-space: pre-line;">{{ $message }}</p>
                                @else
                                    <p class="mb-2 text-muted">Notification</p>
                                @endif

                                <div class="d-flex align-items-center gap-2 small text-muted flex-wrap">
                                    <span>
                                        <i class="ti ti-clock me-1"></i>
                                        {{ optional($notification->created_at)->diffForHumans() ?? 'Recently' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        @if ($notifications->hasPages())
            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @endif
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                     style="width:64px;height:64px;background:#f1f4f8;color:#6c757d;">
                    <i class="ti ti-bell-off fs-3"></i>
                </div>
                <h2 class="h5 fw-bold">No notifications yet</h2>
                <p class="text-muted mb-0">New system updates and alerts will appear here.</p>
            </div>
        </div>
    @endif
</div>
@endsection
