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
        <strong>{{ $acct['name'] }}</strong>'s account was created for <strong>{{ $acct['hte_name'] }}</strong>.
        Share these credentials with the HTE representative - this password is shown once and cannot be recovered here:
        <div class="mt-2">
            ID number: <code>{{ $acct['id_number'] }}</code>
            &nbsp;&middot;&nbsp;
            Temporary password: <code>{{ $acct['temporary_password'] }}</code>
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

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-10">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by representative name, ID number, or HTE name..."
                value="{{ request('search') }}"
            >
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-sm btn-outline-primary">
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
                        <div class="fw-semibold">{{ $account->user->name }}</div>
                        <div class="text-secondary" style="font-size: 0.78rem;">{{ $account->user->id_number }}</div>
                    </td>
                    <td>
                        @if ($account->hte)
                            <span class="badge rounded-pill text-bg-primary">{{ $account->hte->name }}</span>
                        @else
                            <span class="badge rounded-pill text-bg-light text-secondary">No HTE linked</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('coordinator.hte-accounts.reset-password', $account) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                    onclick="return confirm('Generate a new temporary password for {{ $account->user->name }}?')">
                                Reset password
                            </button>
                        </form>
                        <form method="POST" action="{{ route('coordinator.hte-accounts.destroy', $account) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete the account of {{ $account->user->name }}{{ $account->hte ? ' ('.$account->hte->name.')' : '' }}? They will no longer be able to sign in. The company record and any evaluations they filed are kept. This cannot be undone.')">
                                Delete
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
