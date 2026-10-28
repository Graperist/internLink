<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @include('partials.pwa-head')
    <title>@yield('title', 'HTE Supervisor Portal - PSU Intern-Link')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/internlink.css') }}?v={{ filemtime(public_path('css/internlink.css')) }}" rel="stylesheet">
    {{-- Tabler Icons 2.47.0, bundled locally so the icons load even offline or when a CDN is blocked --}}
    <link rel="stylesheet" href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}">
</head>
<body class="il-shell-body il-staff il-hte">
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

            <div class="il-sidebar-user">
                @if (auth()->user()->avatar_url)
                    <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="il-avatar-img">
                @else
                    <div class="il-avatar">{{ \App\Models\User::initialsOf(auth()->user()->name) }}</div>
                @endif
                <div class="min-w-0">
                    <p class="il-user-name text-truncate">{{ auth()->user()->name }}</p>
                    <p class="il-user-role">HTE Supervisor - {{ auth()->user()->hteRepresentative?->hte?->name ?? '—' }}</p>
                </div>
            </div>

            <nav class="il-nav">
                {{-- HTE accounts are for evaluating the interns only. --}}
                <p class="il-nav-label">Intern Management</p>
                <a href="{{ route('hte.students') }}" class="il-nav-link {{ request()->routeIs('hte.students') ? 'active' : '' }}">
                    <i class="ti ti-users"></i> Interns
                </a>
                <a href="{{ route('hte.evaluations') }}" class="il-nav-link {{ request()->routeIs('hte.evaluations', 'hte.forms.*', 'hte.self-evaluation.*') ? 'active' : '' }}">
                    <i class="ti ti-clipboard-check"></i> Evaluations
                </a>
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="il-sidebar-footer">
                @csrf
                <button type="submit" class="il-signout-btn">Sign out</button>
            </form>
        </aside>

        <main class="il-main">
            {{-- Same page header as the instructor and student portals --}}
            @hasSection('page_title')
                <div class="il-page-header">
                    <div>
                        <h1 class="il-page-title">@yield('page_title')</h1>
                        @hasSection('page_subtitle')<p class="text-secondary mb-0">@yield('page_subtitle')</p>@endif
                    </div>
                    @hasSection('page_actions')<div class="d-flex gap-2 flex-wrap">@yield('page_actions')</div>@endif
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    {{-- Invisible click tracker only. The audit pop-up is deliberately NOT included on HTE screens. --}}
    @include('partials.audit-tracker')
    @stack('scripts')
</body>
</html>
