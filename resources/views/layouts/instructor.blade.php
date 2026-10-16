<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @include('partials.pwa-head')
    <title>@yield('title', 'PSU Intern-Link')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/internlink.css') }}?v={{ filemtime(public_path('css/internlink.css')) }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="il-shell-body">
    <div class="il-shell">
        @include('partials.mobile-nav')
        <aside class="il-sidebar">
            @php
                // One sidebar for both roles. The OJT Coordinator is also a
                // program instructor: same modules, campus-wide view, plus a
                // Campus Administration section.
                $portalUser = auth()->user();
                $isCoordinator = $portalUser->isCoordinator();
                $programCode = $portalUser->instructor?->program?->code;
            @endphp
            <div class="il-sidebar-brand">
                <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
                <div>
                    <p class="il-brand-title">PSU Intern-Link</p>
                    <p class="il-brand-subtitle">{{ $isCoordinator ? 'Coordinator Portal' : 'Instructor Portal' }}</p>
                </div>
            </div>

            <a href="{{ route('instructor.profile') }}" class="il-sidebar-user il-sidebar-user-link text-decoration-none" title="Edit my profile">
                @if ($portalUser->avatar_url)
                    <img src="{{ $portalUser->avatar_url }}" alt="{{ $portalUser->name }}" class="il-avatar-img">
                @else
                    <div class="il-avatar">{{ collect(explode(' ', $portalUser->name))->map(fn($p) => $p[0] ?? '')->join('') }}</div>
                @endif
                <div class="min-w-0">
                    <p class="il-user-name text-truncate">{{ $portalUser->name }}</p>
                    <p class="il-user-role text-truncate">
                        {{ $isCoordinator ? 'OJT Coordinator' : 'Subject Instructor' }} - {{ $programCode ?? '—' }}
                    </p>
                </div>
                <i class="ti ti-chevron-right ms-auto text-secondary"></i>
            </a>

            <nav class="il-nav">
                <p class="il-nav-label">Overview</p>
                <a href="{{ route('instructor.dashboard') }}" class="il-nav-link {{ request()->routeIs('instructor.dashboard') ? 'active' : '' }}">
                    <i class="ti ti-layout-dashboard"></i> Dashboard
                </a>

                <p class="il-nav-label">Internship</p>
                <a href="{{ route('instructor.htes') }}" class="il-nav-link {{ request()->routeIs('instructor.htes*') ? 'active' : '' }}">
                    <i class="ti ti-building"></i> Partner Agencies
                </a>
                <a href="{{ route('instructor.advisees') }}" class="il-nav-link {{ request()->routeIs('instructor.advisees*', 'instructor.interns.*') ? 'active' : '' }}">
                    <i class="ti ti-users"></i> {{ $isCoordinator ? 'Students' : 'My Students' }}
                </a>
                <a href="{{ route('instructor.documents') }}" class="il-nav-link {{ request()->routeIs('instructor.documents*') ? 'active' : '' }}">
                    <i class="ti ti-file-text"></i> Document Review
                </a>
                <a href="{{ route('instructor.monitoring') }}" class="il-nav-link {{ request()->routeIs('instructor.monitoring') ? 'active' : '' }}">
                    <i class="ti ti-clock"></i> Monitoring
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

                @if ($isCoordinator)
                    <p class="il-nav-label">Campus Administration</p>
                    <a href="{{ route('coordinator.programs.index') }}" class="il-nav-link {{ request()->routeIs('coordinator.programs.*') ? 'active' : '' }}">
                        <i class="ti ti-school"></i> Programs
                    </a>
                    <a href="{{ route('coordinator.instructors.index') }}" class="il-nav-link {{ request()->routeIs('coordinator.instructors.*') ? 'active' : '' }}">
                        <i class="ti ti-chalkboard"></i> Instructors
                    </a>
                    <a href="{{ route('coordinator.hte-accounts.index') }}" class="il-nav-link {{ request()->routeIs('coordinator.hte-accounts.*') ? 'active' : '' }}">
                        <i class="ti ti-user-shield"></i> HTE Accounts
                    </a>
                    <a href="{{ route('coordinator.documents') }}" class="il-nav-link {{ request()->routeIs('coordinator.documents*') ? 'active' : '' }}">
                        <i class="ti ti-checklist"></i> Document Tracking
                    </a>
                    <a href="{{ route('coordinator.repository') }}" class="il-nav-link {{ request()->routeIs('coordinator.repository*') ? 'active' : '' }}">
                        <i class="ti ti-folder"></i> Repository
                    </a>
                    <a href="{{ route('coordinator.reports') }}" class="il-nav-link {{ request()->routeIs('coordinator.reports*') ? 'active' : '' }}">
                        <i class="ti ti-file-export"></i> Campus Exports
                    </a>
                    <a href="{{ route('coordinator.campus-settings.edit') }}" class="il-nav-link {{ request()->routeIs('coordinator.campus-settings.*') ? 'active' : '' }}">
                        <i class="ti ti-settings"></i> Campus Settings
                    </a>
                @endif

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
    @stack('scripts')
</body>
</html>
