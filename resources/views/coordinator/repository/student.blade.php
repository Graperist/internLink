@extends('layouts.coordinator')

@section('title', 'Digital Repository - '.$student->user->name.' - PSU Intern-Link')

@section('content')
@php
    $stageLabels = [
        'pre_deployment' => 'Pre-Deployment',
        'active' => 'During Training',
        'post_deployment' => 'Post-Deployment',
    ];
    $listQuery = array_filter(['program_id' => request('program_id')]);
    $studentUrl = fn ($id, $file = null) => route('coordinator.repository', array_filter(['student' => $id, 'file' => $file] + $listQuery));
    $preTotal = $stageTotals['pre_deployment'] ?? 0;
    $postTotal = $stageTotals['post_deployment'] ?? 0;
    $preDone = $passedByStage['pre_deployment'] ?? 0;
    $postDone = $passedByStage['post_deployment'] ?? 0;
    $fileIcon = fn ($name) => preg_match('/\.pdf$/i', (string) $name) ? ['ti-file-type-pdf', 'text-danger']
        : (preg_match('/\.(png|jpe?g|gif|webp)$/i', (string) $name) ? ['ti-photo', 'text-primary'] : ['ti-file', 'text-secondary']);
@endphp

<div class="il-page-header">
    <div>
        <a href="{{ route('coordinator.repository', $listQuery) }}" class="small text-decoration-none"><i class="ti ti-arrow-left"></i> All students</a>
        <h1 class="il-page-title mt-1">Digital repository</h1>
    </div>
    <div class="d-flex align-items-center gap-2">
        @if ($prevStudent)
            <a href="{{ $studentUrl($prevStudent->id) }}" class="btn btn-sm btn-outline-secondary" title="Previous: {{ $prevStudent->user->name }}"><i class="ti ti-chevron-left"></i></a>
        @else
            <span class="btn btn-sm btn-outline-secondary disabled"><i class="ti ti-chevron-left"></i></span>
        @endif
        <span class="small text-secondary">Student {{ $position['index'] }} of {{ $position['total'] }}</span>
        @if ($nextStudent)
            <a href="{{ $studentUrl($nextStudent->id) }}" class="btn btn-sm btn-outline-secondary" title="Next: {{ $nextStudent->user->name }}"><i class="ti ti-chevron-right"></i></a>
        @else
            <span class="btn btn-sm btn-outline-secondary disabled"><i class="ti ti-chevron-right"></i></span>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

{{-- Student header --}}
<div class="il-card mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="h5 fw-bold mb-1">
                {{ $student->user->name }}
                <span class="text-secondary fw-normal small">{{ $student->user->id_number }}</span>
                <span class="badge rounded-pill text-bg-light text-secondary fw-semibold">{{ $student->program->code ?? '' }}</span>
            </h2>
            <div class="d-flex flex-wrap gap-2 mt-2 small">
                <span class="badge rounded-pill {{ $preTotal > 0 && $preDone >= $preTotal ? 'text-bg-primary' : 'text-bg-light text-secondary border' }}">Pre-deployment {{ $preDone }} / {{ $preTotal }} passed</span>
                <span class="badge rounded-pill {{ $postTotal > 0 && $postDone >= $postTotal ? 'text-bg-primary' : 'text-bg-light text-secondary border' }}">Post-deployment {{ $postDone }} / {{ $postTotal }} passed</span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" class="d-flex align-items-center gap-2 position-relative" id="repoJumpForm">
                <label class="small text-secondary mb-0" for="repoJumpInput">Jump to</label>
                <input type="hidden" name="student" id="repoJumpValue" value="{{ $student->id }}">
                @if (request('program_id'))<input type="hidden" name="program_id" value="{{ request('program_id') }}">@endif
                <input type="text" id="repoJumpInput" class="form-control form-control-sm" style="width: 240px;"
                       placeholder="Search by name or ID number…" value="{{ $student->user->name }} ({{ $student->user->id_number }})" autocomplete="off">
                <div id="repoJumpResults" class="list-group shadow-sm d-none"
                     style="position: absolute; top: 100%; right: 0; z-index: 1050; width: 320px; max-height: 320px; overflow-y: auto;"></div>
            </form>
            @if ($files->isNotEmpty())
                <a href="{{ route('coordinator.repository.student.download-all', $student) }}" class="btn btn-sm btn-primary">
                    <i class="ti ti-file-zip"></i> Download all (ZIP)
                </a>
            @endif
        </div>
    </div>
</div>

@if ($files->isEmpty())
    <div class="il-card text-center text-secondary py-5">
        <i class="ti ti-folder-off" style="font-size: 2rem;"></i>
        <p class="mt-2 mb-0">{{ $student->user->name }} has no passed files yet. Forms appear here once they are approved in Document Review.</p>
    </div>
@else
    <div class="row g-3">
        {{-- Left: the passed files, like the Document Review forms list --}}
        <div class="col-lg-4">
            <div class="il-card p-0 overflow-hidden" style="position: sticky; top: 1rem;">
                <div class="px-3 pt-3 pb-2 d-flex justify-content-between align-items-center">
                    <span class="small fw-bold text-uppercase text-secondary" style="letter-spacing:.03em;">Passed files</span>
                    <span class="small text-secondary">{{ $files->count() }} {{ \Illuminate\Support\Str::plural('file', $files->count()) }}</span>
                </div>
                <div class="list-group list-group-flush il-form-list">
                    @foreach ($stageLabels as $stageKey => $stageLabel)
                        @php $stageFiles = $files->where('stage', $stageKey); @endphp
                        @continue ($stageFiles->isEmpty())
                        <div class="list-group-item bg-light small fw-semibold text-secondary py-1">{{ $stageLabel }}</div>
                        @foreach ($stageFiles as $file)
                            @php
                                $isOpen = $selected && $file['key'] === $selected['key'];
                                [$icon, $iconColor] = $file['kind'] === 'attachment' ? ['ti-paperclip', 'text-secondary'] : ['ti-circle-check-filled', 'text-success'];
                            @endphp
                            <a href="{{ $studentUrl($student->id, $file['key']) }}"
                               class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ $isOpen ? 'active-form' : '' }} {{ $file['kind'] === 'attachment' ? 'ps-4' : '' }}"
                               @if ($isOpen) aria-current="true" @endif>
                                <i class="ti {{ $icon }} {{ $iconColor }} fs-5 flex-shrink-0"></i>
                                <span class="flex-grow-1" style="min-width: 0;">
                                    <span class="d-block small fw-semibold" style="overflow-wrap: anywhere;">{{ $file['title'] }}</span>
                                    <span class="d-block text-secondary" style="font-size: 0.72rem;">
                                        {{ $file['kind'] === 'attachment' ? 'Attached to '.\Illuminate\Support\Str::before($file['parent'], ' – ') : 'Passed' }}{{ $file['date'] ? ' · '.$file['date']->format('M d, Y') : '' }}
                                    </span>
                                </span>
                                <span class="small fw-semibold flex-shrink-0 {{ $isOpen ? 'text-secondary' : 'text-primary' }}">{{ $isOpen ? 'Open' : 'View' }}</span>
                            </a>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Right: the open file --}}
        <div class="col-lg-8">
            @php [$selIcon, $selIconColor] = $fileIcon($selected['filename']); @endphp
            <div class="il-card mb-3">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                    <div style="min-width: 0;">
                        <p class="small fw-bold text-uppercase text-secondary mb-1" style="letter-spacing: 0.03em;">{{ $stageLabels[$selected['stage']] ?? '' }}{{ $selected['kind'] === 'attachment' ? ' · Attachment' : '' }}</p>
                        <div class="fw-bold">{{ $selected['title'] }}</div>
                        @if ($selected['kind'] === 'attachment')
                            <div class="small text-secondary">Attached to {{ $selected['parent'] }}</div>
                        @endif
                    </div>
                    <div class="d-flex gap-2">
                        @if ($selected['previewable'])
                            <a href="{{ $selected['view_url'] }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="ti ti-external-link"></i> Open in new tab</a>
                        @endif
                        <a href="{{ $selected['download_url'] }}" class="btn btn-sm btn-primary"><i class="ti ti-download"></i> Download</a>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-3 small text-secondary">
                    <span><i class="ti {{ $selIcon }} {{ $selIconColor }}"></i> {{ $selected['filename'] ?: 'File' }}</span>
                    <span><i class="ti ti-calendar"></i> {{ $selected['kind'] === 'attachment' ? 'Uploaded' : 'Passed' }} {{ $selected['date']?->format('M d, Y') ?? '—' }}</span>
                    <span><i class="ti ti-user"></i> {{ $selected['uploader'] }}</span>
                </div>
            </div>

            <div class="il-card p-0 overflow-hidden" style="min-height: 70vh;">
                @if ($selected['previewable'])
                    <iframe src="{{ $selected['view_url'] }}" title="{{ $selected['title'] }}" style="width: 100%; height: 75vh; border: 0; background: #f4f6f9;"></iframe>
                @else
                    <div class="d-flex flex-column align-items-center justify-content-center text-center p-5" style="min-height: 70vh;">
                        <i class="ti {{ $selIcon }} {{ $selIconColor }}" style="font-size: 3rem;"></i>
                        <p class="mt-2 mb-3">{{ $selected['filename'] }} can't be previewed here.</p>
                        <a href="{{ $selected['download_url'] }}" class="btn btn-outline-primary"><i class="ti ti-download"></i> Download to view</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

@php $jumpData = $jumpList; @endphp
<script>
(function () {
    const students = @json($jumpData);
    const input = document.getElementById('repoJumpInput');
    const hidden = document.getElementById('repoJumpValue');
    const results = document.getElementById('repoJumpResults');
    const form = document.getElementById('repoJumpForm');

    function render(list) {
        results.innerHTML = '';
        if (!list.length) {
            results.innerHTML = '<div class="list-group-item small text-secondary">No matching students.</div>';
        }
        list.slice(0, 20).forEach(function (s) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action small';
            item.textContent = s.name + ' (' + s.id_number + ')';
            item.addEventListener('click', function () { hidden.value = s.id; form.submit(); });
            results.appendChild(item);
        });
        results.classList.remove('d-none');
    }

    input.addEventListener('focus', function () { input.select(); render(students); });
    input.addEventListener('input', function () {
        const q = input.value.trim().toLowerCase();
        render(students.filter(s => (s.name + ' ' + s.id_number).toLowerCase().includes(q)));
    });
    document.addEventListener('click', function (e) { if (!form.contains(e.target)) results.classList.add('d-none'); });
    form.addEventListener('submit', function (e) { if (!hidden.value) e.preventDefault(); });
})();
</script>

<style>
    .il-form-list .list-group-item { border-left: 3px solid transparent; }
    .il-form-list .active-form { background: rgba(28, 63, 102, 0.07); border-left-color: var(--il-navy, #1c3f66); }
</style>
@endsection
