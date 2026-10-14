@extends('layouts.instructor')

@section('title', 'Dashboard - PSU Intern-Link')

@section('content')
<style>
    .il-card-link {
        display: block;
        text-decoration: none;
        color: inherit;
        transition: box-shadow .15s ease, transform .15s ease;
    }
    .il-card-link:hover,
    .il-card-link:focus {
        box-shadow: 0 .35rem .9rem rgba(0, 0, 0, .08);
        transform: translateY(-1px);
        color: inherit;
    }
    .il-row-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none;
        color: inherit;
        border-radius: .375rem;
        margin: 0 -.5rem;
        padding: .5rem;
        transition: background-color .15s ease;
    }
    .il-row-link:hover {
        background-color: rgba(0, 0, 0, .035);
        color: inherit;
    }
</style>

<div class="il-page-header">
    <h1 class="il-page-title">Dashboard overview</h1>
    <span class="il-page-date">{{ now()->format('F d, Y') }}</span>
</div>

<div class="il-hero mb-3">
    <p class="il-hero-title">Good morning, {{ auth()->user()->name }}!</p>
    <p class="il-hero-subtitle">
        You have {{ $counts['pending_review'] }} document{{ $counts['pending_review'] === 1 ? '' : 's' }} pending review
        and {{ $counts['awaiting_approval'] }} student{{ $counts['awaiting_approval'] === 1 ? '' : 's' }} awaiting deployment approval.
    </p>
</div>

{{-- Stat cards - each one links to the page that explains the number --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <a href="{{ route('instructor.advisees') }}" class="il-card il-card-link">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="il-stat-number">{{ $counts['advisees'] }}</div>
                <span class="badge rounded-pill text-bg-success">Active</span>
            </div>
            <div class="text-secondary small">My {{ auth()->user()->instructor->program->code ?? '' }} Students</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('instructor.advisees', ['status' => 'active']) }}" class="il-card il-card-link">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="il-stat-number">{{ $counts['deployed'] }}</div>
                <span class="badge rounded-pill text-bg-success">{{ $counts['deployment_rate'] }}%</span>
            </div>
            <div class="text-secondary small">Deployed</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('instructor.documents') }}" class="il-card il-card-link">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="il-stat-number">{{ $counts['pending_review'] }}</div>
                <span class="badge rounded-pill text-bg-warning">Pending</span>
            </div>
            <div class="text-secondary small">Docs For Review</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('instructor.advisees', ['status' => 'pending']) }}" class="il-card il-card-link">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="il-stat-number">{{ $counts['awaiting_approval'] }}</div>
                <span class="badge rounded-pill text-bg-danger">
                    <i class="ti ti-alert-triangle"></i> Action Needed
                </span>
            </div>
            <div class="text-secondary small">Awaiting Approval</div>
        </a>
    </div>
</div>

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Recent Submissions</h2>
        <a href="{{ route('instructor.documents') }}" class="small fw-semibold text-decoration-none">Review All &rarr;</a>
    </div>

    @forelse ($recentSubmissions as $document)
        @php
            $studentName = $document->internship->student->user->name;
            $initials = collect(explode(' ', $studentName))->map(fn ($p) => $p[0] ?? '')->join('');
        @endphp
        <a
            href="{{ route('instructor.documents', ['student' => $document->internship->student_id]) }}"
            class="il-row-link {{ ! $loop->last ? 'border-bottom' : '' }}"
        >
            <div class="d-flex align-items-center gap-3">
                <div class="il-row-avatar">{{ $initials }}</div>
                <div>
                    <div class="fw-semibold small">{{ $studentName }}</div>
                    <div class="text-secondary" style="font-size: 0.75rem;">{{ $document->internship->student->user->id_number }}</div>
                </div>
            </div>

            <div class="text-secondary small d-none d-md-block">{{ $document->requirement->name }}</div>

            <div class="text-secondary small d-none d-md-block" style="font-size: 0.78rem;">
                {{ $document->submitted_at?->format('M d, Y') ?? '—' }}
            </div>

            <span class="small fw-semibold">Review</span>
        </a>
    @empty
        <p class="text-center text-secondary py-4 mb-0">Nothing waiting on you right now.</p>
    @endforelse
</div>
@endsection
