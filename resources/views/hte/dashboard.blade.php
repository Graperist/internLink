@extends('layouts.hte')

@section('title', 'Dashboard - PSU Intern-Link')
@section('page_title', 'Dashboard Overview')

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
        text-decoration: none;
        color: inherit;
        display: table-row;
    }
    .il-row-link:hover {
        background-color: rgba(0, 0, 0, .035);
        color: inherit;
    }
</style>

<div class="il-hero mb-3">
    <p class="il-hero-title">Good morning, {{ auth()->user()->name }}!</p>
    <p class="il-hero-subtitle">
        You have {{ $pendingDtrCount }} DTR {{ $pendingDtrCount === 1 ? 'entry' : 'entries' }} pending verification from your assigned interns.
    </p>
</div>

{{-- Stat cards - each one links to the page that explains the number --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <a href="{{ route('hte.students') }}" class="il-card il-card-link h-100 hte-stat-card">
            <div class="il-stat-number">{{ $assignedDeployments->count() }}</div>
            <div class="text-secondary small">Assigned Interns</div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a href="{{ route('hte.dtr') }}" class="il-card il-card-link h-100 hte-stat-card">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="il-stat-number">{{ $pendingDtrCount }}</div>
                @if($pendingDtrCount > 0)<span class="il-mini-badge warning">Action Needed</span>@endif
            </div>
            <div class="text-secondary small">DTR Pending Verification</div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a href="{{ route('hte.evaluations') }}" class="il-card il-card-link h-100 hte-stat-card">
            <div class="il-stat-number">{{ $evaluationsSubmitted }}</div>
            <div class="text-secondary small">Evaluations Submitted</div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a href="{{ route('hte.evaluations') }}" class="il-card il-card-link h-100 hte-stat-card">
            <div class="il-stat-number">{{ $evaluationsPending }}</div>
            <div class="text-secondary small">Evaluations Pending</div>
        </a>
    </div>
</div>

<div class="il-card p-0 overflow-hidden">
    <div class="hte-section-heading">Today's Attendance</div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle hte-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Location</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($todayAttendance as $dtr)
                    @php
                        $student = $dtr->deployment->internship->student;
                        $initials = collect(explode(' ', $student->user->name))->map(fn($p) => $p[0] ?? '')->join('');
                    @endphp
                    <tr class="il-row-link" onclick="window.location='{{ route('hte.dtr') }}'" style="cursor: pointer;">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="il-row-avatar">{{ substr($initials, 0, 2) }}</div>
                                <div>
                                    <div class="fw-semibold">{{ $student->user->name }}</div>
                                    <div class="text-secondary" style="font-size:.76rem;">{{ $student->user->id_number }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $student->program->code ?? '—' }}</td>
                        <td>{{ $dtr->time_in ? \Carbon\Carbon::parse($dtr->time_in)->format('g:i A') : '—' }}</td>
                        <td>{{ $dtr->time_out ? \Carbon\Carbon::parse($dtr->time_out)->format('g:i A') : '—' }}</td>
                        <td>{{ $dtr->deployment->hte->name ?? '—' }}</td>
                        <td>
                            @if($dtr->status === 'approved')
                                <span class="il-status-pill approved">Verified</span>
                            @elseif($dtr->status === 'flagged')
                                <span class="il-status-pill flagged">Flagged</span>
                            @else
                                <span class="il-status-pill active">Active</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-5">No attendance records have been logged today.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
