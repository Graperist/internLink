@extends('layouts.instructor')

@section('title', 'Monitoring Dashboard - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Monitoring Dashboard</h1>
</div>

<div class="il-card mb-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Real-Time DTR Monitoring</h2>
        <span class="text-secondary small">Today, {{ now()->format('F d, Y') }}</span>
    </div>

    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Student</th>
                <th>Time in</th>
                <th>Time out</th>
                <th>Hours</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $dtr = $row['dtr'];
                    $statusLabel = match (true) {
                        $dtr && $dtr->time_in && $dtr->time_out => 'Logged',
                        $dtr && $dtr->time_in && ! $dtr->time_out => 'Active',
                        default => 'No DTR',
                    };
                    $statusColor = match ($statusLabel) {
                        'Logged' => 'text-bg-primary',
                        'Active' => 'text-bg-warning',
                        default => 'text-bg-danger',
                    };
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $row['student']->user->name }}</div>
                        <div class="text-secondary" style="font-size: 0.78rem;">{{ $row['student']->program->code }}</div>
                    </td>
                    <td>{{ $dtr?->time_in ? \Carbon\Carbon::parse($dtr->time_in)->format('g:i A') : '—' }}</td>
                    <td>{{ $dtr?->time_out ? \Carbon\Carbon::parse($dtr->time_out)->format('g:i A') : '—' }}</td>
                    <td>
                        @if ($dtr && $dtr->time_in && ! $dtr->time_out)
                            <span class="text-warning fw-semibold">In progress</span>
                        @elseif ($dtr?->hours_logged)
                            <span class="text-primary fw-semibold">{{ $dtr->hours_logged }}h</span>
                        @else
                            <span class="text-danger">Absent</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge rounded-pill {{ $statusColor }}">{{ $statusLabel }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-secondary py-4">
                        No actively deployed interns yet.
                        <a href="{{ route('instructor.advisees', ['status' => 'pending']) }}">Start a deployment</a> first.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="il-card">
    <h2 class="h6 fw-bold mb-3">Weekly Hours Summary</h2>

    @forelse ($rows as $row)
        @php
            $student = $row['student'];
            $required = $student->program->required_hours;
            $percent = $required > 0 ? min(100, round(($student->hours_rendered / $required) * 100)) : 0;
            $barColor = $percent >= 50 ? 'bg-success' : 'bg-warning';
        @endphp
        <div class="mb-3">
            <div class="d-flex justify-content-between small mb-1">
                <span class="fw-semibold">{{ $student->user->name }}</span>
                <span class="text-secondary">{{ $student->hours_rendered }} / {{ $required }}h</span>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar {{ $barColor }}" style="width: {{ $percent }}%"></div>
            </div>
        </div>
    @empty
        <p class="small text-secondary mb-0">No actively deployed interns yet.</p>
    @endforelse
</div>
@endsection
