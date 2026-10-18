@extends('layouts.auth')

@section('title', 'Sign In - PSU Intern-Link')
@section('body_class', 'login-body')

@push('styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}" rel="stylesheet">
@endpush

@section('content')
<main class="login-shell">

    {{-- ================= LEFT: sign-in ================= --}}
    <section class="login-panel" aria-labelledby="login-heading">
        <div class="login-panel-inner">

            <header class="login-brand">
                <img src="{{ asset('images/psu-logo.png') }}" alt="Pangasinan State University seal">
                <div>
                    <span class="login-brand-name">PSU Intern-Link</span>
                    <span class="login-brand-sub">Pangasinan State University</span>
                </div>
            </header>

            <div class="login-intro">
                <h1 id="login-heading">Welcome back</h1>
                <p>Sign in with your Student or Employee ID. We'll take you to your campus and your portal automatically.</p>
            </div>

            @if (session('status'))
                <div class="login-alert login-alert-success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="login-alert login-alert-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="login-form" novalidate>
                @csrf

                <div class="login-field">
                    <label for="id_number">Student / Employee ID</label>
                    <div class="login-input-wrap">
                        <svg class="login-input-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16c.6-1.5 1.8-2.3 3.2-2.3s2.6.8 3.2 2.3M14.5 10h3.5M14.5 13.5h2.5"/></svg>
                        <input
                            type="text"
                            name="id_number"
                            id="id_number"
                            placeholder="e.g. 23-AC-0092 or ACC-INST-001"
                            value="{{ old('id_number') }}"
                            autocomplete="username"
                            autocapitalize="characters"
                            spellcheck="false"
                            aria-describedby="id-detect"
                            required
                            autofocus
                            @class(['is-invalid' => $errors->has('id_number')])
                        >
                    </div>
                    <div id="id-detect" class="login-detect" aria-live="polite">
                        <span class="login-detect-idle">Your campus is detected from your ID.</span>
                    </div>
                </div>

                <div class="login-field">
                    <div class="login-label-row">
                        <label for="password">Password</label>
                        <a href="{{ route('password.forgot') }}" class="login-link">Forgot password?</a>
                    </div>
                    <div class="login-input-wrap">
                        <svg class="login-input-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.8a4 4 0 0 1 8 0v2.7"/><circle cx="12" cy="15.5" r="1.3"/></svg>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="login-reveal" id="toggle-password" aria-label="Show password" aria-pressed="false">
                            <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.1A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 0 1-3 3.7M6.3 7.6A16.4 16.4 0 0 0 2.5 12S6 18.5 12 18.5a9 9 0 0 0 4.2-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-submit">
                    <span>Sign in</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            <p class="login-help">
                No account yet? Accounts are created by your campus OJT Coordinator.
            </p>

            <footer class="login-foot">
                &copy; {{ now()->year }} Pangasinan State University &middot; PSU Intern-Link
            </footer>
        </div>
    </section>

    {{-- ================= RIGHT: about the system ================= --}}
    <aside class="login-about" aria-label="About PSU Intern-Link">
        <svg class="login-about-waves" viewBox="0 0 800 800" preserveAspectRatio="none" aria-hidden="true">
            @for ($i = 0; $i < 14; $i++)
                <path d="M-50 {{ 520 + $i * 14 }} C 180 {{ 380 + $i * 10 }}, 420 {{ 700 - $i * 6 }}, 850 {{ 430 + $i * 12 }}" />
            @endfor
        </svg>

        <div class="login-about-inner">
            <span class="login-eyebrow">Internship &amp; Management Monitoring System</span>

            <h2>Every OJT hour, form, and evaluation &mdash; in one place.</h2>

            <p class="login-lede">
                PSU Intern-Link replaces paper-based practicum tracking across Pangasinan State University.
                Students, instructors, coordinators, and host training establishments work from the same
                live record, from the first endorsement letter to the final evaluation.
            </p>

            <ul class="login-features">
                <li>
                    <span class="login-feature-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3.5h7l4.5 4.5v12.5H7z"/><path d="M14 3.5V8h4.5M9.8 13h5.4M9.8 16.5h5.4"/></svg>
                    </span>
                    <div>
                        <strong>Digital OJT documents</strong>
                        <span>Fill out, submit, and track every pre-deployment requirement online.</span>
                    </div>
                </li>
                <li>
                    <span class="login-feature-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
                    </span>
                    <div>
                        <strong>Internship time frame</strong>
                        <span>Daily time in and out, totalled by the system and approved by the HTE supervisor.</span>
                    </div>
                </li>
                <li>
                    <span class="login-feature-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 19.5h15M7 16V11M12 16V6.5M17 16v-3.5"/></svg>
                    </span>
                    <div>
                        <strong>Real-time monitoring</strong>
                        <span>Coordinators see deployment progress and rendered hours across the campus.</span>
                    </div>
                </li>
                <li>
                    <span class="login-feature-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/></svg>
                    </span>
                    <div>
                        <strong>Weekly reports &amp; evaluations</strong>
                        <span>Instructors review reports; HTEs and students complete evaluations online.</span>
                    </div>
                </li>
            </ul>

            <div class="login-roles">
                <span class="login-roles-label">One sign-in for</span>
                <div class="login-role-chips">
                    <span>Student Interns</span>
                    <span>Subject Instructors</span>
                    <span>OJT Coordinators</span>
                    <span>HTE Supervisors</span>
                </div>
            </div>
        </div>
    </aside>
</main>
@endsection

@push('scripts')
<script>
(function () {
    // Codes that identify a campus inside an ID, e.g. { "ACC": "Alaminos City Campus", "AC": "Alaminos City Campus" }.
    // Detection here is only a hint; the server works out the real campus and role at sign-in.
    var campusCodes = @json($campusCodes);

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
        for (var i = 0; i < segments.length; i++) {
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
        btn.querySelector('span').textContent = 'Signing in…';
    });
})();
</script>
@endpush
