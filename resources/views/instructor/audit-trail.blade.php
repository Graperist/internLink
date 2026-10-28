@extends($layout)

@section('title', 'Audit Trail - PSU Intern-Link')

@section('content')
@unless ($isAdmin)
@include('coordinator._settings-back')
@endunless
@php
    $kinds = ['all' => 'All', 'click' => 'Clicks', 'page' => 'Pages', 'action' => 'Actions', 'security' => 'Security'];
    $kindStyle = [
        'click' => ['ti-pointer', '#5d6b80', '#eef1f6', 'Click'],
        'page' => ['ti-file-text', '#1c3f66', '#e8eef7', 'Page'],
        'action' => ['ti-bolt', '#1d6b3a', '#e7f6ec', 'Action'],
        'security' => ['ti-shield-lock', '#8a5d00', '#fff3d6', 'Security'],
    ];
@endphp
<style>
    .at-tabs .nav-link { color: #5d6b80; font-weight: 600; font-size: .86rem; padding: .45rem .8rem; }
    .at-tabs .nav-link.active { color: #1c3f66; border-bottom: 2px solid #1c3f66; background: none; }
    .at-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #5d6b80; white-space: nowrap; }
    .at-time { white-space: nowrap; font-variant-numeric: tabular-nums; }
    .at-time .at-date { font-weight: 700; color: #14213a; }
    .at-time .at-clock { color: #5d6b80; font-size: .8rem; }
    .at-kind { display: inline-flex; align-items: center; gap: .25rem; padding: .1rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .at-alert td { background: #fff5f5; }
</style>

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Audit Trail</h1>
        <p class="text-secondary small mb-0">Who did what, and when. Showing: {{ $scope }}.</p>
    </div>
</div>

<div class="il-card">
    <ul class="nav at-tabs border-bottom mb-3">
        @foreach ($kinds as $value => $label)
            <li class="nav-item">
                <a class="nav-link {{ $kind === $value ? 'active' : '' }}"
                   href="{{ route($routeName, array_filter(['kind' => $value === 'all' ? null : $value, 'q' => $search ?: null, 'date' => $date, 'campus_id' => $campusFilter])) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <form method="GET" action="{{ route($routeName) }}" class="row g-2 mb-3">
        @if ($kind !== 'all')<input type="hidden" name="kind" value="{{ $kind }}">@endif
        <div class="{{ $isAdmin ? 'col-md-4' : 'col-md-7' }}">
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search by user, ID number or action">
        </div>
        @if ($isAdmin)
            <div class="col-md-3">
                <select name="campus_id" class="form-select" onchange="this.form.submit()" aria-label="Campus">
                    <option value="">All campuses</option>
                    @foreach ($campuses as $campus)
                        <option value="{{ $campus->id }}" @selected((string) $campusFilter === (string) $campus->id)>{{ $campus->code }} - {{ $campus->name }}</option>
                    @endforeach
                    <option value="none" @selected($campusFilter === 'none')>Super Admin (no campus)</option>
                </select>
            </div>
        @endif
        <div class="col-md-3">
            <input type="date" name="date" value="{{ $date }}" class="form-control" aria-label="Date">
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary"><i class="ti ti-search"></i> Search</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle at-table mb-0">
            <thead>
                <tr><th style="width: 140px;">Time</th><th style="width: 260px;">User</th><th>Action</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php [$icon, $color, $bg, $kindLabel] = $kindStyle[$log->kind] ?? $kindStyle['action']; @endphp
                    <tr class="{{ $log->is_alert ? 'at-alert' : '' }}">
                        <td class="at-time">
                            <div class="at-date">{{ $log->created_at->format('Y.m.d') }}</div>
                            <div class="at-clock">{{ $log->created_at->format('H:i:s') }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $log->user?->name ?? 'Unknown user' }}</div>
                            <div class="text-secondary" style="font-size: .76rem;">
                                {{ $log->user?->id_number }}{{ isset($roleLabels[$log->role_code]) ? ' · '.$roleLabels[$log->role_code] : '' }}{{ $isAdmin && $log->campus_id && isset($campusCodes[$log->campus_id]) ? ' · '.$campusCodes[$log->campus_id] : '' }}
                            </div>
                        </td>
                        <td>
                            <span class="at-kind me-1" style="color: {{ $color }}; background: {{ $bg }};"><i class="ti {{ $icon }}"></i> {{ $kindLabel }}</span>
                            @if ($log->is_alert)<span class="at-kind me-1" style="color:#a32525;background:#fdecec;"><i class="ti ti-alert-triangle"></i> Alert</span>@endif
                            <span>{{ $log->description }}</span>
                            @if ($log->ip_address)<span class="text-secondary ms-1" style="font-size: .72rem;">· {{ $log->ip_address }}</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-secondary py-5">Nothing recorded{{ $search || $date || $kind !== 'all' ? ' for this filter' : ' yet' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
