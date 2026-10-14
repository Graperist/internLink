@extends('layouts.student')

@section('title', 'My Dashboard - PSU Intern-Link')

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
    <h1 class="il-page-title">My dashboard</h1>
</div>

<div class="il-hero mb-3">
    <p class="il-hero-title">Good morning, {{ explode(' ', $student->user->name)[0] }}!</p>
    <p class="il-hero-subtitle">
        You have {{ $hoursRendered }} hours rendered. Keep it up — you're {{ $hoursPercent }}% done!
    </p>
</div>



<div class="row g-3 mb-3">
    <div class="col-md-3">
        <a href="{{ route('student.dtr') }}" class="il-card il-card-link text-center">
            <div class="il-stat-number">{{ $hoursRendered }}</div>
            <div class="text-secondary small">Hours Rendered</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('student.progress') }}" class="il-card il-card-link text-center">
            <div class="il-stat-number">{{ $requiredHours }}</div>
            <div class="text-secondary small">Required Hours</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('student.dtr') }}" class="il-card il-card-link text-center">
            <div class="il-stat-number">{{ $hoursRemaining }}</div>
            <div class="text-secondary small">Hours Remaining</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('student.documents') }}" class="il-card il-card-link text-center">
            <div class="il-stat-number">{{ $submittedCount }}/{{ $checklist->count() }}</div>
            <div class="text-secondary small">Documents Submitted</div>
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="il-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0">My Document Checklist</h2>
                <span class="text-secondary small">{{ $submittedCount }} of {{ $checklist->count() }} complete</span>
            </div>

            @forelse ($checklist as $row)
                <a
                    href="{{ route('student.documents') }}"
                    class="il-row-link {{ ! $loop->last ? 'border-bottom' : '' }}"
                >
                    <div class="d-flex align-items-center gap-2">
                        <i class="ti {{ $row['document'] ? 'ti-circle-check-filled text-primary' : 'ti-circle text-secondary' }}"></i>
                        <span>{{ $row['requirement']->name }}</span>
                    </div>
                    <span class="text-secondary small">
                        {{ $row['document'] ? 'Submitted '.$row['document']->submitted_at?->format('M d') : 'Not submitted' }}
                    </span>
                </a>
            @empty
                <p class="text-secondary small mb-0">No document requirements set up for your campus yet.</p>
            @endforelse
        </div>
    </div>

    <div class="col-lg-4">
        <a href="{{ route('student.progress') }}" class="il-card il-card-link d-block">
            <h2 class="h6 fw-bold mb-3">Hours Progress</h2>
            <div class="text-center mb-3">
                <div class="il-stat-number" style="font-size: 2.5rem;">{{ $hoursPercent }}%</div>
                <div class="text-secondary small">{{ $hoursPercent }}% of required {{ $requiredHours }} hours completed</div>
            </div>
            <div class="progress mb-1" style="height: 10px;">
                <div class="progress-bar bg-success" style="width: {{ $hoursPercent }}%"></div>
            </div>
            <div class="d-flex justify-content-between small text-secondary">
                <span>Overall</span>
                <span>{{ $hoursRendered }} / {{ $requiredHours }}h</span>
            </div>
        </a>
    </div>
</div>
@endsection
