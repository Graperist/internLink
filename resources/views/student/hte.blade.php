@extends('layouts.student')

@section('title', 'My HTE Info - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">My HTE info</h1>
</div>

@if (! $deployment)
    <div class="il-card">
        <p class="text-secondary mb-0">
            You haven't been assigned a Host Training Establishment yet. Your OJT Coordinator
            will assign one from the Deployment screen.
        </p>
    </div>
@else
    <div class="il-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold mb-0">My Host Training Establishment</h2>
            <span class="badge rounded-pill text-bg-primary">{{ ucfirst($deployment->status) }}</span>
        </div>

        <div class="p-3 mb-3" style="background: #f4f6f9; border-radius: 10px;">
            <div class="fw-bold mb-1">{{ $deployment->hte->name }}</div>
            <div class="text-secondary small mb-3">{{ $deployment->hte->address ?? 'No address on file' }}</div>

            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.68rem;">Deployment date</div>
                    <div>{{ $deployment->start_date?->format('F d, Y') ?? '—' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.68rem;">Contact person</div>
                    <div>{{ $deployment->hte->contact_person ?? '—' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.68rem;">Contact</div>
                    <div>{{ $deployment->hte->contact_phone ?? $deployment->hte->contact_email ?? '—' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.68rem;">MOA status</div>
                    <div>{{ ucfirst($deployment->hte->moa_status) }}</div>
                </div>
            </div>
        </div>

        <p class="text-secondary small mb-0">
            Your OJT schedule is arranged directly with your HTE Supervisor and isn't tracked
            in the system yet — check with them for exact hours.
        </p>
    </div>
@endif

@if ($deployment)
    @php
        $likert = [5 => 'Strongly Agree', 4 => 'Agree', 3 => 'Neither Agree nor Disagree', 2 => 'Disagree', 1 => 'Strongly Disagree'];
        $ratingFields = $feedback?->requirement->fieldDefinitions->where('field_type', 'rating_scale') ?? collect();
    @endphp
    <div class="il-card mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h2 class="h6 fw-bold mb-0">Training Supervisor&rsquo;s Feedback <span class="text-secondary fw-normal">(Form 21)</span></h2>
            @if ($feedback)
                <span class="badge rounded-pill text-bg-success">Received</span>
            @else
                <span class="badge rounded-pill text-bg-secondary">Not yet received</span>
            @endif
        </div>

        @if (! $feedback)
            <p class="text-secondary small mb-0">
                After your internship is completed, your host training establishment submits its feedback about you
                here. You&rsquo;ll be notified when it arrives.
            </p>
        @else
            <p class="text-secondary small mb-3">
                Submitted by {{ $feedback->fieldValue('supervisor_name') ?: ($feedback->uploader?->name ?? 'your training supervisor') }}
                on {{ ($feedback->submitted_at ?? $feedback->created_at)->format('F d, Y') }}.
            </p>

            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                            <th>Criteria</th>
                            <th class="text-end">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ratingFields as $field)
                            @php $rating = (int) $feedback->fieldValue($field->field_key); @endphp
                            <tr>
                                <td>{{ $field->label }}</td>
                                <td class="text-end text-nowrap">{{ $rating }} &ndash; {{ $likert[$rating] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (filled($feedback->fieldValue('other_comments')))
                <div class="p-3 mb-3" style="background: #f4f6f9; border-radius: 10px;">
                    <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.68rem;">Other comments and suggestions</div>
                    <div style="white-space: pre-line;">{{ $feedback->fieldValue('other_comments') }}</div>
                </div>
            @endif

            <a href="{{ route('student.hte.feedback', $feedback) }}" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-eye"></i> View official form
            </a>
            <a href="{{ route('student.hte.feedback', ['document' => $feedback, 'download' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-download"></i> Download
            </a>
        @endif
    </div>
@endif
@endsection
