@extends('layouts.auth')

@section('title', 'Sign In - PSU Intern-Link')
@section('body_class', 'login-body')

@push('styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/login-split.css') }}?v={{ filemtime(public_path('css/login-split.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    // The login photos, managed by the Super Admin (built-in campus photos until any are uploaded).
    $slides = \App\Models\LoginSlide::forLoginPage();
@endphp
@if (! empty($preview))
    {{-- Coordinator's preview of the sign-in page (Settings > Login Page Photos). --}}
    <div style="position:fixed;top:0;left:0;right:0;z-index:50;background:#0b2f86;color:#fff;font:600 13px/1.3 system-ui,sans-serif;padding:8px 14px;text-align:center;">
        Preview of the sign-in page. Signing in is turned off here.
        <a href="{{ route('admin.login-photos') }}" style="color:#ffc928;margin-left:10px;">Back to Login Page Photos</a>
    </div>
@endif
<main class="lx">

    {{-- ===== Background: campus photos, white side, blue and gold sweeps ===== --}}
    <div class="lx-photos" aria-hidden="true">
        @foreach ($slides as $i => $slide)
            <div class="lx-hero-photo {{ $i === 0 ? 'is-active' : '' }}" data-campus="{{ $slide['campus'] }}"
                 style="background-image: url('{{ $slide['src'] }}');"></div>
        @endforeach
    </div>

    <svg class="lx-white" viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-hidden="true">
        <path d="M330 0 C 210 230, 260 520, 120 1000 L1000 1000 L1000 0 Z" fill="#ffffff"/>
        <path d="M330 0 C 210 230, 260 520, 120 1000" fill="none" stroke="#f5b800" stroke-width="10"/>
    </svg>

    <svg class="lx-corner" viewBox="0 0 600 300" preserveAspectRatio="none" aria-hidden="true">
        <path d="M60 0 C 120 150, 330 230, 600 200 L600 0 Z" fill="#f5b800"/>
        <path d="M110 0 C 170 120, 360 190, 600 165 L600 0 Z" fill="#1546b8"/>
    </svg>
    <img class="lx-seal" src="{{ asset('images/psu-logo.png') }}" alt="" aria-hidden="true">

    <svg class="lx-sweep" viewBox="0 0 1000 600" preserveAspectRatio="none" aria-hidden="true">
        <defs>
            <linearGradient id="lxBlue" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#1a55c9"/>
                <stop offset="1" stop-color="#0b2f86"/>
            </linearGradient>
        </defs>
        <path d="M0 175 C 260 70, 520 70, 720 190 C 840 262, 930 290, 1000 280 L1000 600 L0 600 Z" fill="#f5b800"/>
        <path d="M0 205 C 260 104, 520 104, 720 222 C 840 292, 930 320, 1000 312 L1000 600 L0 600 Z" fill="url(#lxBlue)"/>
    </svg>

    {{-- ===== Brand ===== --}}
    <header class="lx-brand">
        <img src="{{ asset('images/psu-logo.png') }}" alt="Pangasinan State University seal">
        <div>
            <span class="lx-brand-name">Pangasinan State University</span>
            <span class="lx-brand-sub">Region's Premier University</span>
        </div>
    </header>

    @if ($slides->count() > 1 || filled($slides[0]['campus'] ?? null))
        <div class="lx-campus" aria-live="polite">
            <span class="lx-campus-name" id="lxCampusPill" @if (! filled($slides[0]['campus'])) hidden @endif><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg><span id="lxCampusName">{{ $slides[0]['campus'] }}</span></span>
            @if ($slides->count() > 1)
                <span class="lx-dots">
                    @foreach ($slides as $i => $slide)
                        <button type="button" class="lx-dot {{ $i === 0 ? 'is-active' : '' }}" data-slide="{{ $i }}" aria-label="Show photo {{ $i + 1 }}{{ $slide['campus'] ? ': '.$slide['campus'] : '' }}"></button>
                    @endforeach
                </span>
            @endif
        </div>
    @endif

    <div class="lx-layout">
        {{-- ===== Left: headline on the blue sweep ===== --}}
        <aside class="lx-hero" aria-label="About PSU Intern-Link">
            <h2>PSU Internship<br><span>Management System</span></h2>
            <p class="lx-tagline"><span class="lx-tagline-line"></span><span>Bridging Classroom Learning<br>to Real-World Opportunities.</span></p>

        </aside>

        {{-- ===== Right: sign-in card ===== --}}
        <section class="lx-card" aria-labelledby="login-heading">
            <div class="lx-cap" aria-hidden="true">
                <svg viewBox="0 0 64 48"><path d="M32 4 2 17l30 13 30-13Z" fill="#1546b8"/><path d="M14 23v11c0 4 8 8 18 8s18-4 18-8V23l-18 8Z" fill="#0b2f86"/><path d="M56 19v15" stroke="#f5b800" stroke-width="3" stroke-linecap="round"/><circle cx="56" cy="36" r="3.5" fill="#f5b800"/></svg>
            </div>
            <h1 id="login-heading">PSU Internship <span>Portal</span></h1>
            <p class="lx-card-sub">Sign in to access your account.</p>

            @if (session('status'))
                <div class="lx-alert lx-alert-success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="lx-alert lx-alert-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="login-form" novalidate @if (! empty($preview)) onsubmit="return false" @endif>
                @csrf

                <label class="lx-label" for="id_number">Student / Employee ID</label>
                <div class="lx-input">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16c.6-1.5 1.8-2.3 3.2-2.3s2.6.8 3.2 2.3M14.5 10h3.5M14.5 13.5h2.5"/></svg>
                    <input type="text" name="id_number" id="id_number" placeholder="e.g. 23-AC-0092"
                           value="{{ old('id_number') }}" autocomplete="username" autocapitalize="characters" spellcheck="false"
                           aria-describedby="id-detect" required autofocus @class(['is-invalid' => $errors->has('id_number')])>
                </div>
                <div id="id-detect" class="lx-detect" aria-live="polite">
                    <span class="login-detect-idle">Your campus is detected from your ID.</span>
                </div>

                <label class="lx-label" for="password">Password</label>
                <div class="lx-input">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.8a4 4 0 0 1 8 0v2.7"/><circle cx="12" cy="15.5" r="1.3"/></svg>
                    <input type="password" name="password" id="password" placeholder="Enter your password" autocomplete="current-password" required>
                    <button type="button" class="lx-reveal" id="toggle-password" aria-label="Show password" aria-pressed="false">
                        <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.1A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 0 1-3 3.7M6.3 7.6A16.4 16.4 0 0 0 2.5 12S6 18.5 12 18.5a9 9 0 0 0 4.2-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                    </button>
                </div>
                <div class="lx-forgot"><a href="{{ route('password.forgot') }}">Forgot Password?</a></div>

                <button type="submit" class="login-submit lx-submit" @disabled(! empty($preview))><span>LOGIN</span></button>
            </form>

            <div class="lx-or"><span>or</span></div>
            <p class="lx-help">No account yet? Accounts are created by your campus OJT Coordinator.</p>
            <p class="lx-card-foot">Pangasinan State University &bull; Internship Management System</p>
        </section>
    </div>
</main>
@endsection

@push('scripts')
<script>
// Campus photos: one after another every 5 seconds (dots pick one).
(function () {
    var photos = document.querySelectorAll('.lx-hero-photo');
    if (photos.length < 2) return;
    var dots = document.querySelectorAll('.lx-dot'), label = document.getElementById('lxCampusName');
    var current = 0, timer;
    function show(n) {
        photos[current].classList.remove('is-active'); dots[current].classList.remove('is-active');
        current = (n + photos.length) % photos.length;
        photos[current].classList.add('is-active'); dots[current].classList.add('is-active');
        label.textContent = photos[current].dataset.campus;
        document.getElementById('lxCampusPill').hidden = !photos[current].dataset.campus;
    }
    function start() { clearInterval(timer); timer = setInterval(function () { show(current + 1); }, 5000); }
    dots.forEach(function (d) { d.addEventListener('click', function () { show(+d.dataset.slide); start(); }); });
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) start();
})();

(function () {
    // Codes that identify a campus inside an ID, e.g. { "ACC": "Alaminos City Campus", "AC": "Alaminos City Campus" }.
    // Detection here is only a hint; the server works out the real campus and role at sign-in.
    var campusCodes = @json($campusCodes);
    // In a student number these win: "AC" in 23-AC-0092 is Alaminos City, not the Asingan code.
    var studentCodes = @json($studentCodes ?? []);

    var input = document.getElementById('id_number');
    var out = document.getElementById('id-detect');
    var idleHtml = out.innerHTML;

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function detect() {
        var raw = input.value.trim().toUpperCase();
        if (raw.length < 2) { out.innerHTML = idleHtml; return; }

        var segments = raw.split(/[^A-Z0-9]+/);
        var campus = null;
        var student = raw.match(/^\d{2,4}-([A-Z]+)-\d+$/);
        if (student && Object.prototype.hasOwnProperty.call(studentCodes, student[1])) {
            campus = studentCodes[student[1]];
        }
        for (var i = 0; ! campus && i < segments.length; i++) {
            if (segments[i] && Object.prototype.hasOwnProperty.call(campusCodes, segments[i])) {
                campus = campusCodes[segments[i]];
                break;
            }
        }

        // Student numbers start with the enrollment year (23-AC-0092); employee IDs start with the campus code.
        // Employee IDs that carry a role tag (ACC-COORD-001, ACC-INST-001, ACC-HTE-001) get a more specific label.
        var kind = /^\d{2,4}-/.test(raw) ? 'Student Intern'
            : segments.indexOf('COORD') > -1 ? 'OJT Coordinator'
            : segments.indexOf('INST') > -1 ? 'Subject Instructor'
            : segments.indexOf('HTE') > -1 ? 'HTE Supervisor'
            : 'Employee';

        if (campus) {
            out.innerHTML =
                '<span class="login-detect-chip">' +
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg>' +
                    escapeHtml(campus) +
                '</span>' +
                '<span class="login-detect-kind">' + kind + '</span>';
        } else {
            out.innerHTML = '<span class="login-detect-idle">Keep typing &mdash; your campus will show here.</span>';
        }
    }

    input.addEventListener('input', detect);
    detect();

    var pw = document.getElementById('password');
    var toggle = document.getElementById('toggle-password');
    toggle.addEventListener('click', function () {
        var show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        toggle.classList.toggle('is-on', show);
    });

    document.querySelector('.login-form').addEventListener('submit', function (e) {
        var btn = this.querySelector('.login-submit');
        if (!input.value.trim() || !pw.value) {
            e.preventDefault();
            (input.value.trim() ? pw : input).focus();
            return;
        }
        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.querySelector('span').textContent = 'SIGNING IN…';
    });
})();
</script>
@endpush
