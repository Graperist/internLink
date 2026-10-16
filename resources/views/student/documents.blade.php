@extends('layouts.student')

@section('title', 'My Documents - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">My documents</h1>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if (session('field_warnings') && count(session('field_warnings')) > 0)
    <div class="alert alert-warning">
        <i class="ti ti-alert-triangle"></i> <strong>The system flagged {{ count(session('field_warnings')) === 1 ? 'something' : 'a few things' }} worth double-checking:</strong>
        <ul class="mb-0 mt-1">
            @foreach (session('field_warnings') as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
        You can still go back and correct it before your instructor reviews it, if needed.
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="il-card">
    <h2 class="h6 fw-bold mb-3">OJT Requirements</h2>

    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Requirement</th>
                <th>Stage</th>
                <th>Date submitted</th>
                <th>Instructor feedback</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @php
                $statusColor = fn ($label) => match ($label) {
                    'Approved' => 'text-bg-primary',
                    'Submitted', 'For Review', 'Sent to HTE' => 'text-bg-primary',
                    'Printed - Out for Signing' => 'text-bg-info',
                    'Signed Copy Upload Unlocked' => 'text-bg-success',
                    'Returned' => 'text-bg-danger',
                    default => 'text-bg-warning', // Not submitted
                };
            @endphp
            @foreach ($checklist as $row)
                @php
                    $doc = $row['document'];
                    $requirement = $row['requirement'];
                    $structured = $requirement->hasStructuredFields();
                    $repeating = $requirement->is_repeating;
                    $sentToHte = false; // reset per row; set below for Forms 20 / 23c

                    if ($repeating) {
                        $label = match ($doc?->status) {
                            'draft' => ($doc->entries->count() ?? 0).' entr'.($doc->entries->count() === 1 ? 'y' : 'ies').' logged',
                            'submitted', 'for_review' => 'For Review',
                            'returned' => 'Returned',
                            'printed' => 'Printed - Out for Signing',
                            'completed' => 'Signed Copy Upload Unlocked',
                            'approved' => 'Approved',
                            default => 'Not started',
                        };
                    } elseif ($structured) {
                        $sentToHte = in_array($requirement->code, \App\Support\EvaluationRouting::studentToHteCodes(), true)
                            && \App\Support\EvaluationRouting::isSubmitted($doc);
                        $label = $sentToHte ? 'Sent to HTE' : match ($doc?->status) {
                            'submitted', 'for_review' => 'For Review',
                            'returned' => 'Returned',
                            'printed' => 'Printed - Out for Signing',
                            'completed' => 'Signed Copy Upload Unlocked',
                            'approved' => 'Approved',
                            default => 'Not submitted',
                        };
                    } else {
                        $label = match ($doc?->status) {
                            'approved' => 'Approved',
                            'submitted' => 'Submitted',
                            'for_review' => 'For Review',
                            'returned' => 'Returned',
                            default => 'Not submitted',
                        };
                    }
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">@if($requirement->form_number && ! \Illuminate\Support\Str::startsWith($requirement->name, 'Form '))<span class="text-secondary me-1">Form {{ $requirement->form_number }}</span>· @endif{{ $requirement->name }}</div>
                        @if ($doc?->original_filename)
                            <div class="text-secondary" style="font-size: 0.75rem;">{{ $doc->original_filename }}</div>
                        @endif
                        @if ($doc && $doc->attachments->isNotEmpty())
                            <div class="d-flex flex-wrap gap-2 mt-1" style="font-size: 0.75rem;">
                                @foreach ($doc->attachments as $attachment)
                                    <a href="{{ route('student.documents.attachment', $attachment) }}" target="_blank" class="text-decoration-none"><i class="ti ti-paperclip"></i> {{ $attachment->label() }}</a>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="text-secondary small">{{ ucfirst(str_replace('_', ' ', $requirement->stage)) }}</td>
                    <td class="text-secondary small">{{ $doc?->submitted_at?->format('M d, Y') ?? 'Not yet submitted' }}</td>
                    <td class="text-secondary small">
                        {{ $doc?->latestReview?->remarks ?? '—' }}
                    </td>
                    <td>
                        <span class="badge rounded-pill {{ $statusColor($label) }}">{{ $label }}</span>
                    </td>
                    <td class="text-end">
                        @if ($repeating)
                            @if ($doc?->status === 'completed')
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#signedUploadModal"
                                    data-document-id="{{ $doc->id }}" data-requirement-name="{{ $requirement->name }}">
                                    Upload Signed Copy
                                </button>
                            @elseif ($doc?->status === 'approved')
                                <span class="text-secondary small">Signed copy received</span>
                            @elseif ($doc?->status === 'printed')
                                <span class="text-secondary small">Awaiting signed copy</span>
                            @else
                                <a href="{{ route('student.documents.log', $requirement) }}" class="btn btn-sm btn-outline-primary">
                                    View Log
                                </a>
                            @endif
                        @elseif ($structured)
                            @if ($sentToHte)
                                <span class="text-secondary small">Delivered to your HTE's Evaluations Received</span>
                            @elseif ($doc?->status === 'completed')
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#signedUploadModal"
                                    data-document-id="{{ $doc->id }}" data-requirement-name="{{ $requirement->name }}">
                                    Upload Signed Copy
                                </button>
                            @elseif ($doc?->status === 'approved')
                                <span class="text-secondary small">Signed copy received</span>
                            @elseif (in_array($doc?->status, ['printed']))
                                <span class="text-secondary small">Awaiting signed copy</span>
                            @else
                                <a href="{{ route('student.documents.fill', $requirement) }}" class="btn btn-sm btn-outline-primary">
                                    {{ $doc ? 'Edit Form' : 'Fill Form' }}
                                </a>
                            @endif
                        @else
                            @if ($doc?->status === 'returned' && $doc->latestReview?->remarks)
                                <div class="small text-danger mb-2 text-start">
                                    <strong>Returned:</strong> {{ $doc->latestReview->remarks }}
                                </div>
                            @endif

                            @if (! $doc || $doc->status === 'returned')
                                <button type="button" class="btn btn-sm {{ $doc?->status === 'returned' ? 'btn-primary' : 'btn-outline-primary' }}"
                                    data-bs-toggle="modal" data-bs-target="#uploadModal"
                                    data-requirement-id="{{ $requirement->id }}" data-requirement-name="{{ $requirement->name }}">
                                    {{ $doc?->status === 'returned' ? 'Upload Corrected File' : 'Upload' }}
                                </button>
                            @elseif (in_array($doc->status, ['submitted', 'for_review']))
                                <span class="text-secondary small">Awaiting instructor review</span>
                            @elseif ($doc->status === 'approved')
                                <span class="text-secondary small">Signed copy received</span>
                            @else
                                <span class="text-secondary small">{{ ucfirst($doc->status) }}</span>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Shared upload modal --}}
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('student.documents.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold" id="uploadModalLabel">Upload document</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="document_requirement_id" id="document_requirement_id">
                    <label class="form-label small fw-semibold">File (PDF, JPG, PNG — max 10MB)</label>
                    <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Upload Signed Copy modal (structured forms, only reachable once Completed) --}}
<div class="modal fade" id="signedUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="signedUploadForm" action="" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold" id="signedUploadModalLabel">Upload signed copy</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary">
                        Upload a scan of the physically signed copy. This can only be done once for this form.
                    </p>
                    <label class="form-label small fw-semibold">File (PDF, JPG, PNG — max 10MB)</label>
                    <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('uploadModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('uploadModalLabel').textContent = 'Upload — ' + button.getAttribute('data-requirement-name');
        document.getElementById('document_requirement_id').value = button.getAttribute('data-requirement-id');
    });

    document.getElementById('signedUploadModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('signedUploadModalLabel').textContent = 'Upload signed copy — ' + button.getAttribute('data-requirement-name');
        document.getElementById('signedUploadForm').action = '/student/documents/' + button.getAttribute('data-document-id') + '/upload-signed';
    });
</script>
@endsection
