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
    @php
        $acct = session('provisioned_account');
        $acctMessage = 'PSU Intern-Link sign-in details'."\n"
            .'Name: '.$acct['name']."\n"
            .'ID number: '.$acct['id_number']."\n"
            .'Temporary password: '.$acct['password']."\n"
            .'Sign in at: '.route('login')."\n"
            .'You will be asked to set your own password the first time you sign in.';
    @endphp
    {{-- The new sign-in details pop up (shown once), like enrolling a student. --}}
    <div class="modal fade" id="credentialsModal" tabindex="-1" aria-labelledby="credentialsTitle" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold" id="credentialsTitle">
                        <i class="ti ti-circle-check text-success"></i>
                        {{ ! empty($acct['reset']) ? 'Password reset' : 'Coordinator account created' }}
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-3">
                        @if (! empty($acct['reset']))
                            <strong>{{ $acct['name'] }}</strong>'s password was reset.
                        @else
                            <strong>{{ $acct['name'] }}</strong>'s OJT Coordinator account was created for <strong>{{ $acct['campus'] }}</strong>.
                        @endif
                        Send these sign-in details to the coordinator. The password is shown only this once.
                    </p>
                    <div class="p-3 rounded mb-2" style="background:#f4f6f9;">
                        <div class="d-flex justify-content-between small mb-1"><span class="text-secondary">ID number</span> <code class="fs-6">{{ $acct['id_number'] }}</code></div>
                        <div class="d-flex justify-content-between small"><span class="text-secondary">Temporary password</span> <code class="fs-6">{{ $acct['password'] }}</code></div>
                    </div>
                    @if ($acct['emailed'] === true)
                        <p class="small text-success mb-1"><i class="ti ti-mail-check"></i> Also emailed to <strong>{{ $acct['email'] }}</strong>.</p>
                    @elseif ($acct['emailed'] === false)
                        <p class="small text-danger mb-1"><i class="ti ti-mail-off"></i> The email to {{ $acct['email'] }} couldn't be sent. Share these details yourself.</p>
                    @endif
                    <p class="text-secondary mb-0" style="font-size:.78rem;">They will set their own password the first time they sign in.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="copyCredentials" data-message="{{ $acctMessage }}">
                        <i class="ti ti-copy"></i> Copy as message
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('credentialsModal');
        if (window.bootstrap && modal) bootstrap.Modal.getOrCreateInstance(modal).show();
        const copy = document.getElementById('copyCredentials');
        copy?.addEventListener('click', function () {
            navigator.clipboard?.writeText(copy.dataset.message).then(function () {
                copy.innerHTML = '<i class="ti ti-check"></i> Copied - paste it in Messenger';
            });
        });
    });
    </script>
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

    @php $searching = request()->hasAny(['search', 'campus_id']); @endphp
    @forelse ($groups as $year => $coordinators)
        {{-- Closed until clicked; opened when searching so the matches show. --}}
        <details class="ay-group" @if ($searching) open @endif>
            <summary>
                <i class="ti ti-chevron-right ay-chev"></i>
                <span class="fw-bold">A.Y. {{ $year }}</span>
                @if ($year === $currentYear)<span class="badge rounded-pill text-bg-primary ms-1">Current</span>@endif
                <span class="text-secondary small ms-auto">{{ $coordinators->count() }} {{ \Illuminate\Support\Str::plural('coordinator', $coordinators->count()) }}</span>
            </summary>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-secondary" style="font-size: .75rem; text-transform: uppercase; letter-spacing: .03em;">
                            <th>Coordinator</th><th>Campus</th><th>Email</th><th>Program</th><th>Status</th><th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($coordinators as $coordinator)
                            @include('admin.coordinators._row')
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @empty
        <p class="text-center text-secondary py-5 mb-0">
            {{ $searching ? 'No coordinators match this search or filter.' : 'No coordinator accounts yet.' }}
        </p>
    @endforelse

    @if ($archived->isNotEmpty())
        <details class="ay-group mt-3" @if ($searching) open @endif>
            <summary>
                <i class="ti ti-chevron-right ay-chev"></i>
                <span class="fw-bold"><i class="ti ti-archive text-secondary"></i> Archived coordinators</span>
                <span class="text-secondary small ms-auto">{{ $archived->count() }} archived</span>
            </summary>
            <p class="text-secondary small px-3 pt-2 mb-1">Kept with all their records. They can't sign in until restored.</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @foreach ($archived as $coordinator)
                            <tr>
                                <td>
                                    <span class="fw-semibold d-block text-secondary">{{ $coordinator->name }}</span>
                                    <span class="text-secondary" style="font-size:.78rem;">{{ $coordinator->id_number }}</span>
                                </td>
                                <td><span class="badge rounded-pill text-bg-light border fw-bold">{{ $coordinator->campus?->code ?? '—' }}</span> <span class="small text-secondary">{{ $coordinator->campus?->name }}</span></td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.coordinators.restore', $coordinator) }}" class="d-inline"
                                          onsubmit="return confirm('Restore {{ addslashes($coordinator->name) }}? They will be able to sign in again.')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="ti ti-restore"></i> Restore</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
    <style>
        .ay-group { border: 1px solid #e6eaf0; border-radius: 10px; margin-bottom: .6rem; background: #fff; }
        .ay-group > summary { display: flex; align-items: center; gap: .5rem; padding: .75rem 1rem; cursor: pointer; list-style: none; }
        .ay-group > summary::-webkit-details-marker { display: none; }
        .ay-group > summary:hover { background: #f6f8fb; border-radius: 10px; }
        .ay-group[open] > summary { border-bottom: 1px solid #e6eaf0; border-radius: 10px 10px 0 0; }
        .ay-chev { display: inline-block; transition: transform .15s; color: #5d6b80; }
        .ay-group[open] .ay-chev { transform: rotate(90deg); }
        .ay-group .table-responsive { padding: 0 .5rem .25rem; }
    </style>
</div>
@endsection
