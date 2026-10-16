@extends('layouts.instructor')

@section('title', $document->requirement->name.' - '.$student->user->name.' - PSU Intern-Link')

@section('content')
@php
    $requirement = $document->requirement;
    $status = $document->status;
    $printSign = $requirement->workflow_mode === 'print_sign';
    $structured = $requirement->hasStructuredFields();
    $hasFile = $document->file_path !== null;
    $isImageOrPdf = $hasFile && preg_match('/\.(pdf|png|jpe?g|gif|webp)$/i', (string) ($document->original_filename ?: $document->file_path));

    // What the left pane shows: the filled-in form, or the uploaded file.
    $frameUrl = $structured && ! ($status === 'approved' && $hasFile)
        ? route('instructor.documents.printable', ['document' => $document, 'embed' => 1])
        : ($hasFile && $isImageOrPdf ? route('instructor.documents.view', $document) : null);

    $statusText = match ($status) {
        'submitted', 'for_review' => 'Turned in - for review',
        'returned' => 'Returned to student',
        'printed' => 'Printed - out for signing',
        'completed' => 'Signed copy upload unlocked',
        'approved' => 'Approved',
        default => ucfirst((string) $status),
    };
    $statusClass = match ($status) {
        'submitted', 'for_review' => 'text-bg-warning',
        'returned' => 'text-bg-danger',
        'approved' => 'text-bg-success',
        default => 'text-bg-light border',
    };
    $awaiting = in_array($status, ['submitted', 'for_review'], true);
    $canReturn = $canAct && in_array($status, ['submitted', 'for_review', 'printed'], true);
    $backToStudent = route('instructor.documents', ['student' => $student->id, 'form' => $document->document_requirement_id]);
@endphp

<div class="il-page-header mb-2">
    <div class="min-w-0">
        <a href="{{ $backToStudent }}" class="small text-decoration-none"><i class="ti ti-arrow-left"></i> {{ $student->user->name }}'s forms</a>
        <h1 class="il-page-title mt-1 mb-0">
            {{ $requirement->name }}@if ($document->packet_number) <span class="text-secondary fw-normal">&middot; Packet {{ $document->packet_number }}</span>@endif
        </h1>
        <div class="small text-secondary">
            {{ $student->user->name }} &middot; {{ $student->user->id_number }} &middot; {{ $student->program->code ?? '' }}
            <span class="badge rounded-pill {{ $statusClass }} ms-1">{{ $statusText }}</span>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        @if ($prevDocument)
            <a href="{{ route('instructor.documents.show', $prevDocument) }}" class="btn btn-sm btn-outline-secondary" title="Previous form: {{ $prevDocument->requirement->name }}"><i class="ti ti-chevron-left"></i></a>
        @else
            <span class="btn btn-sm btn-outline-secondary disabled"><i class="ti ti-chevron-left"></i></span>
        @endif
        <span class="small text-secondary text-nowrap">Form {{ $position['index'] }} of {{ $position['total'] }} submitted</span>
        @if ($nextDocument)
            <a href="{{ route('instructor.documents.show', $nextDocument) }}" class="btn btn-sm btn-outline-secondary" title="Next form: {{ $nextDocument->requirement->name }}"><i class="ti ti-chevron-right"></i></a>
        @else
            <span class="btn btn-sm btn-outline-secondary disabled"><i class="ti ti-chevron-right"></i></span>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success py-2" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger py-2" role="alert">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    {{-- Left: the submitted form / file --}}
    <div class="col-lg-8">
        <div class="il-card p-0 overflow-hidden h-100" style="min-height: 72vh;">
            @if ($frameUrl)
                <iframe src="{{ $frameUrl }}" title="{{ $requirement->name }}" style="width: 100%; height: 78vh; border: 0; background: #f4f6f9;"></iframe>
            @elseif ($hasFile)
                <div class="d-flex flex-column align-items-center justify-content-center text-center p-5 h-100">
                    <i class="ti ti-file-description" style="font-size: 3rem;"></i>
                    <p class="mt-2 mb-3">{{ $document->original_filename }} can't be previewed here.</p>
                    <a href="{{ route('instructor.documents.download', $document) }}" class="btn btn-outline-primary"><i class="ti ti-download"></i> Download to view</a>
                </div>
            @else
                <div class="d-flex align-items-center justify-content-center text-secondary p-5 h-100">Nothing has been submitted for this form yet.</div>
            @endif
        </div>
    </div>

    {{-- Right: files, remarks and the Return / Approve panel --}}
    <div class="col-lg-4">
        <div class="il-card mb-3">
            <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">Files</p>
            <div class="small text-secondary mb-2">
                Turned in {{ $document->submitted_at?->format('M d, Y g:i A') ?? '—' }}
            </div>
            @if ($structured)
                <a href="{{ route('instructor.documents.printable', ['document' => $document, 'embed' => 1]) }}" target="_blank"
                   class="d-flex align-items-center gap-2 border rounded p-2 mb-2 text-decoration-none text-reset">
                    <i class="ti ti-forms text-primary fs-5"></i>
                    <span class="flex-grow-1 small fw-semibold">Filled-in form (online)</span>
                    <i class="ti ti-external-link text-secondary"></i>
                </a>
            @endif
            @if ($hasFile)
                <a href="{{ $isImageOrPdf ? route('instructor.documents.view', $document) : route('instructor.documents.download', $document) }}" target="_blank"
                   class="d-flex align-items-center gap-2 border rounded p-2 mb-2 text-decoration-none text-reset">
                    <i class="ti ti-file-type-pdf text-danger fs-5"></i>
                    <span class="flex-grow-1 small fw-semibold text-break">{{ $document->original_filename ?: 'Uploaded file' }}{{ $structured ? ' (signed copy)' : '' }}</span>
                    <i class="ti ti-external-link text-secondary"></i>
                </a>
            @endif
            @if (! $structured && ! $hasFile)
                <div class="small text-secondary">No file uploaded.</div>
            @endif
        </div>

        @if ($reviews->isNotEmpty())
            <div class="il-card mb-3">
                <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">Previous remarks</p>
                @foreach ($reviews->take(5) as $review)
                    <div class="small {{ ! $loop->last ? 'border-bottom pb-2 mb-2' : '' }}">
                        <span class="fw-semibold">{{ $review->decision === 'returned' ? 'Returned' : 'Approved' }}</span>
                        <span class="text-secondary">&middot; {{ $review->reviewed_at?->format('M d, Y g:i A') }}{{ $review->instructor?->user ? ' · '.$review->instructor->user->name : '' }}</span>
                        @if ($review->remarks)
                            <div class="mt-1">{{ $review->remarks }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="il-card">
            @if (! $canAct)
                <p class="small text-secondary mb-0"><i class="ti ti-eye"></i> View only &mdash; this student's subject instructor reviews this form.</p>
            @elseif ($canReturn)
                <form method="POST" action="{{ route('instructor.documents.review', $document) }}" id="returnPanel">
                    @csrf
                    <input type="hidden" name="return_to" value="viewer">
                    <input type="hidden" name="decision" value="returned">
                    <label class="small fw-bold text-uppercase text-secondary mb-2 d-block" style="letter-spacing:.03em;" for="viewer_remarks">Remarks for the student</label>
                    <textarea name="remarks" id="viewer_remarks" class="form-control mb-2" rows="4" maxlength="2000"
                              placeholder="What needs to be corrected? (required when returning)">{{ old('remarks') }}</textarea>
                    <button type="submit" class="btn btn-danger w-100 mb-2" onclick="return document.getElementById('viewer_remarks').value.trim() !== '' || (alert('Add remarks so the student knows what to correct.'), false);">
                        <i class="ti ti-arrow-back-up"></i> Return to student
                    </button>
                </form>

                @if ($awaiting && $printSign)
                    <form method="POST" action="{{ route('instructor.documents.print', $document) }}">
                        @csrf
                        <input type="hidden" name="return_to" value="viewer">
                        <button type="submit" class="btn btn-primary w-100"><i class="ti ti-printer"></i> Looks good &mdash; print for signing</button>
                    </form>
                @elseif ($awaiting)
                    <form method="POST" action="{{ route('instructor.documents.review', $document) }}">
                        @csrf
                        <input type="hidden" name="return_to" value="viewer">
                        <input type="hidden" name="decision" value="approved">
                        <button type="submit" class="btn btn-primary w-100"><i class="ti ti-circle-check"></i> Approve</button>
                    </form>
                @elseif ($status === 'printed')
                    <form method="POST" action="{{ route('instructor.documents.complete', $document) }}">
                        @csrf
                        <input type="hidden" name="return_to" value="viewer">
                        <button type="submit" class="btn btn-primary w-100"><i class="ti ti-circle-check"></i> Signed paper is back &mdash; approve</button>
                    </form>
                    <p class="small text-secondary mt-2 mb-0">This unlocks the student's one-time upload of the signed copy.</p>
                @endif
            @else
                <p class="small text-secondary mb-0">
                    @switch($status)
                        @case('returned') Returned &mdash; waiting for the student's corrected resubmission. @break
                        @case('completed') Waiting for the student's one-time upload of the signed copy. @break
                        @case('approved') Approved. Nothing left to do on this form. @break
                        @default Nothing to review on this form right now.
                    @endswitch
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
