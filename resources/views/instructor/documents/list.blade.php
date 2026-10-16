@extends('layouts.instructor')

@section('title', 'Document Review - PSU Intern-Link')

@section('content')
@php
    $isCoordinator = $instructor->isCoordinator();
    $tabs = [
        'all' => 'All students',
        'review' => 'Needs review',
        'missing' => 'Missing / returned',
        'complete' => 'All approved',
    ];
@endphp

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Document review</h1>
        <p class="text-secondary small mb-0">Pick a student to open their forms.</p>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<div class="il-card">
    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3">
        @foreach ($tabs as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active fw-semibold' : 'text-secondary' }}"
                   href="{{ route('instructor.documents', array_filter(['tab' => $key === 'all' ? null : $key, 'search' => request('search'), 'program_id' => request('program_id')])) }}">
                    {{ $label }} <span class="badge rounded-pill {{ $tab === $key ? 'text-bg-primary' : 'text-bg-light text-secondary' }}">{{ $counts[$key] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <form method="GET" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="{{ $tab === 'all' ? '' : $tab }}">
        <div class="{{ $isCoordinator ? 'col-md-7' : 'col-md-10' }}">
            <input type="text" name="search" class="form-control" placeholder="Search by name or ID number..." value="{{ request('search') }}">
        </div>
        @if ($isCoordinator)
            <div class="col-md-3">
                <select name="program_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All programs</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                            {{ $program->code }}{{ $program->id === $instructor->program_id ? ' (mine)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary"><i class="ti ti-search"></i> Search</button>
        </div>
    </form>

    <form method="POST" action="{{ route('instructor.documents.remind') }}" id="bulkForm">
        @csrf
        <div class="d-flex align-items-center gap-2 mb-2 small" id="bulkBar">
            <span class="text-secondary" id="selectedCount">0 selected</span>
            <button type="submit" class="btn btn-sm btn-outline-primary" id="remindButton" disabled>
                <i class="ti ti-bell-ringing"></i> Remind selected about missing forms
            </button>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 il-review-list">
                <thead>
                    <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                        <th style="width: 36px;"><input type="checkbox" class="form-check-input" id="selectAll" aria-label="Select all"></th>
                        <th>Name</th>
                        @if ($isCoordinator)<th>Program</th>@endif
                        <th>Status</th>
                        <th>Approved</th>
                        <th>Last submitted</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $student = $row->student;
                            $percent = $row->required > 0 ? (int) round($row->approved / $row->required * 100) : 0;
                            $openUrl = route('instructor.documents', ['student' => $student->id]);
                            $initials = collect(explode(' ', $student->user->name))->map(fn ($p) => $p[0] ?? '')->take(2)->join('');
                        @endphp
                        <tr>
                            <td>
                                @if ($row->can_act)
                                    <input type="checkbox" class="form-check-input row-check" name="student_ids[]" value="{{ $student->id }}" aria-label="Select {{ $student->user->name }}">
                                @endif
                            </td>
                            <td>
                                <a href="{{ $openUrl }}" class="d-flex align-items-center gap-2 text-decoration-none text-reset">
                                    <span class="il-row-avatar">{{ $initials }}</span>
                                    <span>
                                        <span class="fw-semibold d-block">{{ $student->user->name }}</span>
                                        <span class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</span>
                                    </span>
                                </a>
                            </td>
                            @if ($isCoordinator)
                                <td>
                                    <span class="badge rounded-pill {{ $row->can_act ? 'text-bg-primary' : 'text-bg-light text-secondary border' }}">{{ $student->program->code ?? '—' }}</span>
                                </td>
                            @endif
                            <td>
                                @if ($row->to_review > 0)
                                    <span class="text-warning-emphasis fw-semibold small"><i class="ti ti-alert-circle"></i> {{ $row->to_review }} to review</span>
                                @elseif ($row->required > 0 && $row->approved >= $row->required)
                                    <span class="text-success fw-semibold small"><i class="ti ti-circle-check"></i> All approved</span>
                                @elseif ($row->returned > 0)
                                    <span class="text-danger small"><i class="ti ti-arrow-back-up"></i> {{ $row->returned }} returned</span>
                                @elseif ($row->signing > 0)
                                    <span class="text-secondary small"><i class="ti ti-printer"></i> {{ $row->signing }} with student to print &amp; sign</span>
                                @else
                                    <span class="text-secondary small"><i class="ti ti-clock"></i> Waiting on student</span>
                                @endif
                                @if ($row->missing > 0 && $row->to_review > 0)
                                    <div class="text-secondary" style="font-size: 0.72rem;">{{ $row->missing }} not yet submitted</div>
                                @endif
                            </td>
                            <td style="min-width: 140px;">
                                <div class="small fw-semibold">{{ $row->approved }} / {{ $row->required }}</div>
                                <div class="progress" style="height: 5px;" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar {{ $percent === 100 ? 'bg-success' : '' }}" style="width: {{ $percent }}%"></div>
                                </div>
                            </td>
                            <td class="text-secondary small">{{ $row->last_submitted?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ $openUrl }}" class="btn btn-sm {{ $row->to_review > 0 && $row->can_act ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    {{ $row->to_review > 0 && $row->can_act ? 'Review' : 'View' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isCoordinator ? 7 : 6 }}" class="text-center text-secondary py-5">
                                @if (request()->hasAny(['search', 'program_id']) || $tab !== 'all')
                                    No students match this tab or search.
                                @else
                                    {{ $isCoordinator ? 'No students are on your campus yet.' : 'No students are in your program yet.' }}
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
</div>

<div class="mt-3">
    {{ $rows->links() }}
</div>

<style>
    .il-review-list tbody tr:hover { background: rgba(28, 63, 102, 0.035); }
</style>

<script>
(function () {
    const all = document.getElementById('selectAll');
    const checks = () => Array.from(document.querySelectorAll('.row-check'));
    const count = document.getElementById('selectedCount');
    const button = document.getElementById('remindButton');

    function refresh() {
        const n = checks().filter(c => c.checked).length;
        count.textContent = n + ' selected';
        button.disabled = n === 0;
        all.checked = n > 0 && n === checks().length;
        all.indeterminate = n > 0 && n < checks().length;
    }

    all.addEventListener('change', () => { checks().forEach(c => c.checked = all.checked); refresh(); });
    document.addEventListener('change', e => { if (e.target.classList.contains('row-check')) refresh(); });
    if (!checks().length) { all.disabled = true; }
    refresh();
})();
</script>
@endsection
