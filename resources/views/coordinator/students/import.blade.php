@extends('layouts.coordinator')

@section('title', 'Import Interns - PSU Intern-Link')
@section('page_title', 'Import Interns')

@section('content')
<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Bulk Import Interns</h2>
        <a href="{{ route('coordinator.students.index') }}" class="btn btn-sm btn-outline-secondary">
            &larr; Back to Intern List
        </a>
    </div>

    <p class="text-muted small mb-4">
        Upload a CSV or Excel file to provision multiple Student Intern accounts at once.
        Every row is added to <strong>{{ auth()->user()->campus->name }}</strong> only —
        you cannot import interns into another campus.
    </p>

    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-4">
        <div class="small">
            Not sure of the format? Download the template, fill it in, then upload it below.
        </div>
        <a href="{{ route('coordinator.students.import.template') }}" class="btn btn-sm btn-outline-primary">
            Download Template
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger small">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('coordinator.students.import.store') }}" enctype="multipart/form-data">
        @csrf
        <label for="file" class="form-label small fw-semibold">Spreadsheet file (.csv or .xlsx, max 5MB)</label>
        <input type="file" name="file" id="file" class="form-control mb-3" accept=".csv,.xlsx,.xls" required>

        <div class="small text-muted mb-3">
            Required columns: <code>id_number</code>, <code>name</code>, <code>email</code>, <code>program_code</code>.
            Optional: <code>instructor_email</code> to auto-assign an advising instructor.
        </div>

        <button type="submit" class="btn btn-primary">Upload &amp; Provision Accounts</button>
    </form>
</div>
@endsection
