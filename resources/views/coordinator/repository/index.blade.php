@extends('layouts.coordinator')

@section('title', 'Digital Repository - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Digital repository</h1>
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
        $stageLabels = ['pre_deployment' => 'Pre-deployment', 'active' => 'During training', 'post_deployment' => 'Post-deployment'];
        $preTotal = $stageTotals['pre_deployment'] ?? 0;
        $postTotal = $stageTotals['post_deployment'] ?? 0;
    @endphp

    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Student</th>
                <th>Program</th>
                <th>Pre-deployment</th>
                <th>Post-deployment</th>
                <th>Access</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                @php
                    $files = $student->documents->sortBy(fn ($d) => [$d->requirement->stage === 'pre_deployment' ? 1 : ($d->requirement->stage === 'active' ? 2 : 3), $d->requirement->display_order]);
                    $preDone = $files->filter(fn ($d) => $d->requirement->stage === 'pre_deployment' && $d->requirement->is_required)->count();
                    $postDone = $files->filter(fn ($d) => $d->requirement->stage === 'post_deployment' && $d->requirement->is_required)->count();
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $student->user->name }}</div>
                        <div class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</div>
                    </td>
                    <td>{{ $student->program->code ?? '—' }}</td>
                    <td><span class="badge rounded-pill {{ $preTotal > 0 && $preDone >= $preTotal ? 'text-bg-primary' : 'text-bg-light text-secondary' }}">{{ $preDone }} / {{ $preTotal }} passed</span></td>
                    <td><span class="badge rounded-pill {{ $postTotal > 0 && $postDone >= $postTotal ? 'text-bg-primary' : 'text-bg-light text-secondary' }}">{{ $postDone }} / {{ $postTotal }} passed</span></td>
                    <td>
                        @if ($files->isNotEmpty())
                            <a href="{{ route('coordinator.repository.student.download-all', $student) }}" class="btn btn-sm btn-primary">
                                <i class="ti ti-file-zip"></i> Download all (ZIP)
                            </a>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#files-{{ $student->id }}">
                                Files ({{ $files->count() }})
                            </button>
                        @else
                            <span class="text-secondary small">No passed files yet</span>
                        @endif
                    </td>
                </tr>
                @if ($files->isNotEmpty())
                    <tr class="collapse" id="files-{{ $student->id }}">
                        <td colspan="5" class="bg-light">
                            <table class="table table-sm mb-0 align-middle">
                                <tbody>
                                    @foreach ($files as $document)
                                        <tr>
                                            <td style="width:34%">{{ $document->requirement->name }}</td>
                                            <td class="text-secondary small">{{ $stageLabels[$document->requirement->stage] ?? '' }}</td>
                                            <td class="text-secondary small">{{ $document->submitted_at?->format('M d, Y') ?? '—' }}</td>
                                            <td class="text-secondary small">{{ $document->uploader?->role?->name ?? 'Student' }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('coordinator.repository.download', $document) }}" class="text-decoration-none small fw-semibold">
                                                    <i class="ti ti-download"></i> Download
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="5" class="text-center text-secondary py-4">
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

<div class="mt-3">
    {{ $students->links() }}
</div>
@endsection
