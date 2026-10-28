<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Student Portal - PSU Intern-Link')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/internlink.css') }}" rel="stylesheet">
    {{-- Tabler Icons 2.47.0, bundled locally so the icons load even offline or when a CDN is blocked --}}
    <link rel="stylesheet" href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}">
</head>
<body class="il-shell-body">
    <div class="il-shell">
        <aside class="il-sidebar">
            <div class="il-sidebar-brand">
                <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
                <div>
                    <p class="il-brand-title">PSU Intern-Link</p>
                    <p class="il-brand-subtitle">Student Portal</p>
                </div>
            </div>

            <div class="il-sidebar-user">
                <div class="il-avatar">{{ \App\Models\User::initialsOf(auth()->user()->name) }}</div>
                <div>
                    <p class="il-user-name">{{ auth()->user()->name }}</p>
                    <p class="il-user-role">{{ auth()->user()->student->program->code ?? '' }} {{ auth()->user()->id_number }}</p>
                </div>
            </div>

            <nav class="il-nav">
                <p class="il-nav-label">My Internship</p>
                <a href="{{ route('student.dashboard') }}" class="il-nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
                    <i class="ti ti-layout-dashboard"></i> Dashboard
                </a>
                <a href="{{ route('student.dtr') }}" class="il-nav-link {{ request()->routeIs('student.dtr*') ? 'active' : '' }}">
                    <i class="ti ti-clock"></i> Daily Time Record
                </a>
                <a href="{{ route('student.documents') }}" class="il-nav-link {{ request()->routeIs('student.documents*') ? 'active' : '' }}">
                    <i class="ti ti-file-text"></i> My Documents
                </a>
                <a href="{{ route('student.hte') }}" class="il-nav-link {{ request()->routeIs('student.hte') ? 'active' : '' }}">
                    <i class="ti ti-building"></i> My HTE Info
                </a>

                <p class="il-nav-label">Records</p>
                <a href="{{ route('student.progress') }}" class="il-nav-link {{ request()->routeIs('student.progress') ? 'active' : '' }}">
                    <i class="ti ti-chart-bar"></i> My Progress
                </a>
                <a href="{{ route('student.notifications') }}" class="il-nav-link {{ request()->routeIs('student.notifications') ? 'active' : '' }}">
                    <i class="ti ti-bell"></i> Notifications
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
    @stack('scripts')
</body>
</html>
