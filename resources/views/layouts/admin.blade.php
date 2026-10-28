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
    <style>.text-uppercase::placeholder { text-transform: none; }</style>
    <link rel="stylesheet" href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}">
</head>
<body class="il-shell-body il-staff">
    <div class="il-shell">
        @include('partials.mobile-nav')
        <aside class="il-sidebar">
            @php $portalUser = auth()->user(); @endphp
            <div class="il-sidebar-brand">
                <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
                <div>
                    <p class="il-brand-title">PSU Intern-Link</p>
                    <p class="il-brand-subtitle">Super Admin</p>
                </div>
            </div>

            <a href="{{ route('admin.account') }}" class="il-sidebar-user il-sidebar-user-link text-decoration-none" title="My account">
                <div class="il-avatar">{{ \App\Models\User::initialsOf($portalUser->name) }}</div>
                <div class="min-w-0">
                    <p class="il-user-name text-truncate">{{ $portalUser->name }}</p>
                    <p class="il-user-role text-truncate">All campuses</p>
                </div>
                <i class="ti ti-chevron-right ms-auto text-secondary"></i>
            </a>

            <nav class="il-nav">
                <p class="il-nav-label">Overview</p>
                <a href="{{ route('admin.dashboard') }}" class="il-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="ti ti-layout-dashboard"></i> Dashboard
                </a>

                <p class="il-nav-label">University</p>
                <a href="{{ route('admin.campuses.index') }}" class="il-nav-link {{ request()->routeIs('admin.campuses.*') ? 'active' : '' }}">
                    <i class="ti ti-building-community"></i> Campuses
                </a>
                <a href="{{ route('admin.coordinators.index') }}" class="il-nav-link {{ request()->routeIs('admin.coordinators.*') ? 'active' : '' }}">
                    <i class="ti ti-user-star"></i> OJT Coordinators
                </a>
                <a href="{{ route('admin.login-photos') }}" class="il-nav-link {{ request()->routeIs('admin.login-photos*') ? 'active' : '' }}">
                    <i class="ti ti-photo"></i> Login Page Photos
                </a>

                <a href="{{ route('admin.audit-trail') }}" class="il-nav-link {{ request()->routeIs('admin.audit-trail') ? 'active' : '' }}">
                    <i class="ti ti-list-details"></i> Audit Trail
                </a>

                <p class="il-nav-label">Account</p>
                <a href="{{ route('admin.account') }}" class="il-nav-link {{ request()->routeIs('admin.account') ? 'active' : '' }}">
                    <i class="ti ti-user-cog"></i> My Account
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @include('partials.audit-tracker')
    @stack('scripts')
</body>
</html>
