@extends('layouts.instructor')

@section('title', 'Announcements - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Announcements</h1>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger small">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="il-card">
            <h2 class="h6 fw-bold mb-1">New announcement</h2>
            <p class="small text-muted mb-3">
                Sent to your {{ $adviseeCount }} advisee(s). They will see it under Notifications.
            </p>

            <form method="POST" action="{{ route('instructor.announcements.store') }}">
                @csrf

                <label class="form-label small fw-semibold" for="category">Type</label>
                <select id="category" name="category" class="form-select mb-3">
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" {{ old('category', 'pre_ojt_seminar') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                <label class="form-label small fw-semibold" for="title">Title</label>
                <input type="text" id="title" name="title" class="form-control mb-3" maxlength="150"
                       value="{{ old('title') }}" placeholder="e.g. Pre-OJT Seminar" required>

                <div class="row g-2 mb-3">
                    <div class="col-7">
                        <label class="form-label small fw-semibold" for="event_date">Date</label>
                        <input type="date" id="event_date" name="event_date" class="form-control" value="{{ old('event_date') }}">
                    </div>
                    <div class="col-5">
                        <label class="form-label small fw-semibold" for="event_time">Time</label>
                        <input type="time" id="event_time" name="event_time" class="form-control" value="{{ old('event_time') }}">
                    </div>
                </div>

                <label class="form-label small fw-semibold" for="venue">Venue</label>
                <input type="text" id="venue" name="venue" class="form-control mb-3" maxlength="255"
                       value="{{ old('venue') }}" placeholder="e.g. Room 301 / Gymnasium / Online (link)">

                <label class="form-label small fw-semibold" for="message">Message <span class="text-muted fw-normal">(optional)</span></label>
                <textarea id="message" name="message" rows="4" class="form-control mb-3" maxlength="2000"
                          placeholder="Reminders, what to bring, attire, etc.">{{ old('message') }}</textarea>

                <button type="submit" class="btn btn-primary">Send to students</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="il-card">
            <h2 class="h6 fw-bold mb-3">Sent announcements</h2>

            @forelse ($announcements as $announcement)
                <div class="py-3 border-bottom">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="fw-semibold">{{ $announcement->title }}</div>
                        <span class="badge text-bg-secondary align-self-start">{{ $announcement->categoryLabel() }}</span>
                    </div>
                    @if ($announcement->event_at)
                        <div class="small mt-1"><strong>When:</strong> {{ $announcement->event_at->format('l, F j, Y') }}@if ($announcement->event_at->format('H:i') !== '00:00') at {{ $announcement->event_at->format('g:i A') }}@endif</div>
                    @endif
                    @if ($announcement->venue)
                        <div class="small"><strong>Where:</strong> {{ $announcement->venue }}</div>
                    @endif
                    @if ($announcement->message)
                        <div class="small text-secondary mt-1" style="white-space: pre-line;">{{ $announcement->message }}</div>
                    @endif
                    <div class="text-secondary mt-1" style="font-size:.72rem;">
                        Sent {{ $announcement->created_at->format('M d, Y g:i A') }} &middot; {{ $announcement->recipients_count }} student(s)
                    </div>
                </div>
            @empty
                <p class="text-secondary small mb-0">No announcements sent yet.</p>
            @endforelse

            <div class="mt-3">{{ $announcements->links() }}</div>
        </div>
    </div>
</div>
@endsection
