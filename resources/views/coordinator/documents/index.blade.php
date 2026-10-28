@extends('layouts.coordinator')

@section('title', 'Repository - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Repository</h1>
        <p class="text-secondary small mb-0">Every intern's document status and the files they have passed.</p>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 class="h6 fw-bold mb-0">All Interns</h2>
        <div class="d-flex gap-3">
            <a href="{{ route('coordinator.repository.create') }}" class="small fw-semibold text-decoration-none">
                <i class="ti ti-upload"></i> Upload File
            </a>
        </div>
    </div>

    {{-- Search + filters --}}
    <form method="GET" class="row g-2 mb-3 align-items-center">
        <div class="col-md il-search">
            <i class="ti ti-search"></i>
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by name or ID number..."
                value="{{ request('search') }}"
            >
        </div>
        <div class="col-md-3">
            <select name="document_requirement_id" class="form-select" onchange="this.form.submit()">
                <option value="">All File Types</option>
                @foreach ($requirements as $requirement)
                    <option value="{{ $requirement->id }}" @selected(request('document_requirement_id') == $requirement->id)>
                        {{ $requirement->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="program_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>
                        {{ $program->code }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-auto d-flex align-items-center gap-1">
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-search"></i> Search
            </button>
            @if (request()->hasAny(['search', 'program_id', 'document_requirement_id']))
                <a href="{{ route('coordinator.documents') }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    @php
        $preTotal = $stageTotals['pre_deployment'] ?? 0;
        $postTotal = $stageTotals['post_deployment'] ?? 0;
    @endphp

    <div class="table-responsive">
    <table class="table align-middle mb-0 il-track-list">
        <thead>
            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Student</th>
                <th>Endorsement letter</th>
                <th>MOA status</th>
                <th>Latest DTR</th>
                <th>Narrative report</th>
                <th>Passed files</th>
                <th class="text-end"></th>
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

                    // Passed files (the repository part of this page)
                    $passed = $student->documents;
                    $fileCount = $passed->filter(fn ($d) => filled($d->file_path))->count() + $passed->sum(fn ($d) => $d->attachments->count());
                    $preDone = $passed->filter(fn ($d) => $d->requirement->stage === 'pre_deployment' && $d->requirement->is_required)->count();
                    $postDone = $passed->filter(fn ($d) => $d->requirement->stage === 'post_deployment' && $d->requirement->is_required)->count();
                    $filesUrl = route('coordinator.repository', array_filter(['student' => $student->id, 'program_id' => request('program_id')]));
                @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="il-row-avatar">{{ \App\Models\User::initialsOf($student->user->name) }}</span>
                            <span>
                                <span class="fw-semibold d-block">{{ $student->user->name }}</span>
                                <span class="text-secondary" style="font-size: 0.78rem;">
                                    {{ $student->user->id_number }} &middot; {{ $student->program->code }}
                                </span>
                            </span>
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
                    <td class="small">
                        @if ($fileCount)
                            <div><i class="ti ti-files text-secondary"></i> {{ $fileCount }} {{ \Illuminate\Support\Str::plural('file', $fileCount) }}</div>
                        @else
                            <div class="text-secondary">No passed files yet</div>
                        @endif
                        <div class="text-secondary" style="font-size: 0.74rem;">
                            Pre {{ $preDone }} / {{ $preTotal }} &middot; Post {{ $postDone }} / {{ $postTotal }} passed
                        </div>
                    </td>
                    <td class="text-end text-nowrap">
                        @if ($fileCount)
                            <a href="{{ route('coordinator.repository.student.download-all', $student) }}" class="btn btn-sm btn-outline-secondary" title="Download passed files as ZIP"><i class="ti ti-file-zip"></i></a>
                            <a href="{{ $filesUrl }}" class="btn btn-sm btn-primary" title="Open the files they have passed"><i class="ti ti-folder-open"></i> Files</a>
                        @else
                            <a href="{{ $filesUrl }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-folder"></i> Files</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-secondary py-4">
                        @if (request()->hasAny(['search', 'program_id', 'document_requirement_id']))
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
</div>

<div class="mt-3">
    {{ $students->links() }}
</div>
<style>
    .il-track-list tbody tr:hover { background: rgba(28, 63, 102, 0.035); }
</style>
@endsection
