@extends('layouts.auth')

@section('title', 'Change Password - PSU Intern-Link')
@section('body_class', 'login-body')

@push('styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}" rel="stylesheet">
@endpush

@php
    $user = auth()->user();
    $firstTime = (bool) $user->must_change_password;
    $lockIcon = '<svg class="login-input-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.8a4 4 0 0 1 8 0v2.7"/><circle cx="12" cy="15.5" r="1.3"/></svg>';
    $revealButton = fn (string $for) => '<button type="button" class="login-reveal" data-reveal="'.$for.'" aria-label="Show password" aria-pressed="false">'
        .'<svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>'
        .'<svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.1A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 0 1-3 3.7M6.3 7.6A16.4 16.4 0 0 0 2.5 12S6 18.5 12 18.5a9 9 0 0 0 4.2-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>'
        .'</button>';
@endphp

@section('content')
<main class="login-shell login-shell-single">
    <section class="login-panel" aria-labelledby="password-heading">
        <div class="login-panel-inner">

            <header class="login-brand">
                <img src="{{ asset('images/psu-logo.png') }}" alt="Pangasinan State University seal">
                <div>
                    <span class="login-brand-name">PSU Intern-Link</span>
                    <span class="login-brand-sub">Pangasinan State University</span>
                </div>
            </header>

            <div class="login-intro">
                @if ($firstTime)
                    <h1 id="password-heading">Set your password</h1>
                    <p>Welcome, <strong>{{ $user->name }}</strong>. Choose your own password before continuing.</p>
                @else
                    <h1 id="password-heading">Change password</h1>
                    <p>We'll email a 6-digit code to <strong>{{ \App\Support\PasswordCodeService::maskEmail($user->email) }}</strong> to confirm it's you.</p>
                @endif
            </div>

            @if ($errors->any())
                <div class="login-alert login-alert-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="login-form" id="password-form">
                @csrf
                @method('PUT')

                @unless ($firstTime)
                    <div class="login-field">
                        <label for="current_password">Current password</label>
                        <div class="login-input-wrap">
                            {!! $lockIcon !!}
                            <input type="password" name="current_password" id="current_password" placeholder="Enter your current password"
                                   autocomplete="current-password" required autofocus
                                   @class(['is-invalid' => $errors->has('current_password')])>
                            {!! $revealButton('current_password') !!}
                        </div>
                    </div>
                @endunless

                <div class="login-field">
                    <label for="password">New password</label>
                    <div class="login-input-wrap">
                        {!! $lockIcon !!}
                        <input type="password" name="password" id="password" placeholder="At least 8 characters"
                               autocomplete="new-password" minlength="8" required @if ($firstTime) autofocus @endif
                               @class(['is-invalid' => $errors->has('password')])>
                        {!! $revealButton('password') !!}
                    </div>
                </div>

                <div class="login-field">
                    <label for="password_confirmation">Confirm new password</label>
                    <div class="login-input-wrap">
                        {!! $lockIcon !!}
                        <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Type it again"
                               autocomplete="new-password" required>
                        {!! $revealButton('password_confirmation') !!}
                    </div>
                </div>

                <ul class="login-checklist" aria-live="polite">
                    <li data-check="length">At least 8 characters</li>
                    <li data-check="match">Both passwords match</li>
                </ul>

                <button type="submit" class="login-submit">
                    <span>{{ $firstTime ? 'Save password' : 'Send code' }}</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            @if ($firstTime)
                <p class="login-help">Keep it somewhere safe &mdash; you'll use it every time you sign in.</p>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="login-help">
                @csrf
                Not {{ \Illuminate\Support\Str::before($user->name, ' ') ?: $user->name }}?
                <button type="submit" class="login-link login-link-button">Sign out</button>
            </form>

            <footer class="login-foot">
                &copy; {{ now()->year }} Pangasinan State University &middot; PSU Intern-Link
            </footer>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-reveal]').forEach(function (toggle) {
        var input = document.getElementById(toggle.getAttribute('data-reveal'));
        toggle.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggle.classList.toggle('is-on', show);
        });
    });

    var pw = document.getElementById('password');
    var confirm = document.getElementById('password_confirmation');
    var length = document.querySelector('[data-check="length"]');
    var match = document.querySelector('[data-check="match"]');

    function check() {
        length.classList.toggle('is-ok', pw.value.length >= 8);
        match.classList.toggle('is-ok', pw.value.length > 0 && pw.value === confirm.value);
    }
    pw.addEventListener('input', check);
    confirm.addEventListener('input', check);
    check();

    document.getElementById('password-form').addEventListener('submit', function (e) {
        if (pw.value !== confirm.value) {
            e.preventDefault();
            match.classList.add('is-bad');
            confirm.focus();
            return;
        }
        var btn = this.querySelector('.login-submit');
        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.querySelector('span').textContent = 'Saving…';
    });
})();
</script>
@endpush
