@extends('layouts.instructor')

@section('title', 'Time Frame Monitoring - PSU Intern-Link')

@section('content')
@php $fmt = fn ($h) => \App\Support\TimeFrame::format($h); @endphp

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Time Frame Monitoring</h1>
        <p class="text-secondary small mb-0">Form 17 – Internship Time Frame. Days approved by the HTE supervisor count right away; return a day if it needs correcting.</p>
    </div>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3">
    <div class="{{ $selected ? 'col-lg-4' : 'col-12' }}">
        <div class="il-card p-0 overflow-hidden">
            <div class="px-3 pt-3 pb-2 d-flex justify-content-between align-items-center">
                <span class="small fw-bold text-uppercase text-secondary" style="letter-spacing:.03em;">Deployed interns</span>
                <span class="small text-secondary">{{ $rows->count() }}</span>
            </div>
            <div class="list-group list-group-flush il-form-list">
                @forelse ($rows as $row)
                    @php
                        $percent = $row['required'] > 0 ? min(100, (int) round($row['approved'] / $row['required'] * 100)) : 0;
                        $isOpen = $selected && $selected['student']->id === $row['student']->id;
                    @endphp
                    <a href="{{ route('instructor.monitoring', ['student' => $row['student']->id]) }}" class="list-group-item list-group-item-action {{ $isOpen ? 'active-form' : '' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <div style="min-width:0;">
                                <div class="fw-semibold small">{{ $row['student']->user->name }}</div>
                                <div class="text-secondary" style="font-size:.72rem;">{{ $row['student']->program?->code }} · {{ $row['deployment']?->hte?->name }}</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="small fw-semibold">{{ $fmt($row['approved']) }} / {{ $fmt($row['required']) }} h</div>
                                <div style="font-size:.72rem;">
                                    @if ($row['waiting'])<span class="text-warning">{{ $row['waiting'] }} waiting</span>@endif
                                    @if ($row['declined'])<span class="text-danger ms-1">{{ $row['declined'] }} declined</span>@endif
                                    @if (! $row['waiting'] && ! $row['declined'])<span class="text-secondary">{{ $row['last'] ? 'Last '.\Carbon\Carbon::parse($row['last'])->format('M d') : 'No days yet' }}</span>@endif
                                </div>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height:5px;"><div class="progress-bar {{ $percent >= 100 ? 'bg-success' : '' }}" style="width: {{ $percent }}%"></div></div>
                    </a>
                @empty
                    <div class="list-group-item text-center text-secondary py-4 small">
                        No deployed interns yet. <a href="{{ route('instructor.advisees', ['status' => 'pending']) }}">Start a deployment</a> first.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($selected)
    <div class="col-lg-8">
        <div class="il-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h6 fw-bold mb-0">{{ $selected['student']->user->name }}</h2>
                    <div class="small text-secondary">{{ $selected['deployment']?->hte?->name }}</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold">{{ $fmt($selected['approved']) }} <span class="small text-secondary fw-normal">/ {{ $fmt($selected['required']) }} hours approved</span></div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle small mb-0">
                    <thead>
                        <tr class="text-secondary" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;">
                            <th>Date</th><th>Morning</th><th>Afternoon</th><th class="text-end">Hours</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($days as $day)
                            <tr>
                                <td><div class="fw-semibold">{{ $day->log_date->format('M d, Y') }}</div><div class="text-secondary">{{ $day->log_date->format('l') }}</div></td>
                                <td class="text-nowrap">{{ $day->period('am') ?: '—' }}</td>
                                <td class="text-nowrap">{{ $day->period('pm') ?: '—' }}</td>
                                <td class="text-end fw-semibold">{{ $fmt($day->hours_logged) }}</td>
                                <td>
                                    <span class="badge rounded-pill {{ ['approved' => 'text-bg-success', 'flagged' => 'text-bg-danger'][$day->status] ?? 'text-bg-warning' }}">{{ $day->statusLabel() }}</span>
                                    @if ($day->status === 'flagged')
                                        <div class="text-secondary mt-1" style="font-size:.72rem;">{{ $day->flag_reason }}{{ $day->decliner ? ' — '.$day->decliner->name : '' }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($day->status === 'approved')
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#returnDay{{ $day->id }}">Return</button>
                                        <div class="modal fade text-start" id="returnDay{{ $day->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <form method="POST" action="{{ route('instructor.monitoring.return', $day) }}" class="modal-content">
                                                    @csrf
                                                    <div class="modal-header"><h5 class="modal-title">Return {{ $day->log_date->format('M d, Y') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                    <div class="modal-body">
                                                        <p class="small text-secondary">{{ $day->period('am') ?: '—' }} · {{ $day->period('pm') ?: '—' }} · {{ $fmt($day->hours_logged) }} hours. The student corrects it and sends it to the supervisor again. Its hours are taken off until it is approved again.</p>
                                                        <label class="form-label small fw-semibold">Reason</label>
                                                        <input type="text" name="flag_reason" class="form-control" maxlength="255" required placeholder="e.g. Afternoon time out is missing">
                                                    </div>
                                                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Return to student</button></div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">No days entered yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

<style>
    .il-form-list .list-group-item { border-left: 3px solid transparent; }
    .il-form-list .active-form { background: rgba(28, 63, 102, 0.07); border-left-color: var(--il-navy, #1c3f66); }
</style>
@endsection
