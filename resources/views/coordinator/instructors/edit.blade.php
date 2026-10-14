@extends('layouts.coordinator')

@section('title', 'Reassign Instructor - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Reassign instructor</h1>
    <a href="{{ route('coordinator.instructors.index') }}" class="il-btn-secondary text-decoration-none">
        <i class="ti ti-arrow-left"></i> Back to instructors
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

    <p class="mb-3">
        <span class="fw-semibold">{{ $instructor->user->name }}</span>
        <span class="text-secondary">({{ $instructor->user->id_number }})</span>
        @if (! $instructor->program_id)
            <span class="badge rounded-pill text-bg-danger ms-2">Currently unassigned</span>
        @endif
    </p>

    <form method="POST" action="{{ route('coordinator.instructors.update', $instructor) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="il-form-label" for="program_id">Assigned program</label>
            <select class="form-select" id="program_id" name="program_id" required>
                <option value="" disabled {{ old('program_id', $instructor->program_id) ? '' : 'selected' }}>Select program</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(old('program_id', $instructor->program_id) == $program->id)>
                        {{ $program->code }} - {{ $program->name }}
                    </option>
                @endforeach
            </select>
            <p class="il-form-hint">
                Reassigning this instructor changes which students they see immediately -
                nothing needs to be moved on the student side.
            </p>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">Save changes</button>
            <a href="{{ route('coordinator.instructors.index') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@endsection
