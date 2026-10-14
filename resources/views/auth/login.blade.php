@extends('layouts.auth')

@section('title', 'Sign In - PSU Intern-Link')

@section('content')
<div class="auth-card">

    <div class="auth-brand">
        <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
        <div>
            <h1>PSU Intern-Link</h1>
            <p>Pangasinan State University</p>
        </div>
    </div>

    <p class="auth-subtitle">Internship &amp; Management Monitoring System</p>

    @if (session('status'))
        <div class="auth-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-error">
            {{ $errors->first() }}
        </div>
    @endif

       <form method="POST" action="{{ route('login.attempt') }}">
        @csrf

        <label class="auth-label" for="campus_id">Select campus</label>
        <select class="auth-select" name="campus_id" id="campus_id" required>
            <option value="" disabled {{ old('campus_id') ? '' : 'selected' }}>--Select your campus--</option>
            @foreach ($campuses as $campus)
                <option value="{{ $campus->id }}" @selected(old('campus_id') == $campus->id)>
                    {{ $campus->name }}
                </option>
            @endforeach
        </select>

        <label class="auth-label">Select role</label>
        <div class="role-grid">
            @foreach ([
                'coordinator' => 'OJT Coordinator',
                'instructor' => 'Subject Instructor',
                'hte_representative' => 'HTE Supervisor',
                'student' => 'Student Intern',
            ] as $code => $label)
                <div class="role-option">
                    <input
                        type="radio"
                        name="role"
                        id="role_{{ $code }}"
                        value="{{ $code }}"
                        {{ old('role', 'coordinator') === $code ? 'checked' : '' }}
                    >
                    <label for="role_{{ $code }}">{{ $label }}</label>
                </div>
            @endforeach
        </div>

        <label class="auth-label" for="id_number">Employee/Student ID</label>
        <input
            class="auth-input"
            type="text"
            name="id_number"
            id="id_number"
            placeholder="e.g. 23-AC-0092"
            value="{{ old('id_number') }}"
            required
            autofocus
        >

        <label class="auth-label" for="password">Password</label>
        <input
            class="auth-input"
            type="password"
            name="password"
            id="password"
            placeholder="••••••••"
            required
        >
        <div class="auth-link-row">
            <a href="{{ route('password.forgot') }}" class="auth-link">Forgot password?</a>
        </div>

        <button type="submit" class="auth-submit mt-2">Sign in to InternWeb</button>
    </form>

</div>
@endsection
