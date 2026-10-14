@extends('layouts.coordinator')

@section('title', 'Upload File - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Upload file</h1>
</div>

<div class="il-card" style="max-width: 560px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Add to Digital Repository</h2>
        <a href="{{ route('coordinator.repository') }}" class="btn btn-sm btn-outline-secondary">
            &larr; Back
        </a>
    </div>

    <p class="text-muted small mb-4">
        Use this to log a document on the student's behalf — for example, a physical
        submission you're recording manually. This is not a substitute for the student's
        own upload flow once that exists.
    </p>

    @if ($errors->any())
        <div class="alert alert-danger small">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('coordinator.repository.store') }}" enctype="multipart/form-data">
        @csrf

        <label class="form-label small fw-semibold">Student</label>
        <select name="student_id" class="form-select mb-3" required>
            <option value="">-- Select student --</option>
            @foreach ($students as $student)
                <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                    {{ $student->user->name }} ({{ $student->user->id_number }})
                </option>
            @endforeach
        </select>

        <label class="form-label small fw-semibold">Document type</label>
        <select name="document_requirement_id" class="form-select mb-3" required>
            <option value="">-- Select document type --</option>
            @foreach ($requirements as $requirement)
                <option value="{{ $requirement->id }}" @selected(old('document_requirement_id') == $requirement->id)>
                    {{ $requirement->name }}
                </option>
            @endforeach
        </select>

        <label class="form-label small fw-semibold">File (PDF, DOC, DOCX, JPG, PNG — max 10MB)</label>
        <input type="file" name="file" class="form-control mb-4" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>

        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
</div>
@endsection
