<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @include('partials.pwa-head')
    <title>@yield('title', 'Coordinator Portal - PSU Intern-Link')</title>
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
                    <p class="il-brand-subtitle">Coordinator Portal</p>
                </div>
            </div>

            <a href="{{ route('coordinator.profile') }}" class="il-sidebar-user il-sidebar-user-link text-decoration-none" title="Edit my profile">
                @if (auth()->user()->avatar_url)
                    <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="il-avatar-img">
                @else
                    <div class="il-avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn($p) => $p[0] ?? '')->join('') }}</div>
                @endif
                <div class="min-w-0">
                    <p class="il-user-name text-truncate">{{ auth()->user()->name }}</p>
                    <p class="il-user-role text-truncate">OJT Coordinator</p>
                </div>
                <i class="ti ti-chevron-right ms-auto text-secondary"></i>
            </a>

            <nav class="il-nav">
                <p class="il-nav-label">Overview</p>
                <a href="{{ route('coordinator.dashboard') }}" class="il-nav-link {{ request()->routeIs('coordinator.dashboard') ? 'active' : '' }}">
                    <i class="ti ti-layout-dashboard"></i> Dashboard
                </a>

                <p class="il-nav-label">Management</p>
                <a href="{{ route('coordinator.students.index') }}" class="il-nav-link {{ request()->routeIs('coordinator.students.*') ? 'active' : '' }}">
                    <i class="ti ti-users"></i> Intern List
                </a>
                <a href="{{ route('coordinator.instructors.index') }}" class="il-nav-link {{ request()->routeIs('coordinator.instructors.*') ? 'active' : '' }}">
                    <i class="ti ti-school"></i> Instructors
                </a>
                <a href="{{ route('coordinator.deployments.index') }}" class="il-nav-link {{ request()->routeIs('coordinator.deployments.*') ? 'active' : '' }}">
                    <i class="ti ti-briefcase"></i> Deployment
                </a>
                <a href="{{ route('coordinator.monitoring') }}" class="il-nav-link {{ request()->routeIs('coordinator.monitoring') ? 'active' : '' }}">
                    <i class="ti ti-clock"></i> Monitoring
                </a>
                <a href="{{ route('coordinator.documents') }}" class="il-nav-link {{ request()->routeIs('coordinator.documents') ? 'active' : '' }}">
                    <i class="ti ti-file-text"></i> Documents
                </a>
                <a href="{{ route('coordinator.repository') }}" class="il-nav-link {{ request()->routeIs('coordinator.repository') ? 'active' : '' }}">
                    <i class="ti ti-folder"></i> Repository
                </a>

                <p class="il-nav-label">HTE Management</p>
                <a href="{{ route('coordinator.htes') }}" class="il-nav-link {{ request()->routeIs('coordinator.htes*') ? 'active' : '' }}">
                    <i class="ti ti-building"></i> Partner HTEs
                </a>
                <a href="{{ route('coordinator.hte-accounts.index') }}" class="il-nav-link {{ request()->routeIs('coordinator.hte-accounts.*') ? 'active' : '' }}">
                    <i class="ti ti-user-shield"></i> HTE Accounts
                </a>

                <p class="il-nav-label">System</p>
                <a href="{{ route('coordinator.reports') }}" class="il-nav-link {{ request()->routeIs('coordinator.reports') ? 'active' : '' }}">
                    <i class="ti ti-report"></i> Reports
                </a>
                <a href="{{ route('coordinator.notifications') }}" class="il-nav-link {{ request()->routeIs('coordinator.notifications') ? 'active' : '' }}">
                    <i class="ti ti-bell"></i> Notifications
                </a>

                <p class="il-nav-label">Account</p>
                <a href="{{ route('coordinator.profile') }}" class="il-nav-link {{ request()->routeIs('coordinator.profile') ? 'active' : '' }}">
                    <i class="ti ti-user-cog"></i> My Profile
                </a>
                <a href="{{ route('coordinator.campus-settings.edit') }}" class="il-nav-link {{ request()->routeIs('coordinator.campus-settings.*') ? 'active' : '' }}">
                    <i class="ti ti-building"></i> Campus Settings
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
