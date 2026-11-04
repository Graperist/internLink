@extends('layouts.auth')

@section('title', 'New Password - PSU Intern-Link')

@section('content')
<div class="auth-card">

    <div class="auth-brand">
        <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
        <div>
            <h1>PSU Intern-Link</h1>
            <p>Pangasinan State University</p>
        </div>
    </div>

    <p class="auth-subtitle">Code verified. Choose your new password.</p>

    @if ($errors->any())
        <div class="auth-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.forgot.reset.save') }}">
        @csrf

        <label class="auth-label" for="password">New password</label>
        <input class="auth-input" type="password" name="password" id="password"
               placeholder="8+ characters: Aa, 0-9 and a symbol" required autofocus>
        <div class="small text-secondary mb-2">At least 8 characters with a capital letter, a small letter, a number and a special character (e.g. ! @ # $ %).</div>

        <label class="auth-label" for="password_confirmation">Confirm new password</label>
        <input class="auth-input" type="password" name="password_confirmation" id="password_confirmation" required>

        <button type="submit" class="auth-submit mt-2">Save new password</button>
    </form>

</div>
@endsection
