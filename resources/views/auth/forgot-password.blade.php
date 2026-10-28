@extends('layouts.auth')

@section('title', 'Forgot Password - PSU Intern-Link')

@section('content')
<div class="auth-card">

    <div class="auth-brand">
        <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
        <div>
            <h1>PSU Intern-Link</h1>
            <p>Pangasinan State University</p>
        </div>
    </div>

    <p class="auth-subtitle">Enter your Student or Employee ID. We'll email a 6-digit code to the address saved on your profile.</p>

    @if ($errors->any())
        <div class="auth-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.forgot.send') }}">
        @csrf

        <label class="auth-label" for="id_number">Employee/Student ID</label>
        <input class="auth-input" type="text" name="id_number" id="id_number"
               placeholder="e.g. 23-AC-0092" value="{{ old('id_number') }}" required autofocus>

        <button type="submit" class="auth-submit mt-2">Send code</button>
    </form>

    <div class="auth-link-row mt-3" style="text-align:center;">
        <a href="{{ route('login') }}" class="auth-link">Back to sign in</a>
    </div>

</div>
@endsection
