@extends('layouts.coordinator')

@section('title', 'Document Tracking - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Document Tracking</h1>
</div>

{{-- HTE Evaluation Forms: Form 23b (Evaluation Instrument of PSU Partner Agencies - Self Ratee) --}}
<div class="il-card mb-4" id="hte-evaluation-forms">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
        <h2 class="h6 fw-bold mb-0">HTE Evaluation Forms <span class="text-secondary fw-normal">(Form 23b)</span></h2>
        <div class="d-flex flex-wrap gap-2">
            @foreach (['all' => 'All', 'submitted' => 'Submitted', 'pending' => 'Pending'] as $key => $label)
                <a href="{{ route('coordinator.documents', array_merge(request()->except('hte_eval', 'page'), $key === 'all' ? [] : ['hte_eval' => $key])) }}#hte-evaluation-forms"
                   class="btn btn-sm {{ $hteEvalFilter === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $label }} <span class="badge text-bg-light ms-1">{{ $hteEvalCounts[$key] }}</span>
                </a>
            @endforeach
        </div>
    </div>
    <p class="text-secondary small mb-3">
        Each host training establishment submits the Partner Agency self-evaluation once a student&rsquo;s internship is
        completed. Every submitted form lands here for your records.
    </p>

    <form method="GET" class="row g-2 mb-3">
        @foreach (request()->except('hte_search', 'page') as $k => $v)
            @if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        <div class="col-md-8">
            <input type="text" name="hte_search" class="form-control form-control-sm" placeholder="Search by HTE or student name..." value="{{ request('hte_search') }}">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="ti ti-search"></i> Search</button>
            @if (request()->filled('hte_search'))
                <a href="{{ route('coordinator.documents', request()->except('hte_search', 'page')) }}#hte-evaluation-forms" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                    <th>Host training establishment</th>
                    <th>Student</th>
                    <th>Submitted by</th>
                    <th>Date submitted</th>
                    <th>Status</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($hteEvaluations as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row->hte?->name ?? '—' }}</td>
                        <td>
                            {{ $row->student->user->name }}
                            <div class="text-secondary" style="font-size: 0.78rem;">{{ $row->student->user->id_number }} &middot; {{ $row->student->program->code ?? '' }}</div>
                        </td>
                        <td>{{ $row->document?->uploader?->name ?? '—' }}</td>
                        <td>{{ $row->document ? ($row->document->submitted_at ?? $row->document->created_at)->format('M d, Y') : '—' }}</td>
                        <td>
                            @if ($row->document)
                                <span class="badge rounded-pill text-bg-primary">Submitted</span>
                            @else
                                <span class="badge rounded-pill text-bg-warning">Pending</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($row->document)
                                <a href="{{ route('instructor.documents.printable', ['document' => $row->document, 'preview' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="ti ti-eye"></i> View Form 23b
                                </a>
                            @else
                                <span class="text-secondary small">Awaiting HTE</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">
                            @if (request()->filled('hte_search') || $hteEvalFilter !== 'all')
                                No HTE evaluation forms match this filter.
                            @else
                                No completed internships yet, so no HTE evaluation forms are due.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Document Tracking - All Interns</h2>
        <a href="{{ route('coordinator.documents.export', request()->query()) }}" class="small fw-semibold text-decoration-none">
            Export Report
        </a>
    </div>

    {{-- Search + filters --}}
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
            <select name="program_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>
                        {{ $program->code }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-search"></i> Search
            </button>
            @if (request()->hasAny(['search', 'program_id']))
                <a href="{{ route('coordinator.documents') }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Student</th>
                <th>Endorsement letter</th>
                <th>MOA status</th>
                <th>Latest DTR</th>
                <th>Narrative report</th>
                <th>Evaluation form</th>
                <th>Forms</th>
            </tr>
        </thead>
        <tbody>
            @php
                $badgeColor = fn ($label) => match ($label) {
                    'Approved', 'Submitted' => 'text-bg-primary',
                    'For Review', 'Pending' => 'text-bg-warning',
                    'Active' => 'text-bg-primary',
                    default => 'text-bg-danger', // Missing, Returned, Not Submitted
                };
            @endphp
            @forelse ($students as $student)
                @php
                    $hte = $student->currentInternship?->deployment?->hte;
                    // MOA status follows the student's own Form 10 (MOA) document once
                    // one exists; before that, fall back to the HTE partnership status.
                    $moaDoc = $student->docs_by_code->get('MOA');
                    $moaLabel = match (true) {
                        $moaDoc?->status === 'approved' => 'Approved',
                        $moaDoc?->status === 'submitted' => 'Submitted',
                        $moaDoc?->status === 'for_review' => 'For Review',
                        $moaDoc?->status === 'returned' => 'Returned',
                        $moaDoc !== null => 'Pending',
                        (bool) $hte => ucfirst($hte->moa_status),
                        default => 'Not Submitted',
                    };
                    $latestDtr = $student->currentInternship?->deployment?->latestDtr;

                    $endorsement = $student->docs_by_code->get('ENDORSEMENT_LETTER');
                    $narrative = $student->docs_by_code->get('NARRATIVE_REPORT');
                    $evaluation = $student->docs_by_code->get('EVALUATION_FORM');

                    $labelFor = fn ($doc) => match ($doc?->status) {
                        'approved' => 'Approved',
                        'submitted' => 'Submitted',
                        'for_review' => 'For Review',
                        'returned' => 'Returned',
                        'pending' => 'Pending',
                        default => 'Missing',
                    };
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $student->user->name }}</div>
                        <div class="text-secondary" style="font-size: 0.78rem;">
                            {{ $student->user->id_number }} &middot; {{ $student->program->code }}
                        </div>
                    </td>
                    <td>
                        <span class="badge rounded-pill {{ $badgeColor($labelFor($endorsement)) }}">
                            {{ $labelFor($endorsement) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge rounded-pill {{ $badgeColor($moaLabel) }}">{{ $moaLabel }}</span>
                    </td>
                    <td>
                        @if ($latestDtr)
                            <span class="badge rounded-pill text-bg-primary">{{ $latestDtr->log_date->format('M d') }}</span>
                        @else
                            <span class="badge rounded-pill text-bg-danger">Missing</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge rounded-pill {{ $badgeColor($labelFor($narrative)) }}">
                            {{ $labelFor($narrative) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge rounded-pill {{ $badgeColor($labelFor($evaluation)) }}">
                            {{ $labelFor($evaluation) }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('instructor.documents', ['student' => $student->id]) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ti ti-eye"></i> View All
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-secondary py-4">
                        @if (request()->hasAny(['search', 'program_id']))
                            No interns match this search or filter.
                        @else
                            No interns yet.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-3">
    {{ $students->links() }}
</div>
@endsection
