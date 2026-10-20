@extends('layouts.instructor')

@section('title', 'Settings - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Settings</h1>
        <p class="text-secondary mb-0">For your program{{ $program ? ' ('.$program->code.')' : '' }}.</p>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="il-card" style="max-width: 640px;">
    <h2 class="h6 fw-bold mb-1"><i class="ti ti-calendar-due"></i> Form 19 weekly report deadline</h2>
    <p class="small text-secondary mb-3">
        Each week of the weekly report is due at 11:59 PM this many days after the week ends (0 = on its last day).
        A week first submitted after that is marked <strong>Late</strong> and you are notified; a week past due and not submitted shows as <strong>Missing</strong>.
    </p>

    @if (! $program)
        <div class="alert alert-warning small mb-0">Your account has no program yet - ask your coordinator.</div>
    @else
        <form method="POST" action="{{ route('instructor.settings.update') }}">
            @csrf
            @method('PUT')
            <label class="form-label small fw-semibold" for="weekly_report_due_days">Due</label>
            <div class="input-group mb-1" style="max-width: 360px;">
                <input type="number" min="0" max="14" id="weekly_report_due_days" name="weekly_report_due_days"
                       class="form-control @error('weekly_report_due_days') is-invalid @enderror"
                       value="{{ old('weekly_report_due_days', $program->weekly_report_due_days ?? 2) }}" required>
                <span class="input-group-text">days after the week ends</span>
            </div>
            @error('weekly_report_due_days')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <p class="small text-muted mb-3">Applies to all {{ $program->code }} students{{ $program->weekly_report_due_days === null ? ' (currently the default, 2 days)' : '' }}.</p>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    @endif
</div>
@endsection
