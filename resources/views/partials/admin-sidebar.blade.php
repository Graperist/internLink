{{--
    The Super Admin's sidebar. When a campus is opened with Manage, the
    coordinator's modules for that campus appear in it (under the campus's
    name), so the Super Admin stays in their own portal.
--}}
@php
    $portalUser = auth()->user();
    $managedCampus = \App\Support\CampusManager::campus();
    $allCampuses = $managedCampus ? \App\Models\Campus::orderBy('name')->get(['id', 'code', 'name']) : collect();
    $campusModules = [
        ['instructor.dashboard', ['instructor.dashboard'], 'ti-layout-dashboard', 'Campus Dashboard'],
        ['instructor.htes', ['instructor.htes*'], 'ti-building', 'Partner Agencies'],
        ['instructor.advisees', ['instructor.advisees*', 'instructor.interns.*'], 'ti-users', 'Students'],
        ['instructor.monitoring', ['instructor.monitoring*'], 'ti-clock', 'Time Frame'],
        ['instructor.reports', ['instructor.reports*', 'coordinator.reports*'], 'ti-report', 'Generate Reports'],
        ['coordinator.programs.index', ['coordinator.programs.*'], 'ti-school', 'Programs'],
        ['coordinator.instructors.index', ['coordinator.instructors.*'], 'ti-chalkboard', 'Instructors'],
        ['coordinator.hte-accounts.index', ['coordinator.hte-accounts.*'], 'ti-user-shield', 'HTE Accounts'],
        ['coordinator.documents', ['coordinator.documents*', 'coordinator.repository*'], 'ti-folder', 'Repository'],
        ['coordinator.campus-settings.edit', ['coordinator.settings', 'coordinator.campus-settings.*', 'instructor.audit-trail'], 'ti-settings', 'Campus Settings'],
    ];
@endphp
<aside class="il-sidebar">
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

        @if ($managedCampus)
            {{-- The campus opened with Manage: its coordinator modules. --}}
            <div class="il-manage-head">
                <p class="il-nav-label mb-1">Managing</p>
                <form method="POST" id="ilSwitchCampus" class="mb-1">
                    @csrf
                    <select class="form-select form-select-sm il-manage-select" aria-label="Campus"
                            onchange="this.form.action = this.value; this.form.submit();">
                        @foreach ($allCampuses as $c)
                            <option value="{{ route('admin.campuses.manage', $c) }}" @selected($c->id === $managedCampus->id)>{{ $c->code }} - {{ \Illuminate\Support\Str::after($c->name, ' - ') ?: $c->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            @foreach ($campusModules as [$route, $patterns, $icon, $label])
                <a href="{{ route($route) }}" class="il-nav-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}">
                    <i class="ti {{ $icon }}"></i> {{ $label }}
                </a>
            @endforeach
            <form method="POST" action="{{ route('admin.campuses.leave') }}">
                @csrf
                <button type="submit" class="il-nav-link il-manage-close"><i class="ti ti-x"></i> Close campus</button>
            </form>
        @endif

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
<style>
    .il-manage-head { margin-top: .6rem; }
    .il-manage-select { background-color: rgba(255,255,255,.08); color: #fff; border-color: rgba(255,255,255,.18); font-weight: 600; }
    .il-manage-select option { color: #14213a; }
    .il-manage-close { background: none; border: 0; width: 100%; text-align: left; color: rgba(255,255,255,.65); }
    .il-manage-close:hover { color: #fff; }
</style>
