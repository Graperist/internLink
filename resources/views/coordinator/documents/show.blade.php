@extends('layouts.coordinator')

@section('title', 'Student Forms - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Student Forms</h1>
</div>

<div class="il-card mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <a href="{{ route('coordinator.documents') }}" class="small text-decoration-none">&larr; Back to Document Tracking</a>
            <h2 class="h6 fw-bold mt-2 mb-1">
                {{ $student->user->name }}
                <span class="text-secondary fw-normal">{{ $student->user->id_number }} &middot; {{ $student->program->code }}</span>
            </h2>
            <div class="text-secondary small">
                @if ($internship?->instructor?->user)
                    Subject Instructor: {{ $internship->instructor->user->name }}
                @else
                    No subject instructor assigned yet.
                @endif
                @if ($internship?->deployment?->hte)
                    &middot; Deployed at {{ $internship->deployment->hte->name }}
                @endif
            </div>
        </div>
    </div>
</div>

@if (! $internship)
    <div class="il-card text-center text-secondary py-4">
        This student has no internship record yet, so there are no forms to show.
    </div>
@else
    @php
        $stageLabels = [
            'pre_deployment' => 'Pre-Deployment Forms',
            'active' => 'Active Forms',
            'post_deployment' => 'Post-Deployment Forms',
        ];

        $badgeColor = fn ($status) => match ($status) {
            'approved' => 'text-bg-primary',
            'submitted', 'for_review' => 'text-bg-warning',
            'returned' => 'text-bg-danger',
            default => 'text-bg-light',
        };
        $label = fn ($status) => match ($status) {
            'for_review' => 'For Review',
            'submitted' => 'For Review',
            null => 'Missing',
            default => ucfirst($status),
        };
        $statusLabelStructured = fn ($status) => match ($status) {
            'submitted', 'for_review' => 'Filled Online - For Review',
            'returned' => 'Returned',
            'printed' => 'Printed - Out for Signing',
            'completed' => 'Completed - Upload Unlocked',
            'approved' => 'Approved',
            default => 'Not Yet Filled',
        };

        $formNumber = 1;
    @endphp

    @foreach ($stageLabels as $stageKey => $stageLabel)
        @continue ($requirementsByStage->get($stageKey, collect())->isEmpty())

        <div class="il-card mb-3">
            <p class="small fw-bold text-uppercase text-secondary mb-3" style="letter-spacing: 0.03em;">
                {{ $stageLabel }} ({{ $requirementsByStage->get($stageKey)->count() }} required)
            </p>

            <div class="row g-3">
                @foreach ($requirementsByStage->get($stageKey) as $requirement)
                    @php
                        $document = $documentsByRequirement->get($requirement->id);
                        $status = $document?->status;
                        $structured = $requirement->hasStructuredFields();
                    @endphp
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="fw-semibold small">
                                    Form {{ $formNumber++ }}: {{ $requirement->name }}
                                </div>
                                <span class="badge rounded-pill {{ $badgeColor($status) }}">
                                    {{ $structured ? $statusLabelStructured($status) : $label($status) }}
                                </span>
                            </div>

                            @if (! $document)
                                <div class="text-secondary" style="font-size: 0.78rem;">
                                    Not yet {{ $structured ? 'filled out' : 'submitted' }} by student.
                                </div>
                            @else
                                @if ($status === 'returned' && $document->latestReview?->remarks)
                                    <div class="text-danger mb-2" style="font-size: 0.78rem;">
                                        Returned: {{ $document->latestReview->remarks }}
                                    </div>
                                @endif

                                @if (! $structured && $document->original_filename)
                                    <div class="text-secondary mb-2" style="font-size: 0.78rem;">
                                        {{ $document->original_filename }}
                                        @if ($document->submitted_at)
                                            &middot; {{ $document->submitted_at->format('M d, Y') }}
                                        @endif
                                    </div>
                                @endif

                                <div class="d-flex gap-2 flex-wrap">
                                    @if ($structured)
                                        <a
                                            href="{{ route('coordinator.documents.printable', $document) }}"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-secondary"
                                        >
                                            <i class="ti ti-eye"></i> View Form
                                        </a>
                                        @if (in_array($status, ['completed', 'approved']) && $document->file_path)
                                            <a href="{{ route('coordinator.documents.view', $document) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="ti ti-eye"></i> View signed copy
                                            </a>
                                        @endif
                                    @else
                                        @if ($document->file_path)
                                            <a href="{{ route('coordinator.documents.view', $document) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="ti ti-eye"></i> View file
                                            </a>
                                        @else
                                            <span class="text-secondary" style="font-size: 0.78rem;">No file on record.</span>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endif
@endsection
