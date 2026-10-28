@extends('layouts.student')

@section('title', 'My Progress - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">My progress</h1>
</div>

<div class="il-card mb-3">
    <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><span class="il-tile-icon sm"><i class="ti ti-chart-bar"></i></span> My Internship Progress</h2>

    <div class="mb-3">
        <div class="d-flex justify-content-between small mb-1">
            <span class="fw-semibold">Hours Completed</span>
            <span class="text-secondary">@if ($requiredHours !== null){{ $student->hours_rendered }} / {{ $requiredHours }}h @else {{ $student->hours_rendered }} hours rendered · required hours not configured @endif</span>
        </div>
        <div class="progress" style="height: 8px;">
            <div class="progress-bar bg-success" style="width: {{ $hoursPercent }}%"></div>
        </div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between small mb-1">
            <span class="fw-semibold">Document Submissions</span>
            <span class="text-secondary">{{ $submittedCount }} / {{ $requirementsCount }}</span>
        </div>
        <div class="progress" style="height: 8px;">
            <div class="progress-bar bg-warning" style="width: {{ $docsPercent }}%"></div>
        </div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between small mb-1">
            <span class="fw-semibold">DTR Compliance</span>
            <span class="text-secondary">{{ $weeksWithDtr }} / {{ $weeksElapsed }} weeks</span>
        </div>
        <div class="progress" style="height: 8px;">
            <div class="progress-bar bg-success" style="width: {{ $dtrPercent }}%"></div>
        </div>
    </div>

    <div>
        <div class="d-flex justify-content-between small mb-1">
            <span class="fw-semibold">Overall Completion</span>
            <span class="text-secondary">{{ $overallPercent }}%</span>
        </div>
        <div class="progress" style="height: 8px;">
            <div class="progress-bar bg-primary" style="width: {{ $overallPercent }}%"></div>
        </div>
    </div>
</div>

<div class="il-card">
    <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><span class="il-tile-icon sm"><i class="ti ti-timeline"></i></span> Internship Timeline</h2>

    @foreach ($timeline as $step)
        @php
            $dotColor = match ($step['status']) {
                'Completed' => 'background: #16a34a;',
                'In Progress' => 'background: #16a34a;',
                default => 'background: #cbd5e1;',
            };
            $textColor = $step['status'] === 'Upcoming' ? 'text-secondary' : '';
        @endphp
        <div class="d-flex align-items-start gap-2 mb-3">
            <div class="rounded-circle mt-1" style="width: 9px; height: 9px; {{ $dotColor }}"></div>
            <div>
                <div class="fw-semibold {{ $textColor }}">{{ $step['label'] }}</div>
                <div class="text-secondary small">{{ $step['status'] }}</div>
            </div>
        </div>
    @endforeach
</div>
@endsection
