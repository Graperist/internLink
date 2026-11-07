@extends('layouts.instructor')

@section('title', 'Campus Dashboard - PSU Intern-Link')

@section('content')
{{-- The Super Admin managing a campus: its set-up and accounts (not the coordinator's to-do). --}}
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">{{ $campus->name }}</h1>
        <p class="text-secondary small mb-0">
            <span class="badge rounded-pill text-bg-light border fw-bold">{{ $campus->code }}</span>
            @if ($campus->address) <i class="ti ti-map-pin ms-1"></i> {{ $campus->address }} @endif
            &middot; Campus set-up and accounts &middot; {{ $term->label() }}
        </p>
    </div>
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
        @include('partials.term-filter', ['term' => $term, 'idPrefix' => 'campus'])
    </form>
    <a href="{{ route('admin.campuses.edit', $campus) }}" class="btn btn-outline-secondary"><i class="ti ti-pencil"></i> Edit campus</a>
</div>

<style>
    .sa-tile { display: block; text-decoration: none; color: inherit; transition: box-shadow .15s ease, transform .15s ease; height: 100%; }
    .sa-tile:hover { box-shadow: 0 .35rem .9rem rgba(0,0,0,.08); transform: translateY(-1px); color: inherit; }
    .sa-tile .n { font-size: 1.9rem; font-weight: 800; line-height: 1; }
    .sa-tile .l { color: #5d6b80; font-size: .9rem; margin-top: .35rem; }
    .sa-tile .ic { float: right; width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: #eef3fb; color: #1c3f66; font-size: 1.2rem; }
</style>

<div class="row g-3 mb-3">
    @foreach ([
        ['coordinator', route('admin.coordinators.index', ['campus_id' => $campus->id]), 'ti-user-star', $stats['coordinators']->count(), 'OJT Coordinator'.($stats['coordinators']->count() === 1 ? '' : 's')],
        ['programs', route('coordinator.programs.index'), 'ti-school', $stats['programs'], 'Programs'],
        ['instructors', route('coordinator.instructors.index'), 'ti-chalkboard', $stats['instructors'], 'Subject Instructors'],
        ['students', route('instructor.advisees', $term->query() + ($term->year === 'all' ? ['academic_year' => 'all'] : [])), 'ti-users', $stats['students'], 'Students · '.$term->label()],
        ['agencies', route('instructor.htes', $year ? array_filter(['academic_year' => $year, 'semester' => $term->semester]) : []), 'ti-building', $stats['agencies'], 'Partner agencies · '.$term->label()],
        ['hte', route('instructor.hte-accounts.index', ['academic_year' => 'all']), 'ti-user-shield', $stats['hte_accounts'], 'HTE accounts'],
    ] as [$key, $url, $icon, $number, $label])
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ $url }}" class="il-card sa-tile">
                <span class="ic"><i class="ti {{ $icon }}"></i></span>
                <div class="n">{{ $number }}</div>
                <div class="l">{{ $label }}</div>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="il-card mb-3">
            <h2 class="h6 fw-bold mb-3"><i class="ti ti-user-star text-secondary"></i> OJT Coordinator</h2>
            @forelse ($stats['coordinators'] as $coordinator)
                <div class="d-flex align-items-center gap-2 {{ ! $loop->last ? 'mb-2' : '' }}">
                    <span class="il-row-avatar">{{ \App\Models\User::initialsOf($coordinator->name) }}</span>
                    <div>
                        <div class="fw-semibold">{{ $coordinator->name }}</div>
                        <div class="text-secondary" style="font-size:.78rem;">{{ $coordinator->id_number }} &middot; {{ $coordinator->email }}</div>
                    </div>
                </div>
            @empty
                <p class="small text-danger mb-2"><i class="ti ti-alert-triangle"></i> This campus has no OJT Coordinator yet.</p>
                <a href="{{ route('admin.coordinators.create', ['campus_id' => $campus->id]) }}" class="btn btn-sm btn-primary"><i class="ti ti-plus"></i> Add OJT Coordinator</a>
            @endforelse
        </div>

        <div class="il-card">
            <h2 class="h6 fw-bold mb-3"><i class="ti ti-alert-circle text-secondary"></i> Set-up to finish</h2>
            <ul class="list-unstyled small mb-0">
                @if ($stats['programs'] === 0)
                    <li class="mb-2"><i class="ti ti-school text-danger"></i> No programs yet. <a href="{{ route('coordinator.programs.create') }}">Add a program</a></li>
                @endif
                @foreach ($stats['uncovered'] as $program)
                    <li class="mb-2"><i class="ti ti-chalkboard-off text-danger"></i> <strong>{{ $program->code }}</strong> has no Subject Instructor. <a href="{{ route('coordinator.instructors.create') }}">Add one</a></li>
                @endforeach
                @if ($stats['archived_students'] > 0)
                    <li class="mb-2"><i class="ti ti-archive text-secondary"></i> {{ $stats['archived_students'] }} archived {{ \Illuminate\Support\Str::plural('student', $stats['archived_students']) }} (kept, can't sign in).</li>
                @endif
                @if ($stats['programs'] > 0 && $stats['uncovered']->isEmpty() && $stats['coordinators']->isNotEmpty())
                    <li class="text-success"><i class="ti ti-circle-check"></i> Every program has an instructor and the campus has its coordinator.</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="il-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0"><i class="ti ti-list-details text-secondary"></i> Recent activity on this campus</h2>
                <a href="{{ route('admin.audit-trail', ['campus_id' => $campus->id]) }}" class="small fw-semibold text-decoration-none">Audit Trail &rarr;</a>
            </div>
            @forelse ($activity as $log)
                <div class="d-flex justify-content-between gap-3 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                    <div class="small">
                        <div>{{ $log->description }}</div>
                        <div class="text-secondary" style="font-size:.75rem;">{{ $log->user?->name ?? 'System' }}</div>
                    </div>
                    <div class="text-secondary text-nowrap" style="font-size:.75rem;">{{ $log->created_at?->diffForHumans() }}</div>
                </div>
            @empty
                <p class="small text-secondary mb-0">No activity recorded yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
