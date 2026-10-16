@extends('layouts.coordinator')

@section('title', 'Digital Repository - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Digital repository</h1>
        <p class="text-secondary small mb-0">Pick a student to open the files they have passed.</p>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Secure Digital Repository</h2>
        <a href="{{ route('coordinator.repository.create') }}" class="small fw-semibold text-decoration-none">
            <i class="ti ti-upload"></i> Upload File
        </a>
    </div>

    {{-- Search + filters --}}
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-6">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by student name or ID..."
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
        <div class="col-12">
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-search"></i> Search
            </button>
            @if (request()->hasAny(['search', 'document_requirement_id', 'program_id']))
                <a href="{{ route('coordinator.repository') }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    @php
        $preTotal = $stageTotals['pre_deployment'] ?? 0;
        $postTotal = $stageTotals['post_deployment'] ?? 0;
    @endphp

    <div class="table-responsive">
    <table class="table align-middle mb-0 il-repo-list">
        <thead>
            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Name</th>
                <th>Program</th>
                <th>Pre-deployment</th>
                <th>Post-deployment</th>
                <th>Files</th>
                <th class="text-end"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                @php
                    $files = $student->documents;
                    $fileCount = $files->count() + $files->sum(fn ($d) => $d->attachments->count());
                    $preDone = $files->filter(fn ($d) => $d->requirement->stage === 'pre_deployment' && $d->requirement->is_required)->count();
                    $postDone = $files->filter(fn ($d) => $d->requirement->stage === 'post_deployment' && $d->requirement->is_required)->count();
                    $openUrl = route('coordinator.repository', array_filter(['student' => $student->id, 'program_id' => request('program_id')]));
                    $initials = collect(explode(' ', $student->user->name))->map(fn ($p) => $p[0] ?? '')->take(2)->join('');
                @endphp
                <tr>
                    <td>
                        <a href="{{ $openUrl }}" class="d-flex align-items-center gap-2 text-decoration-none text-reset">
                            <span class="il-row-avatar">{{ $initials }}</span>
                            <span>
                                <span class="fw-semibold d-block">{{ $student->user->name }}</span>
                                <span class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</span>
                            </span>
                        </a>
                    </td>
                    <td><span class="badge rounded-pill text-bg-light text-secondary border">{{ $student->program->code ?? '—' }}</span></td>
                    <td><span class="badge rounded-pill {{ $preTotal > 0 && $preDone >= $preTotal ? 'text-bg-primary' : 'text-bg-light text-secondary' }}">{{ $preDone }} / {{ $preTotal }} passed</span></td>
                    <td><span class="badge rounded-pill {{ $postTotal > 0 && $postDone >= $postTotal ? 'text-bg-primary' : 'text-bg-light text-secondary' }}">{{ $postDone }} / {{ $postTotal }} passed</span></td>
                    <td class="small {{ $fileCount ? '' : 'text-secondary' }}">
                        @if ($fileCount)
                            <i class="ti ti-files text-secondary"></i> {{ $fileCount }} {{ \Illuminate\Support\Str::plural('file', $fileCount) }}
                        @else
                            No passed files yet
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if ($fileCount)
                            <a href="{{ route('coordinator.repository.student.download-all', $student) }}" class="btn btn-sm btn-outline-secondary" title="Download all as ZIP"><i class="ti ti-file-zip"></i></a>
                        @endif
                        <a href="{{ $openUrl }}" class="btn btn-sm {{ $fileCount ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $fileCount ? 'Open' : 'View' }}</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-secondary py-5">
                        @if (request()->hasAny(['search', 'document_requirement_id', 'program_id']))
                            No students match this search or filter.
                        @else
                            No students yet.
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
    .il-repo-list tbody tr:hover { background: rgba(28, 63, 102, 0.035); }
</style>
@endsection
