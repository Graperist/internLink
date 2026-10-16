@extends('layouts.instructor')

@section('title', 'Enroll Student - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Enroll student</h1>
    <a href="{{ route('instructor.advisees') }}" class="il-btn-secondary text-decoration-none">
        <i class="ti ti-arrow-left"></i> Back to my students
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

    <div class="alert alert-light border small mb-4">
        The student is enrolled in <strong>your program, {{ $instructor->program->code }}</strong>
        ({{ $instructor->program->name }}{{ $instructor->program->major ? ' - '.$instructor->program->major : '' }})
        at {{ $instructor->campus->name }}.
    </div>

    <form method="POST" action="{{ route('instructor.interns.store') }}">
        @csrf

        <div class="mb-3">
            <label class="il-form-label" for="id_number">Student ID number</label>
            <input type="text" class="form-control" id="id_number" name="id_number"
                   value="{{ old('id_number') }}" placeholder="e.g. 23-AC-0051" required>
            <p class="il-form-hint">This is what the student signs in with. The campus is read from it.</p>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="name">Full name</label>
            <input type="text" class="form-control" id="name" name="name"
                   value="{{ old('name') }}" placeholder="e.g. Juan Reyes" required>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="{{ old('email') }}" placeholder="e.g. juan.reyes@psu.edu.ph" required>
            <p class="il-form-hint">Used for account recovery, not for signing in.</p>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">Enroll student</button>
            <a href="{{ route('instructor.advisees') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@endsection
