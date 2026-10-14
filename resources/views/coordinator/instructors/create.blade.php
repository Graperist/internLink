@extends('layouts.coordinator')

@section('title', 'Add Instructor - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Add instructor</h1>
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

    <form method="POST" action="{{ route('coordinator.instructors.store') }}">
        @csrf

        <div class="mb-3">
            <label class="il-form-label" for="id_number">Instructor ID number</label>
            <input type="text" class="form-control" id="id_number" name="id_number"
                   value="{{ old('id_number') }}" placeholder="e.g. ACC-INST-002" required>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="name">Full name</label>
            <input type="text" class="form-control" id="name" name="name"
                   value="{{ old('name') }}" placeholder="e.g. Mark Santos" required>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="{{ old('email') }}" placeholder="e.g. mark.santos@psu.edu.ph" required>
            <p class="il-form-hint">Used for account recovery, not for logging in - login uses the ID number above.</p>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="program_id">Assigned program</label>
            <select class="form-select" id="program_id" name="program_id" required>
                <option value="" disabled {{ old('program_id') ? '' : 'selected' }}>Select program</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(old('program_id') == $program->id)>
                        {{ $program->code }} - {{ $program->name }}
                    </option>
                @endforeach
            </select>
            <p class="il-form-hint">
                This instructor will see every student enrolled in this program automatically - more than one
                instructor can be assigned to the same program if needed. This field is required; there is no
                "assign later" option, since an instructor with no program can never see any students.
            </p>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">Create instructor account</button>
            <a href="{{ route('coordinator.instructors.index') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@endsection
