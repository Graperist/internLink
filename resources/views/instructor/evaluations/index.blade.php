@extends('layouts.instructor')

@section('title', 'Evaluations - PSU Intern-Link')

@section('content')
<style>
    /* Row opened from a notification ("...#student-12") is highlighted. */
    .eval-row:target > td { background: #fff7d6; }
    .eval-row { scroll-margin-top: 90px; }
</style>
<div class="il-page-header">
    <h1 class="il-page-title">Evaluations</h1>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $submittedStatuses = ['submitted', 'for_review', 'approved', 'printed', 'completed'];

    // One pass over the advisees; both tables below read from it.
    $rows = $advisees->map(function ($student) use ($progress, $partnerAgencyFormRequirement, $form13Requirement, $submittedStatuses) {
        $internship = $student->currentInternship;
        $deployment = $internship?->deployment;
        $pick = fn ($requirement) => $requirement
            ? $internship?->documents->firstWhere('document_requirement_id', $requirement->id)
            : null;
        $form13 = $pick($form13Requirement);
        $form23a = $pick($partnerAgencyFormRequirement);

        return (object) [
            'student' => $student,
            'deployment' => $deployment,
            'state' => $progress[$student->id],
            'form13' => ($form13 && in_array($form13->status, $submittedStatuses, true)) ? $form13 : null,
            'form23a' => ($form23a && in_array($form23a->status, $submittedStatuses, true)) ? $form23a : null,
        ];
    });
@endphp

{{-- ============ RECEIVED: Form 13, sent to the instructor by each student's HTE ============ --}}
<div class="il-card mb-4" id="evaluations-received">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
        <h2 class="h6 fw-bold mb-0">Evaluations Received <span class="text-secondary fw-normal">(from HTE &mdash; Form 13)</span></h2>
        <span class="badge rounded-pill text-bg-light">{{ $rows->filter(fn ($r) => $r->form13)->count() }} of {{ $rows->count() }} received</span>
    </div>
    <p class="text-secondary small mb-3">
        When a host training establishment finishes its Internship Evaluation Form for one of your advisees, it
        arrives here. Use <strong>View</strong> to read it, or <strong>Download</strong> to save it as a PDF.
    </p>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                    <th>Student</th>
                    <th>HTE</th>
                    <th>Status</th>
                    <th>Date received</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr id="student-{{ $row->student->id }}" class="eval-row">
                        <td>
                            <div class="fw-semibold">{{ $row->student->user->name }}</div>
                            <div class="text-secondary" style="font-size: 0.78rem;">
                                {{ $row->student->program->code ?? '' }} &middot; {{ $row->student->user->id_number }}
                            </div>
                        </td>
                        <td>{{ $row->deployment?->hte?->name ?? '—' }}</td>
                        <td>
                            @if ($row->form13)
                                <span class="badge rounded-pill text-bg-success">Received</span>
                            @else
                                <span class="badge rounded-pill text-bg-secondary">Not yet received</span>
                            @endif
                        </td>
                        <td>{{ $row->form13 ? ($row->form13->submitted_at ?? $row->form13->created_at)->format('M d, Y') : '—' }}</td>
                        <td class="text-end text-nowrap">
                            @if ($row->form13)
                                <a href="{{ route('instructor.evaluations.show', $row->form13) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="ti ti-eye"></i> View
                                </a>
                                <a href="{{ route('instructor.evaluations.show', ['document' => $row->form13, 'download' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-download"></i> Download
                                </a>
                            @else
                                <span class="text-secondary small">Awaiting HTE</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">No deployed advisees yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ============ SENT: Form 23a, the instructor's evaluation of the HTE ============ --}}
<div class="il-card" id="evaluations-sent">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
        <h2 class="h6 fw-bold mb-0">Evaluations Sent <span class="text-secondary fw-normal">(to HTE &mdash; Form 23a)</span></h2>
        <span class="badge rounded-pill text-bg-light">{{ $rows->filter(fn ($r) => $r->form23a)->count() }} of {{ $rows->count() }} sent</span>
    </div>
    <p class="text-secondary small mb-3">
        Once a student&rsquo;s internship is completed, use <strong>Evaluate</strong> to fill in Form 23a, the
        Evaluation Instrument of PSU Partner Agencies. It rates the student&rsquo;s host training establishment on
        communication, ethical dealings, student satisfaction and quality delivery, and is delivered to the HTE&rsquo;s
        Evaluations Received. Intern-Link does not record grades.
    </p>
    @unless ($partnerAgencyFormRequirement)
        <div class="text-secondary small mb-3">Official Form 23a has not been configured for this campus.</div>
    @endunless

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                    <th>Student</th>
                    <th>HTE</th>
                    <th>Status</th>
                    <th>Overall progress</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $state = $row->state; @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $row->student->user->name }}</div>
                            <div class="text-secondary" style="font-size: 0.78rem;">
                                {{ $row->student->program->code ?? '' }} &middot; {{ $row->student->user->id_number }}
                            </div>
                        </td>
                        <td>{{ $row->deployment?->hte?->name ?? '—' }}</td>
                        <td>
                            @if ($row->form23a)
                                <span class="badge rounded-pill text-bg-success">Sent</span>
                                <div class="text-secondary" style="font-size: 0.72rem;">{{ ($row->form23a->submitted_at ?? $row->form23a->created_at)->format('M d, Y') }}</div>
                            @elseif ($row->deployment?->status === 'completed')
                                <span class="badge rounded-pill text-bg-warning">Not yet sent</span>
                            @else
                                <span class="badge rounded-pill text-bg-secondary">Opens after internship</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge rounded-pill {{ $state['color'] }}">{{ $state['label'] }}</span>
                            @if ($state['key'] === 'ongoing')
                                <div class="text-secondary" style="font-size: 0.72rem;">Evaluation opens once the internship is completed</div>
                            @elseif ($state['detail'])
                                <div class="text-secondary" style="font-size: 0.72rem;">{{ $state['detail'] }}</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($row->form23a)
                                <a href="{{ route('instructor.documents.printable', $row->form23a) }}?preview=1" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-eye"></i> View Form 23a
                                </a>
                            @elseif ($partnerAgencyFormRequirement && $row->deployment && $row->deployment->status === 'completed')
                                <a href="{{ route('instructor.forms.create', [$partnerAgencyFormRequirement, $row->deployment]) }}" class="btn btn-sm btn-outline-primary">
                                    Evaluate
                                </a>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                        title="Available after the student's internship is completed">
                                    Evaluate
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">No deployed advisees yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
