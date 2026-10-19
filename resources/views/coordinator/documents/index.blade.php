@extends('layouts.coordinator')

@section('title', 'Document Tracking - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Document Tracking</h1>
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
                        <a href="{{ route('instructor.documents', ['student' => $student->id]) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ti ti-eye"></i> View All
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-secondary py-4">
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
