@extends('layouts.coordinator')

@section('title', 'Intern Master List - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Intern Master List</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('coordinator.students.import') }}" class="btn btn-outline-secondary text-decoration-none">
            <i class="ti ti-upload"></i> Import interns
        </a>
        <a href="{{ route('coordinator.students.create') }}" class="il-btn-primary text-decoration-none">
            <i class="ti ti-plus"></i> Add intern
        </a>
    </div>
</div>

@if (session('provisioned_account'))
    @php $acct = session('provisioned_account'); @endphp
    <div class="il-badge-generated mb-3">
        <strong>{{ $acct['name'] }}</strong>'s account was created.
        Share these credentials with the student - this password is shown once and cannot be recovered here:
        <div class="mt-2">
            ID number: <code>{{ $acct['id_number'] }}</code>
            &nbsp;&middot;&nbsp;
            Temporary password: <code>{{ $acct['temporary_password'] }}</code>
        </div>
    </div>
@endif

@if (session('import_results'))
    @php $results = session('import_results'); @endphp
    <div class="il-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 fw-bold mb-0">Import results</h2>
            <span class="small text-secondary">
                {{ count($results['created']) }} created &middot; {{ count($results['skipped']) }} skipped
            </span>
        </div>

        @if (count($results['created']))
            <div class="small text-secondary mb-1">
                Share each temporary password with the student — it's shown once and cannot be recovered later.
            </div>
            <div class="table-responsive mb-3" style="max-height: 220px; overflow-y: auto;">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase;">
                            <th>Row</th><th>Name</th><th>ID number</th><th>Temporary password</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results['created'] as $row)
                            <tr>
                                <td>{{ $row['row'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td><code>{{ $row['id_number'] }}</code></td>
                                <td><code>{{ $row['temporary_password'] }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (count($results['skipped']))
            <div class="small fw-semibold text-danger mb-1">Skipped rows</div>
            <ul class="small text-danger mb-0">
                @foreach ($results['skipped'] as $row)
                    <li>Row {{ $row['row'] }}: {{ $row['reason'] }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

<div class="il-card mb-3">

    {{-- Sub-header: title + running total, matching the prototype --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Intern Master List - AY 2025-2026</h2>
        <span class="text-secondary small">{{ $counts['all'] }} students total</span>
    </div>

    {{-- Search + filters --}}
    <form method="GET" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="col-md-6">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by name or ID number..."
                value="{{ request('search') }}"
            >
        </div>
        <div class="col-md-3">
            <select name="program_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>
                        {{ $program->code }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" @selected(request('status') === 'active')>Deployed</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                <option value="terminated" @selected(request('status') === 'terminated')>Terminated</option>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-search"></i> Search
            </button>
            @if (request()->hasAny(['search', 'program_id', 'status']))
                <a href="{{ route('coordinator.students.index', ['tab' => $tab]) }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    {{-- Tabs: All / Deployed / Pending / Incomplete Docs --}}
    <div class="il-tabs mb-0">
        @foreach ([
            'all' => 'All',
            'deployed' => 'Deployed',
            'pending' => 'Pending',
            'incomplete' => 'Incomplete',
        ] as $key => $label)
            <a
                href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}"
                class="il-tab {{ $tab === $key ? 'active' : '' }}"
            >
                {{ $label }} ({{ $counts[$key] }})
            </a>
        @endforeach
    </div>
</div>

<div class="il-card">
    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Student</th>
                <th>Program</th>
                <th>Host training establishment</th>
                <th>Deployment status</th>
                <th>Hours rendered</th>
                <th>Documents</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                @php
                    $deployment = $student->currentInternship?->deployment;

                    $docsComplete = $requiredDocsCount > 0
                        && $student->approved_documents_count >= $requiredDocsCount;

                    $deploymentLabel = match (true) {
                        $deployment?->status === 'active' => 'Deployed',
                        $deployment?->status === 'completed' => 'Completed',
                        $deployment?->status === 'terminated' => 'Terminated',
                        $deployment?->status === 'pending' => 'Pending',
                        $docsComplete => 'Ready for Deployment',
                        default => 'Incomplete Docs',
                    };
                    $deploymentColor = match ($deploymentLabel) {
                        'Deployed', 'Completed' => 'text-bg-primary',
                        'Pending' => 'text-bg-warning',
                        'Ready for Deployment' => 'text-bg-success',
                        default => 'text-bg-danger',
                    };

                    $approved = $student->approved_documents_count;
                    $pendingReview = $student->pending_review_documents_count;
                    $missing = max($requiredDocsCount - $approved - $pendingReview, 0);

                    $docsLabel = match (true) {
                        $requiredDocsCount === 0 => 'N/A',
                        $approved >= $requiredDocsCount => 'Complete',
                        $pendingReview > 0 && $missing === 0 => 'For Review',
                        default => "{$missing} Missing",
                    };
                    $docsColor = match (true) {
                        $docsLabel === 'Complete' => 'text-bg-primary',
                        $docsLabel === 'For Review' => 'text-bg-warning',
                        $docsLabel === 'N/A' => 'text-bg-light text-secondary',
                        default => 'text-bg-danger',
                    };
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $student->user->name }}</div>
                        <div class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</div>
                    </td>
                    <td>{{ $student->program->code }}</td>
                    <td>{{ $deployment?->hte?->name ?? '—' }}</td>
                    <td>
                        <span class="badge rounded-pill {{ $deploymentColor }}">{{ $deploymentLabel }}</span>
                    </td>
                    <td>
                        @if ($deployment?->status === 'active' || $deployment?->status === 'completed')
                            <span class="text-primary fw-semibold">{{ $student->hours_rendered }}</span>
                            / {{ $student->program->required_hours }}h
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <span class="badge rounded-pill {{ $docsColor }}">{{ $docsLabel }}</span>
                    </td>
                    <td class="text-end">
                        <form
                            method="POST"
                            action="{{ route('coordinator.students.reset-password', $student) }}"
                            onsubmit="return confirm('Generate a new temporary password for {{ $student->user->name }}? Their current password will stop working immediately.')"
                        >
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                Reset password
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-secondary py-4">
                        @if (request()->hasAny(['search', 'program_id', 'status']) || $tab !== 'all')
                            No interns match this search or filter.
                        @else
                            No interns yet. Click "Add intern" to create the first account.
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

@push('styles')
<style>
    .il-list-header h2 { color: #1e2a45; }

    .il-filters-row .il-search-input {
        position: relative;
        display: flex;
        align-items: center;
    }
    .il-filters-row .il-search-input i {
        position: absolute;
        left: 12px;
        font-size: 0.9rem;
    }
    .il-filters-row .il-search-input input {
        padding-left: 32px;
        background: #f5f7fb;
        border: none;
    }
    .il-filters-row .il-filter-select {
        max-width: 160px;
        background: #f5f7fb;
        border: none;
    }

    .il-tabs {
        border-bottom: 1px solid #eef0f4;
    }
    .il-tabs .nav-link {
        color: #6b7280;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        padding: 0.5rem 0.25rem;
        margin-right: 1.5rem;
    }
    .il-tabs .nav-link.active {
        color: #2952e3;
        border-bottom: 2px solid #2952e3;
        background: transparent;
    }

    .il-status-badge {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .il-status-deployed {
        background: #eef0fb;
        color: #4c5ee0;
    }
    .il-status-pending {
        background: #fff3e0;
        color: #d98c1f;
    }
    .il-status-incomplete {
        background: #fdeaea;
        color: #e0473f;
    }
</style>
@endpush
