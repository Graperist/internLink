<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @include('partials.pwa-head')
    <title>@yield('title', 'HTE Supervisor Portal - PSU Intern-Link')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/internlink.css') }}?v={{ filemtime(public_path('css/internlink.css')) }}" rel="stylesheet">
</head>
<body class="il-shell-body il-hte-portal">
    <div class="il-shell">
        @include('partials.mobile-nav')
        <aside class="il-sidebar">
            <div class="il-sidebar-brand">
                <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
                <div>
                    <p class="il-brand-title">PSU Intern-Link</p>
                    <p class="il-brand-subtitle">HTE Supervisor Portal</p>
                </div>
            </div>

            <a href="{{ route('hte.profile') }}" class="il-sidebar-user il-sidebar-user-link text-decoration-none" title="Edit my profile">
                @if (auth()->user()->avatar_url)
                    <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="il-avatar-img">
                @else
                    <div class="il-avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn($p) => $p[0] ?? '')->join('') }}</div>
                @endif
                <div class="min-w-0">
                    <p class="il-user-name text-truncate">{{ auth()->user()->name }}</p>
                    <p class="il-user-role">HTE Supervisor - {{ auth()->user()->hteRepresentative?->hte?->name ?? '—' }}</p>
                </div>
                <i class="ti ti-chevron-right ms-auto text-secondary"></i>
            </a>

            <nav class="il-nav">
                <p class="il-nav-label">Overview</p>
                <a href="{{ route('hte.dashboard') }}" class="il-nav-link {{ request()->routeIs('hte.dashboard') ? 'active' : '' }}">
                    <i class="ti ti-layout-dashboard"></i> Dashboard
                </a>

                <p class="il-nav-label">Intern Management</p>
                <a href="{{ route('hte.students') }}" class="il-nav-link {{ request()->routeIs('hte.students') ? 'active' : '' }}">
                    <i class="ti ti-users"></i> My Assigned Interns
                </a>
                <a href="{{ route('hte.dtr') }}" class="il-nav-link {{ request()->routeIs('hte.dtr') ? 'active' : '' }}">
                    <i class="ti ti-clock-check"></i> DTR Verification
                </a>
                <a href="{{ route('hte.evaluations') }}" class="il-nav-link {{ request()->routeIs('hte.evaluations') ? 'active' : '' }}">
                    <i class="ti ti-clipboard-check"></i> Submit Evaluation
                </a>
                <a href="{{ route('hte.evaluations.received') }}" class="il-nav-link {{ request()->routeIs('hte.evaluations.received*') ? 'active' : '' }}">
                    <i class="ti ti-inbox"></i> Evaluations Received
                </a>

                <p class="il-nav-label">Updates</p>
                <a href="{{ route('hte.notifications') }}" class="il-nav-link {{ request()->routeIs('hte.notifications') ? 'active' : '' }}">
                    <i class="ti ti-bell"></i> Notifications
                    @if (($hteUnreadNotifications ?? 0) > 0)
                        <span class="badge rounded-pill text-bg-danger ms-1">{{ $hteUnreadNotifications }}</span>
                    @endif
                </a>

                <p class="il-nav-label">Account</p>
                <a href="{{ route('hte.profile') }}" class="il-nav-link {{ request()->routeIs('hte.profile') ? 'active' : '' }}">
                    <i class="ti ti-user-cog"></i> My Profile
                </a>
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="il-sidebar-footer">
                @csrf
                <button type="submit" class="il-signout-btn">Sign Out</button>
            </form>
        </aside>

        <main class="il-main">
            <div class="il-portal-topbar">
                <h1>@yield('page_title', 'Dashboard Overview')</h1>
                <div class="il-topbar-meta">
                    <span>{{ now()->format('F d, Y') }}</span>
                    <a href="{{ route('hte.notifications') }}" class="il-topbar-bell position-relative text-decoration-none" title="HTE portal notifications">
                        <i class="ti ti-bell"></i>
                        @if (($hteUnreadNotifications ?? 0) > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" style="font-size: 0.6rem;">
                                {{ $hteUnreadNotifications > 9 ? '9+' : $hteUnreadNotifications }}
                            </span>
                        @endif
                    </a>
                </div>
            </div>

            @yield('content')
        </main>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/dist/tabler-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    {{-- Invisible click tracker only. The audit pop-up is deliberately NOT included on HTE screens. --}}
    @include('partials.audit-tracker')
    @stack('scripts')
</body>
</html>
