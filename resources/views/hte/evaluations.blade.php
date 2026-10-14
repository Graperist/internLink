@extends('layouts.hte')

@section('title', 'Submit Evaluation - PSU Intern-Link')
@section('page_title', 'Submit Evaluation')

@section('content')
@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="alert alert-info small d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="ti ti-inbox"></i> Evaluations of your agency from the student-intern (Forms 20, 23c) and the Subject Instructor (Form 23a) are available to read and download.</span>
    <a href="{{ route('hte.evaluations.received') }}" class="btn btn-sm btn-outline-primary">View Evaluations Received</a>
</div>

<div class="il-card mb-4">
    <div class="hte-section-heading">Official PSU OJT Forms</div>
    <p class="text-secondary small mb-3">Complete the official post-OJT forms for each student. Each form is delivered to its recipient: Form 13 to the Subject Instructor (Evaluations), Form 21 to the student (My HTE Info), and Form 23b to the Campus Coordinator (Documents).</p>

    @forelse ($deployments->where('status', 'completed') as $deployment)
        @php
            $student = $deployment->internship->student;
            $studentDocs = $deployment->internship->documents->keyBy('document_requirement_id');
        @endphp
        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <div>
                    <div class="fw-semibold">{{ $student->user->name }}</div>
                    <div class="text-secondary small">{{ $student->program->code ?? '' }} · {{ $student->user->id_number }}</div>
                </div>
                <span class="badge rounded-pill text-bg-light">{{ $deployment->status === 'completed' ? 'Completed' : 'Active OJT' }}</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach (['EVALUATION_FORM' => 'Form 13 – Internship Evaluation', 'TRAINING_SUPERVISOR_FEEDBACK' => 'Form 21 – Supervisor Feedback', 'PARTNER_AGENCY_EVAL_SELF' => 'Form 23b – HTE Self-Ratee'] as $code => $label)
                    @php $requirement = $officialFormRequirements->get($code); $doc = $requirement ? $studentDocs->get($requirement->id) : null; @endphp
                    @if ($requirement)
                        @if (in_array($doc?->status, ['submitted', 'for_review', 'printed', 'completed'], true))
                            <span class="btn btn-sm btn-outline-primary disabled">{{ $label }} · Submitted</span>
                        @elseif ($doc?->status === 'approved')
                            <span class="btn btn-sm btn-outline-success disabled">{{ $label }} · Approved</span>
                        @else
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('hte.forms.create', [$requirement, $deployment]) }}">{{ $doc ? 'Edit ' : 'Fill ' }}{{ $label }}</a>
                        @endif
                    @endif
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-secondary small py-2">No completed deployments are available for the official post-OJT forms yet.</div>
    @endforelse
</div>

<div class="alert alert-secondary small mt-3 mb-0">
    Form 13 and other applicable HTE evaluations are completed through the official PSU forms above. Numeric score entry is disabled so the system does not create a second evaluation record or calculate grades.
</div>
@endsection



