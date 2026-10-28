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

@php
    $stageLabels = [
        'pre_deployment' => 'Pre-Deployment Forms',
        'active' => 'Active Forms',
        'post_deployment' => 'Post-Deployment Forms',
    ];
    $statusColor = fn ($label) => match ($label) {
        'Approved', 'Submitted', 'For Review', 'Sent to HTE' => 'text-bg-primary',
        'Ready to Print' => 'text-bg-info',
        'Upload Signed Copy' => 'text-bg-primary',
        'Signed Copy Uploaded' => 'text-bg-primary',
        'Returned' => 'text-bg-danger',
        default => 'text-bg-warning',
    };

    // Form 17 is not submitted: it is the time frame the HTE approves, so it
    // only shows the approved hours and is not counted as a form to finish.
    $timeFrameCode = \App\Support\TimeFrameForm::CODE;
    $tfApprovedHours = $internship?->deployment
        ? (float) \App\Models\Dtr::where('deployment_id', $internship->deployment->id)->withApprovedTime()->get()->sum(fn ($d) => $d->countedHours())
        : 0.0;
    $tfRequiredHours = (float) ($internship?->requiredHours() ?? 0);

    // Form 19: one document per week.
    $weeklyDocs = $internship
        ? $internship->documents()->whereHas('requirement', fn ($q) => $q->where('code', 'WEEKLY_REPORT'))->get()
        : collect();

    // Status of one form: label, icon, and whether the student has to act.
    $info = function ($row) use ($timeFrameCode, $tfApprovedHours, $tfRequiredHours, $weeklyDocs) {
        $doc = $row['document'];
        $requirement = $row['requirement'];
        $structured = $requirement->hasStructuredFields();
        $sentToHte = false;

        if ($requirement->code === $timeFrameCode) {
            $label = \App\Support\TimeFrame::format($tfApprovedHours).($tfRequiredHours ? ' / '.\App\Support\TimeFrame::format($tfRequiredHours) : '').' hours rendered';
            $icon = 'ti-clock';
            $iconColor = 'text-primary';
            $structured = false;

            return compact('label', 'icon', 'iconColor', 'sentToHte', 'structured');
        }

        if ($requirement->code === 'WEEKLY_REPORT') {
            $approved = $weeklyDocs->where('status', 'approved')->count();
            [$label, $icon, $iconColor] = match (true) {
                $weeklyDocs->isEmpty() => ['Not started', 'ti-circle', 'text-secondary'],
                $weeklyDocs->contains('status', 'returned') => ['A week was returned', 'ti-arrow-back-up', 'text-danger'],
                $approved === $weeklyDocs->count() => ["All {$approved} ".\Illuminate\Support\Str::plural('week', $approved).' approved', 'ti-circle-check-filled', 'text-success'],
                default => ["{$approved} of {$weeklyDocs->count()} weeks approved", 'ti-calendar-week', 'text-primary'],
            };
            $structured = false;

            return compact('label', 'icon', 'iconColor', 'sentToHte', 'structured');
        }

        if ($requirement->is_repeating) {
            $isTimeFrame = $requirement->code === 'INTERNSHIP_TIME_FRAME';
            $label = match ($doc?->status) {
                'draft' => $isTimeFrame ? 'Not submitted' : $doc->entries->count().' entr'.($doc->entries->count() === 1 ? 'y' : 'ies').' logged',
                'submitted', 'for_review' => 'For Review',
                'returned' => 'Returned',
                'printed' => $doc->signatures_confirmed_at ? 'Upload Signed Copy' : 'Ready to Print',
                'completed' => filled($doc->file_path) ? 'Signed Copy Uploaded' : 'Upload Signed Copy', // no file yet: an older form still waiting for its upload
                'approved' => 'Approved',
                default => $isTimeFrame ? 'Not submitted' : 'Not started',
            };
        } elseif ($structured) {
            $sentToHte = in_array($requirement->code, \App\Support\EvaluationRouting::studentToHteCodes(), true)
                && \App\Support\EvaluationRouting::isSubmitted($doc);
            $label = $sentToHte ? 'Sent to HTE' : match ($doc?->status) {
                'submitted', 'for_review' => 'For Review',
                'returned' => 'Returned',
                'printed' => $doc->signatures_confirmed_at ? 'Upload Signed Copy' : 'Ready to Print',
                'completed' => filled($doc->file_path) ? 'Signed Copy Uploaded' : 'Upload Signed Copy', // no file yet: an older form still waiting for its upload
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

        [$icon, $iconColor] = match (true) {
            $sentToHte, $doc?->status === 'approved' => ['ti-circle-check-filled', 'text-success'],
            $doc?->status === 'returned' => ['ti-arrow-back-up', 'text-danger'],
            $doc?->status === 'completed' && filled($doc->file_path) => ['ti-clock', 'text-warning'],
            $doc?->status === 'completed' => ['ti-printer', 'text-primary'],
            $doc?->status === 'printed' => ['ti-printer', 'text-primary'],
            in_array($doc?->status, ['submitted', 'for_review'], true) => ['ti-clock', 'text-warning'],
            $doc?->status === 'draft' => ['ti-pencil', 'text-primary'],
            default => ['ti-circle', 'text-secondary'],
        };

        return compact('label', 'icon', 'iconColor', 'sentToHte', 'structured');
    };

    $selectedRow = $checklist->first(fn ($row) => $row['requirement']->id === $selectedId);
    $countedForms = $checklist->reject(fn ($row) => $row['requirement']->code === $timeFrameCode);
    $doneCount = $countedForms->filter(fn ($row) => in_array($info($row)['label'], ['Approved', 'Sent to HTE'], true)
        // Form 19 is done once the internship is over and every week is approved.
        || ($row['requirement']->code === 'WEEKLY_REPORT' && str_starts_with($info($row)['label'], 'All ') && $internship?->hasCompletedDeployment()))->count();
@endphp

@if ($checklist->isEmpty())
    <div class="il-card text-center text-secondary py-5">No forms are available yet.</div>
@else
<div class="row g-3">
    {{-- Left: the forms, like the Document Review forms list --}}
    <div class="col-lg-4">
        <div class="il-card p-0 overflow-hidden" style="position: sticky; top: 1rem;">
            <div class="px-3 pt-3 pb-2 d-flex justify-content-between align-items-center">
                <span class="small fw-bold text-uppercase text-secondary" style="letter-spacing:.03em;">OJT Requirements</span>
                <span class="small text-secondary">{{ $doneCount }} / {{ $countedForms->count() }} done</span>
            </div>
            <div class="list-group list-group-flush il-form-list">
                @foreach ($stageLabels as $stageKey => $stageLabel)
                    @php $stageRows = $checklist->filter(fn ($row) => $row['requirement']->stage === $stageKey); @endphp
                    @continue ($stageRows->isEmpty())
                    <div class="list-group-item bg-light small fw-semibold text-secondary py-1">{{ $stageLabel }}</div>
                    @foreach ($stageRows as $row)
                        @php
                            $rowInfo = $info($row);
                            $isOpen = $row['requirement']->id === $selectedId;
                        @endphp
                        <a href="{{ route('student.documents', ['form' => $row['requirement']->id]) }}"
                           class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ $isOpen ? 'active-form' : '' }}"
                           @if ($isOpen) aria-current="true" @endif>
                            <i class="ti {{ $rowInfo['icon'] }} {{ $rowInfo['iconColor'] }} fs-5 flex-shrink-0"></i>
                            <span class="flex-grow-1" style="min-width: 0;">
                                <span class="d-block small fw-semibold" style="overflow-wrap: anywhere;">@if($row['requirement']->form_number && ! \Illuminate\Support\Str::startsWith($row['requirement']->name, 'Form '))Form {{ $row['requirement']->form_number }} &middot; @endif{{ $row['requirement']->name }}</span>
                                <span class="d-block {{ $rowInfo['label'] === 'Returned' ? 'text-danger' : 'text-secondary' }}" style="font-size: 0.72rem;">{{ $rowInfo['label'] }}</span>
                            </span>
                            <span class="small fw-semibold flex-shrink-0 {{ $isOpen ? 'text-secondary' : 'text-primary' }}">{{ $isOpen ? 'Open' : 'View' }}</span>
                        </a>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>

    {{-- Right: the open form --}}
    <div class="col-lg-8">
        @php
            $doc = $selectedRow['document'];
            $requirement = $selectedRow['requirement'];
            $rowInfo = $info($selectedRow);
            $structured = $rowInfo['structured'];
            $repeating = $requirement->is_repeating;
            $label = $rowInfo['label'];
            // Form 17 has no submission (an upload from the old flow is not shown).
            $hasFile = $doc && filled($doc->file_path) && $requirement->code !== $timeFrameCode;
            $previewable = $hasFile && preg_match('/\.(pdf|png|jpe?g|gif|webp)$/i', (string) ($doc->original_filename ?: $doc->file_path));
            $next = match (true) {
                $requirement->code === 'WEEKLY_REPORT' => 'Add a row for every training day. Each week is its own Form 19: submit it when the week is finished, your instructor checks it, then you print it, have it signed and upload the signed copy. Open the log to see your weeks, grouped by month.',
                $requirement->code === $timeFrameCode => 'Filled in from the hours you enter for each day in Time Frame (DTR). There is nothing to submit; your instructor sees your days for monitoring. View or download a copy whenever you need one.',
                $rowInfo['sentToHte'] => 'Delivered to your HTE\'s Evaluations Received. Nothing else to do on this form.',
                $doc?->status === 'approved' => 'Approved. Nothing else to do on this form.',
                $doc?->status === 'returned' => 'Your instructor returned this form. Correct it and submit again.',
                $doc?->status === 'completed' && $hasFile => 'Signed copy uploaded. Waiting for your instructor to approve it.',
                $doc?->status === 'completed' => 'Your instructor opened the upload. Upload a scan of the signed copy.',
                $doc?->status === 'printed' && $doc->signatures_confirmed_at => 'Your instructor confirmed the signatures. Upload a scan of the signed copy.',
                $doc?->status === 'printed' => 'Approved for printing. Download this form, print it and have it signed by the signatories, then bring the signed form to your instructor. Once they confirm the signatures, the upload of the signed copy opens here.',
                in_array($doc?->status, ['submitted', 'for_review'], true) => 'Submitted. Waiting for your instructor\'s review.',
                $doc?->status === 'draft' => 'Keep logging your entries, then submit them for review.',
                $repeating => 'Start your log for this form.',
                isset(\App\Models\DocumentAttachment::MULTI_UPLOAD[$requirement->code]) => 'The MOA is processed outside InternLink. Upload scans of the signed MOA (you can upload several files, and add more later).',
                $structured => 'Fill in this form online and submit it for review.',
                default => 'Upload this document for review.',
            };
        @endphp

        <div class="il-card mb-3">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                <div style="min-width: 0;">
                    <p class="small fw-bold text-uppercase text-secondary mb-1" style="letter-spacing: 0.03em;">{{ $stageLabels[$requirement->stage] ?? '' }}</p>
                    <h2 class="h5 fw-bold mb-1">@if($requirement->form_number && ! \Illuminate\Support\Str::startsWith($requirement->name, 'Form '))Form {{ $requirement->form_number }} &middot; @endif{{ $requirement->name }}</h2>
                </div>
                <span class="badge rounded-pill {{ $statusColor($label) }}">{{ $label }}</span>
            </div>

            <p class="small text-secondary mt-2 mb-3">{{ $next }}</p>

            @if ($requirement->code !== $timeFrameCode && $doc?->status === 'returned' && $doc->latestReview?->remarks)
                <div class="alert alert-danger py-2 small">
                    <strong><i class="ti ti-arrow-back-up"></i> Returned for correction:</strong> {{ $doc->latestReview->remarks }}
                </div>
            @endif

            {{-- What the student can do now --}}
            <div class="d-flex flex-wrap gap-2">
                @if ($repeating)
                    @if ($requirement->code === 'WEEKLY_REPORT')
                        {{-- Form 19 is printed and uploaded packet by packet on its log page --}}
                        <a href="{{ route('student.documents.log', $requirement) }}" class="btn btn-primary"><i class="ti ti-notebook"></i> View Log</a>
                    @elseif ($requirement->code === $timeFrameCode)
                        {{-- Form 17 is filled from the hours entered in Time Frame (DTR) - nothing to submit --}}
                        <a href="{{ route('student.dtr') }}" class="btn btn-primary"><i class="ti ti-clock"></i> Open Time Frame (DTR)</a>
                    @elseif ($doc?->status === 'printed' || ($doc?->status === 'completed' && ! $hasFile))
                        <a href="{{ route('student.documents.printable', ['document' => $doc, 'download' => 1]) }}" target="_blank" class="btn btn-primary"><i class="ti ti-download"></i> Download Form</a>
                        @if ($doc->signedUploadOpen())
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#signedUploadModal"
                            data-document-id="{{ $doc->id }}" data-requirement-name="{{ $requirement->name }}">
                            <i class="ti ti-upload"></i> Upload Signed Copy
                        </button>
                        @else
                        <span class="small text-secondary align-self-center"><i class="ti ti-lock"></i> Upload opens after your instructor checks the signatures.</span>
                        @endif
                    @elseif (! in_array($doc?->status, ['approved', 'completed'], true))
                        <a href="{{ route('student.documents.log', $requirement) }}" class="btn btn-primary"><i class="ti ti-notebook"></i> View Log</a>
                    @else
                        <a href="{{ route('student.documents.printable', ['document' => $doc, 'download' => 1]) }}" target="_blank" class="btn btn-outline-secondary"><i class="ti ti-download"></i> Download again</a>
                    @endif
                @elseif ($structured)
                    @if ($rowInfo['sentToHte'])
                        {{-- nothing to do --}}
                    @elseif ($doc?->status === 'printed' || ($doc?->status === 'completed' && ! $hasFile))
                        <a href="{{ route('student.documents.printable', ['document' => $doc, 'download' => 1]) }}" target="_blank" class="btn btn-primary"><i class="ti ti-download"></i> Download Form</a>
                        @if ($doc->signedUploadOpen())
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#signedUploadModal"
                            data-document-id="{{ $doc->id }}" data-requirement-name="{{ $requirement->name }}">
                            <i class="ti ti-upload"></i> Upload Signed Copy
                        </button>
                        @else
                        <span class="small text-secondary align-self-center"><i class="ti ti-lock"></i> Upload opens after your instructor checks the signatures.</span>
                        @endif
                    @elseif (in_array($doc?->status, ['completed', 'approved'], true))
                        <a href="{{ route('student.documents.printable', ['document' => $doc, 'download' => 1]) }}" target="_blank" class="btn btn-outline-secondary"><i class="ti ti-download"></i> Download again</a>
                    @else
                        <a href="{{ route('student.documents.fill', $requirement) }}" class="btn btn-primary">
                            <i class="ti ti-pencil"></i> {{ $doc ? 'Edit Form' : 'Fill Form' }}
                        </a>
                    @endif
                @elseif (isset(\App\Models\DocumentAttachment::MULTI_UPLOAD[$requirement->code]))
                    {{-- Form 10 MOA: scans of the signed MOA, several files and submissions allowed --}}
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#moaUploadModal"
                        data-action="{{ route('student.documents.moa', $requirement) }}">
                        <i class="ti ti-upload"></i> {{ $doc && $doc->attachments->isNotEmpty() ? 'Upload More Scans' : 'Upload Scanned MOA' }}
                    </button>
                @else
                    @if (! $doc || $doc->status === 'returned')
                        <button type="button" class="btn btn-primary"
                            data-bs-toggle="modal" data-bs-target="#uploadModal"
                            data-requirement-id="{{ $requirement->id }}" data-requirement-name="{{ $requirement->name }}">
                            <i class="ti ti-upload"></i> {{ $doc?->status === 'returned' ? 'Upload Corrected File' : 'Upload' }}
                        </button>
                    @endif
                @endif
            </div>
        </div>

        @if ($requirement->code === $timeFrameCode && $internship?->deployment)
            {{-- The same Form 17 card as the Time Frame (DTR) page --}}
            <div class="mb-3">
                @include('student._form17-card', [
                    'days' => \App\Models\Dtr::where('deployment_id', $internship->deployment->id)->orderByDesc('log_date')->get(),
                    'internship' => $internship,
                ])
            </div>
        @endif

        @unless ($requirement->code === $timeFrameCode)
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="il-card h-100">
                    <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">Details</p>
                    <dl class="row small mb-0">
                        <dt class="col-5 fw-normal text-secondary">Date submitted</dt>
                        <dd class="col-7">{{ $doc?->submitted_at?->format('M d, Y') ?? 'Not yet submitted' }}</dd>
                        <dt class="col-5 fw-normal text-secondary">Stage</dt>
                        <dd class="col-7">{{ ucfirst(str_replace('_', ' ', $requirement->stage)) }}</dd>
                        @if ($requirement->workflow_mode === 'print_sign')
                            <dt class="col-5 fw-normal text-secondary">Process</dt>
                            <dd class="col-7 mb-0">Fill online &rarr; instructor checks it &rarr; you download &amp; print it, have it signed &rarr; bring it to your instructor to confirm the signatures &rarr; upload the signed copy &rarr; approved</dd>
                        @endif
                    </dl>
                </div>
            </div>
            <div class="col-md-6">
                <div class="il-card h-100">
                    <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">Instructor feedback</p>
                    @php $feedback = $doc?->reviews?->filter(fn ($r) => filled($r->remarks)) ?? collect(); @endphp
                    @forelse ($feedback->take(3) as $review)
                        <div class="small {{ ! $loop->last ? 'border-bottom pb-2 mb-2' : '' }}">
                            <span class="fw-semibold {{ $review->decision === 'returned' ? 'text-danger' : 'text-success' }}">{{ $review->decision === 'returned' ? 'Returned' : 'Approved' }}</span>
                            <span class="text-secondary">&middot; {{ $review->reviewed_at?->format('M d, Y') }}</span>
                            <div>{{ $review->remarks }}</div>
                        </div>
                    @empty
                        <div class="small text-secondary">No feedback yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
        @endunless

        @php
            // The form as filled online, on the official printable page (once approved for printing).
            $hasFilledForm = $doc && $requirement->workflow_mode === 'print_sign' && $structured
                && in_array($doc->status, ['printed', 'completed', 'approved'], true);
        @endphp
        @if ($hasFile || $hasFilledForm || ($doc && $doc->attachments->isNotEmpty()))
            <div class="il-card mb-3">
                <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">Files</p>
                @if ($hasFilledForm)
                    <a href="{{ route('student.documents.printable', ['document' => $doc, 'view' => 1]) }}" target="_blank"
                       class="d-flex align-items-center gap-2 border rounded p-2 mb-2 text-decoration-none text-reset">
                        <i class="ti ti-file-text text-primary fs-5"></i>
                        <span class="flex-grow-1 min-w-0">
                            <span class="small fw-semibold d-block">{{ $requirement->name }}{{ $doc->packet_number ? ' – Week '.$doc->packet_number : '' }} (filled form)</span>
                            <span class="text-secondary" style="font-size: 0.75rem;">The printable form with what you filled in</span>
                        </span>
                        <i class="ti ti-external-link text-secondary"></i>
                    </a>
                @endif
                @if ($hasFile)
                    <a href="{{ $previewable ? route('student.documents.view', $doc) : route('student.documents.download', $doc) }}" target="_blank"
                       class="d-flex align-items-center gap-2 border rounded p-2 mb-2 text-decoration-none text-reset">
                        <i class="ti ti-file-type-pdf text-danger fs-5"></i>
                        <span class="flex-grow-1 small fw-semibold text-break">{{ $doc->original_filename ?: 'Uploaded file' }}{{ $structured ? ' (signed copy)' : '' }}</span>
                        <i class="ti ti-external-link text-secondary"></i>
                    </a>
                @endif
                @foreach ($doc?->attachments ?? [] as $attachment)
                    <a href="{{ route('student.documents.attachment', $attachment) }}" target="_blank"
                       class="d-flex align-items-center gap-2 border rounded p-2 mb-2 text-decoration-none text-reset">
                        <i class="ti ti-paperclip text-primary fs-5"></i>
                        <span class="flex-grow-1 min-w-0">
                            <span class="small fw-semibold d-block">{{ $attachment->label() }}</span>
                            <span class="text-secondary text-break" style="font-size: 0.75rem;">{{ $attachment->original_filename }}</span>
                        </span>
                        <i class="ti ti-external-link text-secondary"></i>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</div>
@endif

<style>
    .il-form-list .list-group-item { border-left: 3px solid transparent; }
    .il-form-list .active-form { background: rgba(28, 63, 102, 0.07); border-left-color: var(--il-navy, #1c3f66); }
</style>

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

{{-- Upload signed MOA scans (several files at once) --}}
<div class="modal fade" id="moaUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="moaUploadForm" action="" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold">Upload scanned MOA</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary">
                        Upload scans or photos of the signed Memorandum of Agreement. You can choose several files
                        (e.g. one per page) and upload more later; files already uploaded are kept.
                    </p>
                    <label class="form-label small fw-semibold" for="moa_files">Files (PDF, JPG, PNG — max 10MB each, up to 10 at a time)</label>
                    <input type="file" name="files[]" id="moa_files" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple required>
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
                        Upload a scan or photo of the printed form after all the signatories have signed it.
                        Your instructor checks it and approves it.
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

    document.getElementById('moaUploadModal').addEventListener('show.bs.modal', function (event) {
        document.getElementById('moaUploadForm').action = event.relatedTarget.getAttribute('data-action');
    });

    document.getElementById('signedUploadModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('signedUploadModalLabel').textContent = 'Upload signed copy — ' + button.getAttribute('data-requirement-name');
        document.getElementById('signedUploadForm').action = '/student/documents/' + button.getAttribute('data-document-id') + '/upload-signed';
    });
</script>
@endsection
