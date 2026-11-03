@extends('layouts.instructor')

@section('title', 'Edit Student - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Edit student</h1>
    <a href="{{ route('instructor.advisees', ['academic_year' => $student->academic_year]) }}" class="il-btn-secondary text-decoration-none">
        <i class="ti ti-arrow-left"></i> Back to {{ $instructor->isCoordinator() ? 'students' : 'my students' }}
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

    <form method="POST" action="{{ route('instructor.interns.update', $student) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="il-form-label" for="academic_year">Academic year</label>
            @include('partials.academic-year-input', ['name' => 'academic_year', 'id' => 'academic_year', 'value' => old('academic_year', $student->academic_year), 'required' => true])
        </div>

        @if ($instructor->isCoordinator())
            <div class="mb-3">
                <label class="il-form-label" for="program_id">Program</label>
                <select class="form-select" id="program_id" name="program_id" @disabled(! $canMoveProgram)>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}" @selected((int) old('program_id', $student->program_id) === $program->id)>{{ $program->code }} &ndash; {{ $program->name }}{{ $program->major ? ' - '.$program->major : '' }}</option>
                    @endforeach
                </select>
                <p class="il-form-hint">
                    @if ($canMoveProgram)
                        Moving the student places them under that program's subject instructor, with its required hours.
                    @else
                        The student is already deployed, so they stay in {{ $student->program->code }}.
                    @endif
                </p>
            </div>
        @else
            <p class="small text-secondary mb-3"><i class="ti ti-school"></i> Program: <strong>{{ $student->program->code }}</strong></p>
        @endif

        <div class="mb-3">
            <label class="il-form-label" for="id_number">Student ID number</label>
            <input type="text" class="form-control" id="id_number" name="id_number" maxlength="50"
                   value="{{ old('id_number', $student->user->id_number) }}" required>
            <p class="il-form-hint">This is what the student signs in with.</p>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="name">Full name</label>
            <input type="text" class="form-control" id="name" name="name" maxlength="255"
                   value="{{ old('name', $student->user->name) }}" required>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email" maxlength="255"
                   value="{{ old('email', $student->user->email) }}" required>
            <p class="il-form-hint">Used for account recovery, not for signing in.</p>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">Save changes</button>
            <a href="{{ route('instructor.advisees', ['academic_year' => $student->academic_year]) }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@endsection
