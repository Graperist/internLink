@extends('layouts.hte')

@section('title', 'Evaluations Received - PSU Intern-Link')
@section('page_title', 'Evaluations Received')

@section('content')
@php
    $senderRole = ['STUDENT_FEEDBACK_FORM' => 'Student-Intern', 'STUDENT_HTE_EVALUATION' => 'Student-Intern', 'PARTNER_AGENCY_EVAL_INSTRUCTOR' => 'Subject Instructor'];
    $shortTitle = [
        'STUDENT_FEEDBACK_FORM' => "Student-Intern's Feedback",
        'PARTNER_AGENCY_EVAL_INSTRUCTOR' => 'Partner Agency Evaluation (Instructor)',
        'STUDENT_HTE_EVALUATION' => 'Partner Agency Evaluation (Student)',
    ];
@endphp
<div class="il-card">
    <div class="hte-section-heading">Evaluations of your agency</div>
    <p class="text-secondary small mb-3">
        Evaluations written about your host training establishment are delivered here once they are submitted:
        the student-intern&rsquo;s feedback (Form 20), the student&rsquo;s evaluation of your agency (Form 23c),
        and the Subject Instructor&rsquo;s evaluation of your agency (Form 23a). This page is view-only.
    </p>

    @if ($requirements->isEmpty())
        <p class="text-secondary small mb-0">The evaluation forms have not been configured for your campus yet.</p>
    @else
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('hte.evaluations.received') }}"
               class="btn btn-sm {{ $filter ? 'btn-outline-secondary' : 'btn-primary' }}">
                All <span class="badge text-bg-light ms-1">{{ $counts->sum() }}</span>
            </a>
            @foreach ($requirements as $code => $requirement)
                <a href="{{ route('hte.evaluations.received', ['form' => $code]) }}"
                   class="btn btn-sm {{ $filter === $code ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Form {{ $requirement->form_number }} &middot; {{ $shortTitle[$code] ?? $requirement->name }}
                    <span class="badge text-bg-light ms-1">{{ $counts->get($code, 0) }}</span>
                </a>
            @endforeach
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                        <th>Student</th>
                        <th>Form</th>
                        <th>Evaluated by</th>
                        <th>Submitted</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $student = $document->internship->student;
                            $code = $document->requirement->code;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $student->user->name }}</div>
                                <div class="text-secondary" style="font-size: 0.78rem;">
                                    {{ $student->program->code ?? '' }} &middot; {{ $student->user->id_number }}
                                </div>
                            </td>
                            <td>
                                <span class="badge rounded-pill text-bg-primary">Form {{ $document->requirement->form_number }}</span>
                                <div class="text-secondary" style="font-size: 0.78rem;">{{ $shortTitle[$code] ?? $document->requirement->name }}</div>
                            </td>
                            <td>
                                {{ $document->uploader?->name ?? '—' }}
                                <div class="text-secondary" style="font-size: 0.78rem;">{{ $senderRole[$code] ?? '' }}</div>
                            </td>
                            <td>{{ ($document->submitted_at ?? $document->created_at)->format('M d, Y') }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('hte.evaluations.received.show', $document) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="ti ti-eye"></i> View
                                </a>
                                <a href="{{ route('hte.evaluations.received.show', ['document' => $document, 'download' => 1]) }}"
                                   target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-download"></i> Download
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-4">
                                No evaluations received yet. You&rsquo;ll be notified when a student or the Subject
                                Instructor submits one for your agency.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-secondary small mt-3 mb-0">
            <strong>Download</strong> opens the form ready to save &mdash; choose <em>Save as PDF</em> as the
            destination to keep a copy.
        </p>
    @endif
</div>
@endsection
