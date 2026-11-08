@extends('layouts.coordinator')

@section('title', 'Edit Instructor - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Edit instructor</h1>
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

    <p class="mb-3 text-secondary small">
        <i class="ti ti-school"></i>
        Subject Instructor &middot; {{ $instructor->program?->code ?? 'Unassigned' }}
        &middot; use <a href="{{ route('coordinator.instructors.edit', $instructor) }}">Reassign</a> to change the program.
    </p>

    <form method="POST" action="{{ route('coordinator.instructors.details.update', $instructor) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="il-form-label" for="id_number">ID number</label>
            <input type="text" class="form-control" id="id_number" name="id_number" value="{{ old('id_number', $instructor->user->id_number) }}" required maxlength="100">
            <p class="il-form-hint">They sign in with this ID number.</p>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="name">Full name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $instructor->user->name) }}" required maxlength="255">
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $instructor->user->email) }}" required maxlength="255">
            <p class="il-form-hint">It can't be an email another account already uses.</p>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">Save changes</button>
            <a href="{{ route('coordinator.instructors.index') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@endsection
