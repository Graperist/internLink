@extends('layouts.student')

@section('title', 'My Profile - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">My profile</h1>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="il-card">
            <h2 class="h6 fw-bold mb-3">My Account</h2>

            <form method="POST" action="{{ route('student.profile.account') }}">
                @csrf
                @method('PUT')

                <label class="form-label small fw-semibold">Full name</label>
                <input
                    type="text"
                    name="name"
                    class="form-control mb-3"
                    value="{{ old('name', $student->user->name) }}"
                    required
                >

                <label class="form-label small fw-semibold">Email</label>
                <input
                    type="email"
                    name="email"
                    class="form-control mb-3"
                    value="{{ old('email', $student->user->email) }}"
                    required
                >

                <label class="form-label small fw-semibold">ID number</label>
                <input type="text" class="form-control mb-3" value="{{ $student->user->id_number }}" disabled>

                <label class="form-label small fw-semibold">Program</label>
                <input type="text" class="form-control mb-2" value="{{ $student->program->name ?? '—' }}" disabled title="{{ $student->program->name ?? '—' }}">
                <p class="small text-secondary mb-4">
                    Your ID number, program, campus, and assigned instructor are set by your OJT Coordinator and can't be changed here.
                </p>

                <button type="submit" class="btn btn-primary">Save account details</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="mb-3">
            @include('partials.profile-photo-card', ['prefix' => 'student'])
        </div>

        <div class="il-card">
            <h2 class="h6 fw-bold mb-2">Password</h2>
            <p class="small text-secondary mb-3">
                Change the password you use to sign in.
            </p>
            <a href="{{ route('password.change') }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-lock"></i> Change password
            </a>
        </div>
    </div>
</div>
@endsection
