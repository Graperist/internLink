@extends('layouts.admin')

@section('title', 'OJT Coordinators - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">OJT Coordinators</h1>
        <p class="text-secondary small mb-0">One account per campus. The coordinator then adds the campus's programs, instructors and HTE accounts.</p>
    </div>
    <a href="{{ route('admin.coordinators.create', array_filter(['campus_id' => request('campus_id')])) }}" class="il-btn-primary text-decoration-none"><i class="ti ti-user-plus"></i> Add coordinator</a>
</div>

@if (session('provisioned_account'))
    @php $acct = session('provisioned_account'); @endphp
    <div class="il-badge-generated mb-3">
        @if ($acct['reset'])
            <strong>{{ $acct['name'] }}</strong>'s password was reset.
        @else
            <strong>{{ $acct['name'] }}</strong>'s OJT Coordinator account was created for <strong>{{ $acct['campus'] }}</strong>.
        @endif
        @if ($acct['emailed'] === true)
            <span class="d-block mt-1"><i class="ti ti-mail-check"></i> The sign-in details were emailed to <strong>{{ $acct['email'] }}</strong>.</span>
        @elseif ($acct['emailed'] === false)
            <span class="d-block mt-1 text-danger"><i class="ti ti-mail-off"></i> The email to {{ $acct['email'] }} couldn't be sent. Share these sign-in details with the coordinator yourself.</span>
        @else
            Share these sign-in details with the coordinator.
        @endif
        The password is temporary: they choose their own when they first sign in. It is shown once.
        <div class="mt-2">ID number: <code>{{ $acct['id_number'] }}</code> &nbsp;&middot;&nbsp; Temporary password: <code>{{ $acct['password'] }}</code></div>
    </div>
@endif
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="il-card">
    <form method="GET" class="row g-2 mb-3 align-items-center">
        <div class="col-md il-search">
            <i class="ti ti-search"></i>
            <input type="text" name="search" class="form-control" placeholder="Search by name, ID number or email..." value="{{ request('search') }}">
        </div>
        <div class="col-md-4">
            <select name="campus_id" class="form-select" onchange="this.form.submit()" aria-label="Campus">
                <option value="">All campuses</option>
                @foreach ($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string) request('campus_id') === (string) $campus->id)>{{ $campus->code }} - {{ $campus->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-auto d-flex align-items-center gap-1">
            <button type="submit" class="btn btn-primary"><i class="ti ti-search"></i> Search</button>
            @if (request()->hasAny(['search', 'campus_id']))<a href="{{ route('admin.coordinators.index') }}" class="btn btn-sm btn-link">Clear</a>@endif
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="text-secondary" style="font-size: .75rem; text-transform: uppercase; letter-spacing: .03em;">
                    <th>Coordinator</th><th>Campus</th><th>Email</th><th>Program</th><th>Status</th><th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coordinators as $coordinator)
                    @php $active = $coordinator->status === 'active'; @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="il-row-avatar">{{ \App\Models\User::initialsOf($coordinator->name) }}</span>
                                <span>
                                    <span class="fw-semibold d-block">{{ $coordinator->name }}</span>
                                    <span class="text-secondary" style="font-size:.78rem;">{{ $coordinator->id_number }}</span>
                                </span>
                            </div>
                        </td>
                        <td><span class="badge rounded-pill text-bg-light border fw-bold">{{ $coordinator->campus?->code ?? '—' }}</span> <span class="small">{{ $coordinator->campus?->name }}</span></td>
                        <td class="small">{{ $coordinator->email }}</td>
                        <td class="small">{{ $coordinator->instructor?->program?->code ?? '—' }}</td>
                        <td><span class="badge rounded-pill {{ $active ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ $active ? 'Active' : 'Deactivated' }}</span></td>
                        <td class="text-end text-nowrap">
                            <form method="POST" action="{{ route('admin.coordinators.reset-password', $coordinator) }}" class="d-inline"
                                  onsubmit="return confirm('Give {{ addslashes($coordinator->name) }} a new temporary password? It will be emailed to {{ addslashes($coordinator->email) }}.')">
                                @csrf
                                <button type="submit" class="il-icon-btn" title="Reset password"><i class="ti ti-key"></i><span class="visually-hidden">Reset password</span></button>
                            </form>
                            <form method="POST" action="{{ route('admin.coordinators.status', $coordinator) }}" class="d-inline"
                                  onsubmit="return confirm('{{ $active ? 'Deactivate '.addslashes($coordinator->name).'? They will no longer be able to sign in.' : 'Reactivate '.addslashes($coordinator->name).'?' }}')">
                                @csrf
                                <button type="submit" class="il-icon-btn" title="{{ $active ? 'Deactivate' : 'Reactivate' }}">
                                    <i class="ti {{ $active ? 'ti-user-off' : 'ti-user-check' }}"></i><span class="visually-hidden">{{ $active ? 'Deactivate' : 'Reactivate' }}</span>
                                </button>
                            </form>
                            @include('partials.admin-delete-button', [
                                'action' => route('admin.coordinators.destroy', $coordinator),
                                'message' => "Delete the OJT Coordinator account of {$coordinator->name} ({$coordinator->id_number})? They will no longer be able to sign in. The campus and its records are kept. This cannot be undone.",
                            ])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-5">
                        {{ request()->hasAny(['search', 'campus_id']) ? 'No coordinators match this search or filter.' : 'No coordinator accounts yet.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
