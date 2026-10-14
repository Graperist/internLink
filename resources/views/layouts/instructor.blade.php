<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @include('partials.pwa-head')
    <title>@yield('title', 'Instructor Portal - PSU Intern-Link')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/internlink.css') }}?v={{ filemtime(public_path('css/internlink.css')) }}" rel="stylesheet">
</head>
<body class="il-shell-body">
    <div class="il-shell">
        @include('partials.mobile-nav')
        <aside class="il-sidebar">
            <div class="il-sidebar-brand">
                <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
                <div>
                    <p class="il-brand-title">PSU Intern-Link</p>
                    <p class="il-brand-subtitle">Instructor Portal</p>
                </div>
            </div>

            <a href="{{ route('instructor.profile') }}" class="il-sidebar-user il-sidebar-user-link text-decoration-none" title="Edit my profile">
                @if (auth()->user()->avatar_url)
                    <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="il-avatar-img">
                @else
                    <div class="il-avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn($p) => $p[0] ?? '')->join('') }}</div>
                @endif
                <div class="min-w-0">
                    <p class="il-user-name text-truncate">{{ auth()->user()->name }}</p>
                    <p class="il-user-role text-truncate">Subject Instructor - {{ auth()->user()->instructor->program->code ?? '—' }}</p>
                </div>
                <i class="ti ti-chevron-right ms-auto text-secondary"></i>
            </a>

            <nav class="il-nav">
                <p class="il-nav-label">Overview</p>
                <a href="{{ route('instructor.dashboard') }}" class="il-nav-link {{ request()->routeIs('instructor.dashboard') ? 'active' : '' }}">
                    <i class="ti ti-layout-dashboard"></i> Dashboard
                </a>

                <p class="il-nav-label">Advising</p>
                <a href="{{ route('instructor.advisees') }}" class="il-nav-link {{ request()->routeIs('instructor.advisees') ? 'active' : '' }}">
                    <i class="ti ti-users"></i> My Students
                </a>
                <a href="{{ route('instructor.documents') }}" class="il-nav-link {{ request()->routeIs('instructor.documents*') ? 'active' : '' }}">
                    <i class="ti ti-file-text"></i> Document Review
                </a>
                <a href="{{ route('instructor.htes') }}" class="il-nav-link {{ request()->routeIs('instructor.htes*') ? 'active' : '' }}">
                    <i class="ti ti-building"></i> Partner Agencies
                </a>
                <a href="{{ route('instructor.evaluations') }}" class="il-nav-link {{ request()->routeIs('instructor.evaluations*') ? 'active' : '' }}">
                    <i class="ti ti-star"></i> Evaluations
                </a>
                <a href="{{ route('instructor.reports') }}" class="il-nav-link {{ request()->routeIs('instructor.reports*') ? 'active' : '' }}">
                    <i class="ti ti-report"></i> Generate Reports
                </a>
                <a href="{{ route('instructor.announcements') }}" class="il-nav-link {{ request()->routeIs('instructor.announcements*') ? 'active' : '' }}">
                    <i class="ti ti-speakerphone"></i> Announcements
                </a>
                <a href="{{ route('instructor.notifications') }}" class="il-nav-link {{ request()->routeIs('instructor.notifications') ? 'active' : '' }}">
                    <i class="ti ti-bell"></i> Notifications
                </a>

                <p class="il-nav-label">Account</p>
                <a href="{{ route('instructor.profile') }}" class="il-nav-link {{ request()->routeIs('instructor.profile') ? 'active' : '' }}">
                    <i class="ti ti-user-cog"></i> My Profile
                </a>
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="il-sidebar-footer">
                @csrf
                <button type="submit" class="il-signout-btn">Sign out</button>
            </form>
        </aside>

        <main class="il-main">
            @yield('content')
        </main>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/dist/tabler-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @include('partials.audit-trail')
    @include('partials.audit-tracker')
</body>
</html>
