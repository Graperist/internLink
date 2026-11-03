@extends('layouts.coordinator')

@section('title', 'HTE Accounts - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">HTE accounts</h1>
    <a href="{{ route('instructor.hte-accounts.create') }}" class="il-btn-primary text-decoration-none">
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
            <span class="d-block mt-1 text-danger"><i class="ti ti-mail-off"></i> The email to {{ $acct['email'] }} couldn't be sent{{ filled($acct['mail_error'] ?? null) ? ': '.$acct['mail_error'] : '.' }} Share these sign-in details with the HTE representative yourself.</span>
        @else
            <span class="d-block mt-1"><i class="ti ti-mail"></i> Nothing was emailed. Use the mail icon to email the sign-in details once the agency's interns have finished.</span>
        @endif
        @if (filled($acct['email_warning'] ?? null))
            <span class="d-block mt-1 text-danger"><i class="ti ti-alert-triangle"></i> {{ $acct['email_warning'] }}</span>
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
@if (session('warning'))
    <div class="alert alert-warning"><i class="ti ti-alert-triangle"></i> {{ session('warning') }}</div>
@endif
@if ($errors->has('send'))
    <div class="alert alert-danger"><i class="ti ti-mail-off"></i> {{ $errors->first('send') }}</div>
@endif

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">HTE Representatives &middot; {{ $year === 'all' ? 'All academic years' : 'AY '.$year.($year === $currentYear ? ' (current)' : '') }}</h2>
        <span class="text-secondary small">{{ $accounts->total() }} total</span>
    </div>

    <form method="GET" class="row g-2 mb-3 align-items-start">
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text small">AY</span>
                @include('partials.academic-year-input', ['name' => 'academic_year', 'id' => 'hte_ay', 'value' => $year === 'all' ? '' : $year, 'autoSubmit' => true])
            </div>
            <div class="small mt-1">
                @if ($year === 'all')
                    <span class="text-secondary">Showing all years &middot;</span> <a href="{{ route('instructor.hte-accounts.index') }}">Back to AY {{ $currentYear }}</a>
                @else
                    <a href="{{ route('instructor.hte-accounts.index', ['academic_year' => 'all']) }}">Show all years</a>
                @endif
            </div>
        </div>
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
            @if (request()->filled('search'))
                <a href="{{ route('instructor.hte-accounts.index', ['academic_year' => $year]) }}" class="btn btn-sm btn-link">Clear</a>
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
                                <div class="text-secondary" style="font-size: 0.78rem;"><i class="ti ti-mail"></i> {{ $account->user->email ?: 'No email' }}</div>
                                                <div style="font-size: 0.75rem;" class="{{ $account->credentials_sent_at ? 'text-success' : 'text-secondary' }}">
                                    <i class="ti {{ $account->credentials_sent_at ? 'ti-mail-check' : 'ti-mail' }}"></i>
                                    {{ $account->credentials_sent_at ? 'Sign-in details sent '.$account->credentials_sent_at->format('M d, Y') : 'Sign-in details not sent yet' }}
                                </div>
                                @if ($account->hte && filled($account->hte->contact_email) && strcasecmp($account->hte->contact_email, (string) $account->user->email) !== 0)
                                    <div class="text-danger" style="font-size: 0.75rem;"><i class="ti ti-alert-triangle"></i> Not the agency's email ({{ $account->hte->contact_email }}) - that address is used by another account.</div>
                                @endif
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
                        @include('partials.hte-send-credentials', ['representative' => $account])
                        <form method="POST" action="{{ route('instructor.hte-accounts.reset-password', $account) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="il-icon-btn" title="Reset password"
                                    onclick="return confirm('Generate a new password for {{ addslashes($account->user->name) }}? It is shown once here and not emailed (use the mail icon to email the sign-in details).')">
                                <i class="ti ti-key"></i><span class="visually-hidden">Reset password</span>
                            </button>
                        </form>
                        <a href="{{ route('instructor.hte-accounts.edit', $account) }}" class="il-icon-btn" title="Edit"><i class="ti ti-pencil"></i><span class="visually-hidden">Edit</span></a>
                        <form method="POST" action="{{ route('instructor.hte-accounts.archive', $account) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="il-icon-btn danger" title="Archive"
                                    onclick="return confirm('Archive the account of {{ addslashes($account->user->name) }}{{ $account->hte ? ' ('.addslashes($account->hte->name).')' : '' }}? It can no longer sign in. The account and the evaluations it filed are kept, and you can restore it anytime.')">
                                <i class="ti ti-archive"></i><span class="visually-hidden">Archive</span>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center text-secondary py-4">
                        @if (request()->filled('search'))
                            No HTE accounts match this search.
                        @elseif ($year !== 'all')
                            No HTE accounts for AY {{ $year }}.
                            <a href="{{ route('instructor.hte-accounts.index', ['academic_year' => 'all']) }}">See all academic years</a>.
                        @else
                            No HTE accounts yet.
                            <a href="{{ route('instructor.hte-accounts.create') }}">Add your first one</a>.
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

@if ($archived->isNotEmpty())
    <details class="il-card mt-3 hte-archived" @if (request()->filled('search')) open @endif>
        <summary class="d-flex align-items-center gap-2">
            <i class="ti ti-chevron-right hte-chev text-secondary"></i>
            <span class="fw-bold"><i class="ti ti-archive text-secondary"></i> Archived HTE accounts{{ $year === 'all' ? '' : ' · AY '.$year }}</span>
            <span class="text-secondary small ms-auto">{{ $archived->count() }} archived</span>
        </summary>
        <p class="text-secondary small mt-2 mb-1">Kept with all their records and evaluations. They can't sign in until restored.</p>
        <table class="table align-middle mb-0">
            <tbody>
                @foreach ($archived as $account)
                    <tr>
                        <td>
                            <span class="fw-semibold d-block text-secondary">{{ $account->user->name }}</span>
                            <span class="text-secondary" style="font-size:.78rem;">{{ $account->user->id_number }} &middot; {{ $account->user->email }}</span>
                        </td>
                        <td><span class="badge rounded-pill text-bg-light border text-secondary"><i class="ti ti-building"></i> {{ $account->hte?->name ?? 'No HTE linked' }}</span></td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('instructor.hte-accounts.restore', $account) }}" class="d-inline"
                                  onsubmit="return confirm('Restore {{ addslashes($account->user->name) }}\'s account? It will be able to sign in again.')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="ti ti-restore"></i> Restore</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>
    <style>
        .hte-archived > summary { cursor: pointer; list-style: none; }
        .hte-archived > summary::-webkit-details-marker { display: none; }
        .hte-chev { transition: transform .15s; }
        .hte-archived[open] .hte-chev { transform: rotate(90deg); }
    </style>
@endif
@endsection
