@extends('layouts.instructor')

@section('title', 'Bulk Enroll Students - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Bulk enroll students</h1>
    <a href="{{ route('instructor.advisees') }}" class="il-btn-secondary text-decoration-none">
        <i class="ti ti-arrow-left"></i> Back to my students
    </a>
</div>

<div class="il-card" style="max-width: 760px;">
    <p class="text-secondary small mb-4">
        Upload a CSV or Excel file to enroll several students at once. Every row is enrolled in
        <strong>your program, {{ $instructor->program->code }}</strong>, at {{ $instructor->campus->name }}.
    </p>

    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-4">
        <div class="small">Not sure of the format? Download the template, fill it in, then upload it below.</div>
        <a href="{{ route('instructor.interns.template') }}" class="btn btn-sm btn-outline-primary">Download template</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger small">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('instructor.interns.import.store') }}" enctype="multipart/form-data">
        @csrf
        <label for="file" class="form-label small fw-semibold">Spreadsheet file (.csv or .xlsx, max 5MB)</label>
        <input type="file" name="file" id="file" class="form-control mb-3" accept=".csv,.xlsx,.xls" required>

        <div class="small text-secondary mb-3">
            Required columns: <code>id_number</code>, <code>name</code>, <code>email</code>.
            Optional: <code>program_code</code> &mdash; if filled in, it must be {{ $instructor->program->code }}.
        </div>

        <button type="submit" class="il-btn-primary">Upload &amp; enroll</button>
    </form>
</div>
@endsection
