@extends('layouts.coordinator')

@section('title', 'HTE Accounts - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">HTE accounts</h1>
    <a href="{{ route('coordinator.hte-accounts.create') }}" class="il-btn-primary text-decoration-none">
        <i class="ti ti-plus"></i> Add HTE account
    </a>
</div>

@if (session('provisioned_account'))
    @php $acct = session('provisioned_account'); @endphp
    <div class="il-badge-generated mb-3">
        @if (! empty($acct['reset']))
            <strong>{{ $acct['name'] }}</strong>'s password for <strong>{{ $acct['hte_name'] }}</strong> was reset.
        @else
            <strong>{{ $acct['name'] }}</strong>'s account was created for <strong>{{ $acct['hte_name'] }}</strong>.
        @endif
        @if (($acct['emailed'] ?? null) === true)
            <span class="d-block mt-1"><i class="ti ti-mail-check"></i> The sign-in details were emailed to <strong>{{ $acct['email'] }}</strong>.</span>
        @elseif (($acct['emailed'] ?? null) === false)
            <span class="d-block mt-1 text-danger"><i class="ti ti-mail-off"></i> The email to {{ $acct['email'] }} couldn't be sent. Share these sign-in details with the HTE representative yourself.</span>
        @else
            Share these sign-in details with the HTE representative.
        @endif
        HTE accounts are for evaluating the interns only and keep this password (they can't change it).
        It is shown once; use "new password" below if it gets lost.
        <div class="mt-2">
            ID number: <code>{{ $acct['id_number'] }}</code>
            &nbsp;&middot;&nbsp;
            Password: <code>{{ $acct['temporary_password'] }}</code>
        </div>
    </div>
@endif

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">HTE Representatives</h2>
        <span class="text-secondary small">{{ $accounts->total() }} total</span>
    </div>

    <form method="GET" class="row g-2 mb-3 align-items-center">
        <div class="col-md il-search">
            <i class="ti ti-search"></i>
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by representative name, ID number, or HTE name..."
                value="{{ request('search') }}"
            >
        </div>
        <div class="col-md-auto d-flex align-items-center gap-1">
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-search"></i> Search
            </button>
            @if (request()->hasAny(['search']))
                <a href="{{ route('coordinator.hte-accounts.index') }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Representative</th>
                <th>HTE</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td>
                        @php $ini = \App\Models\User::initialsOf($account->user->name); @endphp
                        <div class="d-flex align-items-center gap-2">
                            <span class="il-avatar-sm">{{ $ini }}</span>
                            <div>
                                <div class="fw-semibold">{{ $account->user->name }}</div>
                                <div class="text-secondary" style="font-size: 0.78rem;">{{ $account->user->id_number }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if ($account->hte)
                            <span class="badge rounded-pill text-bg-primary"><i class="ti ti-building"></i> {{ $account->hte->name }}</span>
                        @else
                            <span class="badge rounded-pill text-bg-light text-secondary">No HTE linked</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <form method="POST" action="{{ route('coordinator.hte-accounts.reset-password', $account) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="il-icon-btn" title="Reset password"
                                    onclick="return confirm('Generate a new password for {{ addslashes($account->user->name) }}?{{ $account->user->email ? ' It will be emailed to '.addslashes($account->user->email).'.' : '' }}')">
                                <i class="ti ti-key"></i><span class="visually-hidden">Reset password</span>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('coordinator.hte-accounts.destroy', $account) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="il-icon-btn danger" title="Delete"
                                    onclick="return confirm('Delete the account of {{ $account->user->name }}{{ $account->hte ? ' ('.$account->hte->name.')' : '' }}? They will no longer be able to sign in. The company record and any evaluations they filed are kept. This cannot be undone.')">
                                <i class="ti ti-trash"></i><span class="visually-hidden">Delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center text-secondary py-4">
                        @if (request()->hasAny(['search']))
                            No HTE accounts match this search.
                        @else
                            No HTE accounts yet.
                            <a href="{{ route('coordinator.hte-accounts.create') }}">Add your first one</a>.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-3">
    {{ $accounts->links() }}
</div>
@endsection
