@extends('layouts.auth')

@section('title', 'Change Password - PSU Intern-Link')

@section('content')
<div class="auth-card">

    <div class="auth-brand">
        <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
        <div>
            <h1>PSU Intern-Link</h1>
            <p>Pangasinan State University</p>
        </div>
    </div>

    @if (auth()->user()->must_change_password)
        <p class="auth-subtitle">
            Welcome, {{ auth()->user()->name }}. For security, set your own password before continuing.NOTE: Do not forget your password!!!
        </p>
    @else
        <p class="auth-subtitle">
            Change your password. For your security, we'll email a 6-digit code to
            <span style="color:var(--il-gold);">{{ \App\Support\PasswordCodeService::maskEmail(auth()->user()->email) }}</span>
            to confirm it's you.
        </p>
    @endif

    @if ($errors->any())
        <div class="auth-error">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        @method('PUT')

        @unless (auth()->user()->must_change_password)
            <label class="auth-label" for="current_password">Current password</label>
            <input class="auth-input" type="password" name="current_password" id="current_password" required>
        @endunless

        <label class="auth-label" for="password">New password</label>
        <input class="auth-input" type="password" name="password" id="password" placeholder="At least 8 characters" required>

        <label class="auth-label" for="password_confirmation">Confirm new password</label>
        <input class="auth-input" type="password" name="password_confirmation" id="password_confirmation" required>

        <button type="submit" class="auth-submit mt-2">{{ auth()->user()->must_change_password ? 'Save password' : 'Send code' }}</button>
    </form>

</div>
@endsection
