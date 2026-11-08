@extends('layouts.instructor')

@section('title', 'Document Review - PSU Intern-Link')

@section('content')
@php
    // Document Review lists the reviewer's own program (the coordinator follows
    // every program in Document Tracking), so no program column or filter.
    $isCoordinator = false;
    $tabs = [
        'queue' => 'To review',
        'missing' => 'Missing / returned',
        'all' => 'All students',
        'approved' => 'Approved',
    ];
@endphp

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Document review</h1>
        <p class="text-secondary small mb-0">{{ match ($tab) { 'queue' => 'The forms waiting on you, oldest first. Review opens the form.', 'missing' => 'Students with forms still to come in: returned first, then not yet submitted.', 'approved' => 'Students with approved forms. Click a form to open it.', default => 'Pick a student to open their forms.' } }}</p>
    </div>
    @include('instructor.documents._form19-deadline')
</div>
@include('instructor.documents._form19-deadline-form')

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
                   href="{{ route('instructor.documents', array_filter(['tab' => $key === 'queue' ? null : $key, 'search' => request('search'), 'program_id' => request('program_id'), 'hte_id' => request('hte_id')]) + $term->query()) }}">
                    {{ $label }} <span class="badge rounded-pill {{ $tab === $key ? 'text-bg-primary' : 'text-bg-light text-secondary' }}">{{ $counts[$key] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    {{-- All the filters on one row. --}}
    <form method="GET" class="d-flex flex-wrap flex-xl-nowrap gap-2 align-items-center mb-3">
        <input type="hidden" name="tab" value="{{ $tab === 'queue' ? '' : $tab }}">
        @include('partials.term-filter', ['term' => $term, 'idPrefix' => 'review'])
        <input type="text" name="search" class="form-control" style="flex: 1 1 180px; min-width: 150px;" placeholder="Search name or ID..." value="{{ request('search') }}">
        @if ($isCoordinator)
            <select name="program_id" class="form-select" style="flex: 0 1 150px; min-width: 120px;" onchange="this.form.submit()" aria-label="Program">
                <option value="">All programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                        {{ $program->code }}{{ $program->id === $instructor->program_id ? ' (mine)' : '' }}
                    </option>
                @endforeach
            </select>
        @endif
        <select name="hte_id" class="form-select" style="flex: 0 1 200px; min-width: 150px;" onchange="this.form.submit()" aria-label="Partner agency">
            <option value="">All partner agencies</option>
            @foreach ($agencies as $agency)
                <option value="{{ $agency->id }}" @selected((string) request('hte_id') === (string) $agency->id)>{{ $agency->name }}</option>
            @endforeach
            <option value="none" @selected(request('hte_id') === 'none')>No partner agency yet</option>
        </select>
        <button type="submit" class="btn btn-outline-primary flex-shrink-0"><i class="ti ti-search"></i> Search</button>
    </form>
    @php $nmTerm = \App\Support\TermFilter::fromRequest(request()); @endphp
    @if ($rows->total() === 0 && ($counts['all'] ?? 1) === 0 && ! $term->isDefault())
        @include('partials.no-match', ['term' => $nmTerm, 'what' => 'students', 'resetUrl' => route('instructor.documents')])
    @endif

    @if ($tab === 'queue')
        <div class="table-responsive">
            <table class="table align-middle mb-0 il-review-list">
                <thead>
                    <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                        <th>Student</th><th>Form</th><th>Status</th><th>Submitted</th><th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $item)
                        @php
                            $student = $item->student;
                            $document = $item->document;
                            $reviewUrl = route('instructor.documents', ['student' => $student->id, 'form' => $document->document_requirement_id]);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ $reviewUrl }}" class="d-flex align-items-center gap-2 text-decoration-none text-reset">
                                    <span class="il-row-avatar">{{ \App\Models\User::initialsOf($student->user->name) }}</span>
                                    <span>
                                        <span class="fw-semibold d-block">{{ $student->user->name }}</span>
                                        <span class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="small">{{ $item->requirement?->name ?? 'Form' }}</td>
                            <td>
                                @if ($document->status === 'completed')
                                    <span class="text-primary small fw-semibold"><i class="ti ti-signature"></i> Signed copy uploaded &middot; confirm signatures</span>
                                @else
                                    <span class="text-warning-emphasis small fw-semibold"><i class="ti ti-alert-circle"></i> Awaiting your review</span>
                                @endif
                            </td>
                            <td class="text-secondary small text-nowrap">{{ $document->submitted_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-end"><a href="{{ $reviewUrl }}" class="btn btn-sm btn-primary">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-5">
                            <i class="ti ti-circle-check fs-2 d-block mb-1 text-success"></i>
                            {{ request()->hasAny(['search', 'hte_id']) ? 'No forms to review match this search.' : 'Nothing waiting on you right now.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'missing')
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
                            <th>Student</th><th>Forms still to come in</th><th>Status</th><th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $item)
                            @php
                                $student = $item->student;
                                $openUrl = route('instructor.documents', ['student' => $student->id]);
                            @endphp
                            <tr>
                                <td>
                                    @if ($item->can_act)
                                        <input type="checkbox" class="form-check-input row-check" name="student_ids[]" value="{{ $student->id }}" aria-label="Select {{ $student->user->name }}">
                                    @endif
                                </td>
                                <td style="min-width: 200px;">
                                    <a href="{{ $openUrl }}" class="d-flex align-items-center gap-2 text-decoration-none text-reset">
                                        <span class="il-row-avatar">{{ \App\Models\User::initialsOf($student->user->name) }}</span>
                                        <span>
                                            <span class="fw-semibold d-block">{{ $student->user->name }}</span>
                                            <span class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="small">
                                    @include('instructor.documents._form-links', ['student' => $student, 'items' => $item->items, 'kind' => 'missing'])
                                </td>
                                <td class="small text-nowrap">
                                    @if ($item->returned > 0)
                                        <div class="text-danger fw-semibold"><i class="ti ti-arrow-back-up"></i> {{ $item->returned }} returned</div>
                                    @endif
                                    @if ($item->missing > 0)
                                        <div class="text-secondary"><i class="ti ti-clock"></i> {{ $item->missing }} not yet submitted</div>
                                    @endif
                                </td>
                                <td class="text-end"><a href="{{ $openUrl }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-5">
                                <i class="ti ti-circle-check fs-2 d-block mb-1 text-success"></i>
                                {{ request()->hasAny(['search', 'hte_id']) ? 'No students with missing forms match this search.' : 'No missing or returned forms.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    @elseif ($tab === 'approved')
        <div class="table-responsive">
            <table class="table align-middle mb-0 il-review-list">
                <thead>
                    <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                        <th>Student</th><th>Approved forms</th><th>Approved</th><th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $item)
                        @php
                            $student = $item->student;
                            $openUrl = route('instructor.documents', ['student' => $student->id]);
                            $percent = $item->required > 0 ? (int) round($item->approved / $item->required * 100) : 0;
                        @endphp
                        <tr>
                            <td style="min-width: 200px;">
                                <a href="{{ $openUrl }}" class="d-flex align-items-center gap-2 text-decoration-none text-reset">
                                    <span class="il-row-avatar">{{ \App\Models\User::initialsOf($student->user->name) }}</span>
                                    <span>
                                        <span class="fw-semibold d-block">{{ $student->user->name }}</span>
                                        <span class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="small">
                                @include('instructor.documents._form-links', ['student' => $student, 'items' => $item->items, 'kind' => 'approved'])
                            </td>
                            <td style="min-width: 130px;">
                                <div class="small fw-semibold">{{ $item->approved }} / {{ $item->required }}</div>
                                <div class="progress" style="height: 5px;" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar {{ $percent === 100 ? 'bg-success' : '' }}" style="width: {{ $percent }}%"></div>
                                </div>
                                @if ($percent === 100)
                                    <div class="text-success fw-semibold" style="font-size: 0.72rem;"><i class="ti ti-circle-check"></i> All approved</div>
                                @endif
                            </td>
                            <td class="text-end"><a href="{{ $openUrl }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-5">
                            {{ request()->hasAny(['search', 'hte_id']) ? 'No students with approved forms match this search.' : 'No forms approved yet.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
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
                            $initials = \App\Models\User::initialsOf($student->user->name);
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
                                @if (request()->hasAny(['search', 'program_id', 'hte_id']) || ! in_array($tab, ['all', 'queue'], true))
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
    @endif
</div>

<div class="mt-3">
    {{ $rows->links() }}
</div>

<style>
    .il-review-list tbody tr:hover { background: rgba(28, 63, 102, 0.035); }
    .il-form-links li { padding: 1px 0; }
    .il-form-links a:hover .text-body { text-decoration: underline; }
    .il-form-more summary { cursor: pointer; font-size: 0.78rem; list-style: none; padding-top: 2px; }
    .il-form-more summary::-webkit-details-marker { display: none; }
    .il-form-more[open] summary { display: none; }
</style>

<script>
(function () {
    const all = document.getElementById('selectAll');
    if (!all) return;   // the To review tab has no student checkboxes
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
