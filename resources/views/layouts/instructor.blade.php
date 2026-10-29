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
    {{-- Tabler Icons 2.47.0, bundled locally so the icons load even offline or when a CDN is blocked --}}
    <link rel="stylesheet" href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}">
</head>
<body class="il-shell-body il-staff">
    <div class="il-shell">
        @include('partials.mobile-nav')
        @if (auth()->user()->isSuperAdmin())
            {{-- The Super Admin stays in their own portal: the campus's modules are in their sidebar. --}}
            @include('partials.admin-sidebar')
        @else
        <aside class="il-sidebar">
            @php
                // One sidebar for both roles. The OJT Coordinator is also a
                // program instructor: same modules, campus-wide view, plus a
                // Campus Administration section.
                $portalUser = auth()->user();
                $isCoordinator = $portalUser->isCoordinator();
                $programCode = $portalUser->instructor?->program?->code;
                // The Super Admin working in a campus (with the coordinator's tools).
                $isSuperAdmin = $portalUser->isSuperAdmin();
            @endphp
            <div class="il-sidebar-brand">
                <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
                <div>
                    <p class="il-brand-title">PSU Intern-Link</p>
                    <p class="il-brand-subtitle">{{ $isSuperAdmin ? 'Super Admin' : ($isCoordinator ? 'Coordinator Portal' : 'Instructor Portal') }}</p>
                </div>
            </div>

            <a href="{{ $isSuperAdmin ? route('admin.account') : route('instructor.profile') }}" class="il-sidebar-user il-sidebar-user-link text-decoration-none" title="{{ $isSuperAdmin ? 'My account' : 'Edit my profile' }}">
                @if ($portalUser->avatar_url)
                    <img src="{{ $portalUser->avatar_url }}" alt="{{ $portalUser->name }}" class="il-avatar-img">
                @else
                    <div class="il-avatar">{{ \App\Models\User::initialsOf($portalUser->name) }}</div>
                @endif
                <div class="min-w-0">
                    <p class="il-user-name text-truncate">{{ $portalUser->name }}</p>
                    <p class="il-user-role text-truncate">
                        @if ($isSuperAdmin)
                            Super Admin - {{ $portalUser->campus?->code }}
                        @else
                            {{ $isCoordinator ? 'OJT Coordinator' : 'Subject Instructor' }} - {{ $programCode ?? '—' }}
                        @endif
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
                @unless ($isSuperAdmin)
                <a href="{{ route('instructor.documents') }}" class="il-nav-link {{ request()->routeIs('instructor.documents*') && empty($inTracking) ? 'active' : '' }}">
                    <i class="ti ti-file-text"></i> Document Review
                </a>
                @endunless
                <a href="{{ route('instructor.monitoring') }}" class="il-nav-link {{ request()->routeIs('instructor.monitoring*') ? 'active' : '' }}">
                    <i class="ti ti-clock"></i> Time Frame
                </a>
                {{-- Evaluations of the person's own program (the coordinator handles a program too). --}}
                @unless ($isSuperAdmin)
                <a href="{{ route('instructor.evaluations') }}" class="il-nav-link {{ request()->routeIs('instructor.evaluations*', 'instructor.agency-evaluations.*') ? 'active' : '' }}">
                    <i class="ti ti-star"></i> Evaluations
                </a>
                @endunless
                <a href="{{ route('instructor.reports') }}" class="il-nav-link {{ request()->routeIs('instructor.reports*', 'coordinator.reports*') ? 'active' : '' }}">
                    <i class="ti ti-report"></i> Generate Reports
                </a>
                @unless ($isSuperAdmin)
                <a href="{{ route('instructor.announcements') }}" class="il-nav-link {{ request()->routeIs('instructor.announcements*') ? 'active' : '' }}">
                    <i class="ti ti-speakerphone"></i> Announcements
                </a>
                @endunless

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
                    <a href="{{ route('coordinator.documents') }}" class="il-nav-link {{ request()->routeIs('coordinator.documents*', 'coordinator.repository*') || ! empty($inTracking) ? 'active' : '' }}">
                        <i class="ti ti-folder"></i> Repository
                    </a>
                @endif

                <p class="il-nav-label">Account</p>
                @unless ($isSuperAdmin)
                <a href="{{ route('instructor.profile') }}" class="il-nav-link {{ request()->routeIs('instructor.profile') ? 'active' : '' }}">
                    <i class="ti ti-user-cog"></i> My Profile
                </a>
                @endunless
                @if ($isCoordinator)
                    {{-- OJT Coordinator only: Campus Settings and Audit Trail are inside. --}}
                    <a href="{{ route('coordinator.settings') }}" class="il-nav-link {{ request()->routeIs('coordinator.settings', 'coordinator.campus-settings.*', 'instructor.audit-trail') ? 'active' : '' }}">
                        <i class="ti ti-settings"></i> Settings
                    </a>
                @endif
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="il-sidebar-footer">
                @csrf
                <button type="submit" class="il-signout-btn">Sign out</button>
            </form>
        </aside>
        @endif

        <main class="il-main">
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @include('partials.audit-tracker')
    @stack('scripts')
</body>
</html>
