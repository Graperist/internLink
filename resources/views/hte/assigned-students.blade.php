@extends('layouts.hte')

@section('title', 'Interns - PSU Intern-Link')
@section('page_title', 'Interns')

@section('content')
@php
    $evaluatedCount = $deployments->filter(fn ($d) => $d->hte_forms_total > 0 && $d->hte_forms_done >= $d->hte_forms_total)->count();
    $activeCount = $deployments->filter(fn ($d) => $d->status === 'active')->count();
@endphp
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="il-card d-flex align-items-center gap-3 py-3 h-100">
            <span class="il-tile-icon"><i class="ti ti-users"></i></span>
            <div><div class="il-stat-number">{{ $deployments->count() }}</div><div class="text-secondary small">Interns assigned</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="il-card d-flex align-items-center gap-3 py-3 h-100">
            <span class="il-tile-icon"><i class="ti ti-briefcase"></i></span>
            <div><div class="il-stat-number">{{ $activeCount }}</div><div class="text-secondary small">Training now</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="il-card d-flex align-items-center gap-3 py-3 h-100">
            <span class="il-tile-icon"><i class="ti ti-clipboard-check"></i></span>
            <div><div class="il-stat-number">{{ $evaluatedCount }} <span class="fs-6 text-secondary fw-semibold">/ {{ $deployments->count() }}</span></div><div class="text-secondary small">Evaluations submitted</div></div>
        </div>
    </div>
</div>

<div class="il-card">
    <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><span class="il-tile-icon sm"><i class="ti ti-list-details"></i></span> Interns assigned to you</h2>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Deployment Date</th>
                    <th>Hours Rendered</th>
                    <th>Required Hours</th>
                    <th>Evaluation</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deployments as $deployment)
                    @php
                        $student = $deployment->internship->student;
                        $initials = \App\Models\User::initialsOf($student->user->name);
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="il-avatar-sm">{{ $initials }}</span>
                                <div>
                                    <div class="fw-semibold">{{ $student->user->name }}</div>
                                    <div class="text-secondary" style="font-size:.76rem;">{{ $student->user->id_number }}</div>
                                </div>
                            </div>
                        </td>
                        @php
                            $hours = (float) $deployment->rendered_hours;
                            $required = (float) ($deployment->internship->requiredHours() ?? 0);
                            $pct = $required > 0 ? min(100, (int) round($hours / $required * 100)) : 0;
                        @endphp
                        <td><span class="badge rounded-pill text-bg-light">{{ $student->program->code ?? '—' }}</span></td>
                        <td class="small"><i class="ti ti-calendar text-secondary"></i> {{ $deployment->start_date?->format('M d, Y') ?? '—' }}</td>
                        <td style="min-width:140px;">
                            <span class="fw-bold">{{ \App\Support\TimeFrame::format($hours) }}h</span>
                            <div class="progress mt-1" style="height:4px;"><div class="progress-bar {{ $pct >= 100 ? 'bg-success' : '' }}" style="width: {{ $pct }}%"></div></div>
                        </td>
                        <td>{{ number_format($required, 0) }}h</td>
                        <td>
                            @if ($deployment->hte_forms_total > 0 && $deployment->hte_forms_done >= $deployment->hte_forms_total)
                                <span class="badge rounded-pill text-bg-success"><i class="ti ti-circle-check"></i> Submitted</span>
                            @elseif ($deployment->status !== 'completed')
                                <span class="badge rounded-pill text-bg-light" title="Forms 13 and 21 open once the internship is completed"><i class="ti ti-lock"></i> Opens when completed</span>
                            @elseif ($deployment->hte_forms_done > 0)
                                <a href="{{ route('hte.evaluations') }}" class="badge rounded-pill text-bg-warning text-decoration-none"><i class="ti ti-clock"></i> {{ $deployment->hte_forms_done }} of {{ $deployment->hte_forms_total }} submitted</a>
                            @else
                                <a href="{{ route('hte.evaluations') }}" class="badge rounded-pill text-bg-warning text-decoration-none"><i class="ti ti-clock"></i> Not Yet Submitted</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-5"><i class="ti ti-users fs-2 d-block mb-1"></i>No interns are currently assigned to your HTE.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
