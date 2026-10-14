@extends('layouts.instructor')

@section('title', 'My Students - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">My students</h1>
</div>

@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif
@if ($errors->has('completion'))
    <div class="alert alert-danger" role="alert">{{ $errors->first('completion') }}</div>
@endif

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">My {{ auth()->user()->instructor->program->code ?? '' }} Student Interns</h2>
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('instructor.advisees.export', request()->query()) }}" class="small fw-semibold text-decoration-none">
                Export via Excel
            </a>
            <span class="text-secondary small">{{ $advisees->total() }} total</span>
        </div>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-8">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by name or ID number..."
                value="{{ request('search') }}"
            >
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                <option value="active" @selected(request('status') === 'active')>Deployed</option>
                <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                <option value="terminated" @selected(request('status') === 'terminated')>Terminated</option>
                <option value="not_deployed" @selected(request('status') === 'not_deployed')>Not Deployed</option>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-search"></i> Search
            </button>
            @if (request()->hasAny(['search', 'status']))
                <a href="{{ route('instructor.advisees') }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Student</th>
                <th>Required hours</th>
                <th>HTE assignment</th>
                <th>Doc status</th>
                <th>Deployment status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @php
                $deploymentColors = [
                    'pending' => 'text-bg-warning',
                    'active' => 'text-bg-primary',
                    'completed' => 'text-bg-secondary',
                    'terminated' => 'text-bg-danger',
                ];
                $deploymentLabels = [
                    'pending' => 'Pending Approval',
                    'active' => 'Deployed',
                    'completed' => 'Completed',
                    'terminated' => 'Terminated',
                ];
            @endphp
            @forelse ($advisees as $student)
                @php
                    $internship = $student->currentInternship;
                    $deployment = $internship?->deployment;
                    $requiredHours = $student->program?->required_hours;
                    $approved = $student->approved_documents_count;
                    $missing = max($requiredDocsCount - $approved, 0);
                    $periodDtrs = $deployment && $deployment->start_date && $deployment->end_date
                        ? $deployment->dtrs->filter(function ($dtr) use ($deployment) {
                            $date = $dtr->log_date?->toDateString();
                            return $date && $date >= $deployment->start_date->toDateString()
                                && $date <= $deployment->end_date->toDateString()
                                && $date <= today()->toDateString();
                        })
                        : collect();
                    $verifiedHours = $periodDtrs->filter(fn ($dtr) =>
                        $dtr->status === 'approved'
                        && $dtr->time_in !== null
                        && $dtr->time_out !== null
                        && $dtr->clock_in_verified_at !== null
                        && $dtr->clock_out_verified_at !== null
                    )->sum('hours_logged');
                    $hasUnverifiedDtrs = $periodDtrs->contains(fn ($dtr) =>
                        $dtr->status !== 'approved'
                        || $dtr->time_in === null
                        || $dtr->time_out === null
                        || $dtr->clock_in_verified_at === null
                        || $dtr->clock_out_verified_at === null
                    );
                    $completionReady = $deployment
                        && $internship
                        && $deployment->status === 'active'
                        && $internship->status === 'deployed'
                        && $deployment->start_date
                        && $deployment->end_date
                        && ! $deployment->end_date->isFuture()
                        && $requiredHours > 0
                        && $verifiedHours >= $requiredHours
                        && ! $hasUnverifiedDtrs;
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $student->user->name }}</div>
                        <div class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</div>
                    </td>
                    <td>{{ $requiredHours }} hrs</td>
                    <td>{{ $deployment?->hte?->name ?? '—' }}</td>
                    <td>
                        @if ($missing === 0)
                            <span class="badge rounded-pill text-bg-primary">All Complete</span>
                        @else
                            <span class="badge rounded-pill text-bg-danger">{{ $missing }} Missing</span>
                        @endif
                    </td>
                    <td>
                        @if ($deployment)
                            <span class="badge rounded-pill {{ $deploymentColors[$deployment->status] ?? 'text-bg-light' }}">
                                {{ $deploymentLabels[$deployment->status] ?? ucfirst($deployment->status) }}
                            </span>
                        @else
                            <span class="badge rounded-pill text-bg-light text-secondary">Not Deployed</span>
                        @endif
                    </td>
                    <td>
                        @if ($missing > 0)
                            <a href="{{ route('instructor.documents', ['student' => $student->id]) }}" class="small fw-semibold text-decoration-none">
                                Review Docs
                            </a>
                        @else
                            <a href="{{ route('instructor.documents', ['student' => $student->id]) }}" class="small fw-semibold text-decoration-none">
                                View
                            </a>
                        @endif
                        @if ($deployment && $deployment->status === 'active')
                            <div class="mt-2 small text-secondary">Verified: {{ number_format((float) $verifiedHours, 2) }} / {{ number_format((float) $requiredHours, 2) }} hrs</div>
                            @if ($completionReady)
                                <form method="POST" action="{{ route('instructor.advisees.confirm-completion', $deployment) }}" class="mt-2" onsubmit="return confirm('Confirm that this student has reached the internship end date and completed all required verified hours? This will unlock post-OJT requirements.');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Confirm completion</button>
                                </form>
                            @else
                                <div class="small text-secondary mt-1">Completion is locked until the end date is reached, required hours are verified, and all DTR entries in the period are resolved.</div>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-secondary py-4">
                        @if (request()->hasAny(['search', 'status']))
                            No students match this search or filter.
                        @else
                            No students are in your program yet.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-3">
    {{ $advisees->links() }}
</div>
@endsection
