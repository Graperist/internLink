@extends('layouts.coordinator')

@section('title', ($program->exists ? 'Edit Program' : 'Add Program').' - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">{{ $program->exists ? 'Edit '.$program->code : 'Add program' }}</h1>
    <a href="{{ route('coordinator.programs.index') }}" class="il-btn-secondary text-decoration-none">
        <i class="ti ti-arrow-left"></i> Back to programs
    </a>
</div>

<div class="il-card" style="max-width: 640px;">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $program->exists ? route('coordinator.programs.update', $program) : route('coordinator.programs.store') }}">
        @csrf
        @if ($program->exists) @method('PUT') @endif

        <div class="row">
            <div class="col-md-5 mb-3">
                <label class="il-form-label" for="code">Program code</label>
                <input type="text" class="form-control text-uppercase" id="code" name="code"
                       value="{{ old('code', $program->code) }}" placeholder="e.g. BSBA-MM" required>
                <p class="il-form-hint">Short code shown in lists. Use one code per course + major.</p>
            </div>
            <div class="col-md-7 mb-3">
                <label class="il-form-label" for="required_hours">Required OJT hours</label>
                <input type="number" min="1" max="2000" class="form-control" id="required_hours" name="required_hours"
                       value="{{ old('required_hours', $program->required_hours) }}" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="name">Course</label>
            <input type="text" class="form-control" id="name" name="name"
                   value="{{ old('name', $program->exists ? $program->courseTitle() : '') }}"
                   placeholder="e.g. Bachelor of Science in Business Administration" required>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="major">Major <span class="text-secondary fw-normal">(optional)</span></label>
            <input type="text" class="form-control" id="major" name="major"
                   value="{{ old('major', $program->exists && $program->majorLabel() !== 'N/A' ? $program->majorLabel() : '') }}"
                   placeholder="e.g. Marketing Management">
            <p class="il-form-hint">Leave blank if the course has no major. For a second major of the same course, add another program with its own code.</p>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">{{ $program->exists ? 'Save changes' : 'Add program' }}</button>
            <a href="{{ route('coordinator.programs.index') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@endsection
