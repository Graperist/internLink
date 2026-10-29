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
