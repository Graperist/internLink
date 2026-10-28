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
        Upload a CSV or Excel file to enroll several students at once.
        @if ($instructor->isCoordinator())
            Each row is enrolled in the program in its <code>program_code</code> column (any program of {{ $instructor->campus->name }}),
            or in the program you choose below when that column is blank.
        @else
            Every row is enrolled in <strong>your program, {{ $instructor->program->code }}</strong>, at {{ $instructor->campus->name }}.
        @endif
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
        <input type="file" name="file" id="file" class="form-control mb-3" accept=".csv,.xlsx" required>

        @if ($instructor->isCoordinator())
            <label for="program_id" class="form-label small fw-semibold">Program for rows without a program_code</label>
            <select name="program_id" id="program_id" class="form-select mb-3">
                <option value="">{{ $instructor->program ? $instructor->program->code.' (mine)' : 'None - every row needs a program_code' }}</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected((int) old('program_id') === $program->id)>{{ $program->code }} &ndash; {{ $program->name }}</option>
                @endforeach
            </select>
        @endif

        <div class="small text-secondary mb-3">
            Required columns: <code>id_number</code>, <code>name</code>, <code>email</code>.
            @if ($instructor->isCoordinator())
                Optional: <code>program_code</code> (e.g. BSIT, BSTM) &mdash; the program of that student.
            @else
                Optional: <code>program_code</code> &mdash; if filled in, it must be {{ $instructor->program->code }}.
            @endif
        </div>

        <button type="submit" class="il-btn-primary">Upload &amp; enroll</button>
    </form>
</div>
@endsection
