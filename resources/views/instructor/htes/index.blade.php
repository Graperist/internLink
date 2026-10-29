@extends('layouts.instructor')

@section('title', 'Partner Agencies - PSU Intern-Link')

@section('content')
@php
    $isCoordinator = $instructor->isCoordinator();
    $hasProgram = $instructor->canAddForPrograms();
    $termQuery = ['academic_year' => $academicYear, 'semester' => $semester];
    // The coordinator adds for the program being viewed (or picks one on the form).
    $addQuery = $termQuery + ($isCoordinator && $programId ? ['program_id' => $programId] : []);
    $moaPills = ['pending' => 'warn', 'active' => 'ok', 'expired' => 'bad'];
    $totalInterns = $listings->sum('interns_count');
@endphp
@include('partials.ui-list-styles')
<style>
    .pa-filters .form-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6c7a90; font-weight: 700; }
    .pa-meta { font-size: .76rem; color: #6b7689; }
    .pa-meta i { color: #9aa6b8; }
    .pa-slots { min-width: 96px; }
    .pa-slots .bar { height: 4px; border-radius: 99px; background: #eef1f6; overflow: hidden; margin-top: .3rem; }
    .pa-slots .bar > span { display: block; height: 100%; background: #1c3f66; }
    .pa-slots .bar > span.full { background: #1f9d3a; }
    .pa-note { background: #f8f9fb; border-radius: 10px; padding: .7rem .9rem; }
</style>

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Partner agencies</h1>
        <p class="text-secondary small mb-0">Step 1 of the internship process: the program's list of partner agencies for the term.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('instructor.reports.partner-industries', $termQuery + ['program_id' => $programId]) }}" target="_blank"
           class="btn btn-outline-secondary text-decoration-none">
            <i class="ti ti-printer"></i> Print masterlist
        </a>
        @if ($hasProgram)
            <a href="{{ route('instructor.htes.create', $addQuery) }}" class="il-btn-primary text-decoration-none">
                <i class="ti ti-plus"></i> Add partner agency
            </a>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif
@if ($errors->has('listing'))
    <div class="alert alert-danger" role="alert">{{ $errors->first('listing') }}</div>
@endif
@if (! $hasProgram && ! auth()->user()->isSuperAdmin())
    <div class="alert alert-warning small">
        To add partner agencies for the program you teach, <a href="{{ route('instructor.profile') }}" class="alert-link">set your program in My Profile</a>.
    </div>
@endif

<div class="il-card mb-3 pa-filters">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1" for="f_ay">Academic year</label>
            @include('partials.academic-year-input', ['name' => 'academic_year', 'id' => 'f_ay', 'value' => $academicYear, 'required' => true, 'autoSubmit' => true])
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1" for="f_sem">Semester</label>
            {{-- The internship is only in the 2nd Semester. --}}
            <input type="text" id="f_sem" class="form-control" value="{{ \App\Support\Term::SEMESTER }}" readonly>
            <input type="hidden" name="semester" value="{{ \App\Support\Term::SEMESTER }}">
        </div>
        @if ($isCoordinator)
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1" for="f_prog">Program</label>
                <select name="program_id" id="f_prog" class="form-select" onchange="this.form.submit()">
                    <option value="">All programs</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}" @selected((int) $programId === $program->id)>
                            {{ $program->code }}{{ $program->id === $instructor->program_id ? ' (mine)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1" for="f_search">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="ti ti-search text-secondary"></i></span>
                <input type="text" name="search" id="f_search" class="form-control border-start-0" placeholder="Agency name..." value="{{ request('search') }}">
            </div>
        </div>
        @unless ($isCoordinator)
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1" for="f_moa">MOA</label>
            <select name="moa_status" id="f_moa" class="form-select" onchange="this.form.submit()">
                <option value="">Any</option>
                @foreach (['pending' => 'Pending', 'active' => 'Active', 'expired' => 'Expired'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('moa_status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @endunless
    </form>
</div>

@php
    $short = $summary['unplaced'] !== null ? max($summary['unplaced'] - $summary['open'], 0) : 0;
@endphp
@php
    $pctPlaced = $summary['slots'] > 0 ? min(100, (int) round($summary['placed'] / $summary['slots'] * 100)) : 0;
@endphp
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="ev-stat">
            <span class="ic"><i class="ti ti-building-community"></i></span>
            <div class="n">{{ $summary['agencies'] }}</div>
            <div class="l">Partner agencies</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ev-stat">
            <span class="ic"><i class="ti ti-armchair"></i></span>
            <div class="n">{{ $summary['slots'] }}</div>
            <div class="l">Slots given{{ $summary['missing_slots'] ? ' ('.$summary['missing_slots'].' not set)' : '' }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ev-stat">
            <span class="ic"><i class="ti ti-user-check"></i></span>
            <div class="n">{{ $summary['open'] }}</div>
            <div class="l">Open slots &middot; {{ $summary['placed'] }} placed</div>
            <div class="bar"><span style="width: {{ $pctPlaced }}%"></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ev-stat {{ $short > 0 ? 'alert-tile' : '' }}">
            <span class="ic"><i class="ti ti-user-question"></i></span>
            <div class="n">{{ $summary['unplaced'] ?? '—' }}</div>
            <div class="l">{{ $isCurrentTerm ? 'Students still to place' : 'Students to place (current term only)' }}</div>
        </div>
    </div>
</div>

@if ($short > 0)
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small">
            <strong>Not enough slots.</strong> {{ $summary['unplaced'] }} {{ \Illuminate\Support\Str::plural('student', $summary['unplaced']) }}
            still {{ $summary['unplaced'] === 1 ? 'needs' : 'need' }} a placement but only {{ $summary['open'] }} {{ $summary['open'] === 1 ? 'slot is' : 'slots are' }} open
            &mdash; gather {{ $short }} more {{ \Illuminate\Support\Str::plural('slot', $short) }} from new partner agencies and add them to this list.
        </div>
        @if ($hasProgram)
            <a href="{{ route('instructor.htes.create', $addQuery) }}" class="btn btn-sm btn-warning">
                <i class="ti ti-plus"></i> Add another partner agency
            </a>
        @endif
    </div>
@endif
@if ($summary['missing_slots'] > 0)
    <div class="alert alert-light border small">
        {{ $summary['missing_slots'] }} {{ \Illuminate\Support\Str::plural('agency', $summary['missing_slots']) }} on this list
        {{ $summary['missing_slots'] === 1 ? 'has' : 'have' }} no slots recorded yet (carried over from older data). Edit them to add the slots the HTE gave.
    </div>
@endif

<div class="il-card">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div class="ev-head">
            <span class="ev-icon blue"><i class="ti ti-list-details"></i></span>
            <div>
                <h2 class="h6 fw-bold mb-0">
                    {{ $semester }}, AY {{ $academicYear }}
                    &middot;
                    @if ($isCoordinator && ! $programId)
                        All programs
                    @else
                        {{ optional($programs->firstWhere('id', $programId))->code ?? $instructor->program?->code }}
                    @endif
                </h2>
                <div class="text-secondary small">Students choose from this list on their Application for OJT.</div>
            </div>
        </div>
        <span class="ev-pill wait">
            <i class="ti ti-building"></i> {{ $listings->count() }} {{ \Illuminate\Support\Str::plural('agency', $listings->count()) }}
            &middot; {{ $totalInterns }} {{ \Illuminate\Support\Str::plural('intern', $totalInterns) }}
        </span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0 ui-table">
            <thead>
                <tr>
                    <th>Agency</th>
                    <th>Contact person</th>
                    <th>Contact number</th>
                    @if ($isCoordinator && ! $programId)<th>Program</th>@endif
                    @unless ($isCoordinator)<th>MOA</th>@endunless
                    <th class="text-center">Placed / slots</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($listings as $listing)
                    @php $hte = $listing->hte; $canManage = $instructor->canManageListing($listing); @endphp
                    <tr>
                        <td>
                            <div class="ev-agency">{{ $hte->name }}</div>
                            <div class="pa-meta"><i class="ti ti-map-pin"></i> {{ $hte->address ?? '—' }}</div>
                            @if ($listing->remarks)
                                <div class="pa-meta fst-italic"><i class="ti ti-note"></i> {{ $listing->remarks }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold small">{{ $hte->contact_person ?? '—' }}</div>
                            @if ($hte->representative_position)
                                <div class="pa-meta">{{ $hte->representative_position }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="small">@if ($hte->contact_phone)<i class="ti ti-phone text-secondary"></i> {{ $hte->contact_phone }}@else &mdash; @endif</div>
                            @if ($hte->contact_email)
                                <div class="pa-meta"><i class="ti ti-mail"></i> {{ $hte->contact_email }}</div>
                            @endif
                        </td>
                        @if ($isCoordinator && ! $programId)
                            <td><span class="ev-pill blue">{{ $listing->program->code }}</span></td>
                        @endif
                        @unless ($isCoordinator)
                            <td>
                                <span class="ev-pill {{ $moaPills[$hte->moa_status] ?? 'wait' }}">{{ ucfirst($hte->moa_status) }}</span>
                            </td>
                        @endunless
                        <td class="text-nowrap">
                            <div class="pa-slots mx-auto">
                                <div class="d-flex justify-content-between align-items-baseline gap-2">
                                    <span><span class="fw-bold">{{ $listing->interns_count }}</span>@if ($listing->slots)<span class="text-secondary small"> / {{ $listing->slots }}</span>@endif</span>
                                    @if ($listing->slots && $listing->interns_count >= $listing->slots)
                                        <span class="ev-pill ok py-0">Full</span>
                                    @endif
                                </div>
                                @if ($listing->slots)
                                    <div class="bar"><span class="{{ $listing->interns_count >= $listing->slots ? 'full' : '' }}" style="width: {{ min(100, (int) round($listing->interns_count / $listing->slots * 100)) }}%"></span></div>
                                @else
                                    <span class="ev-pill bad py-0 mt-1">slots not set</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($canManage)
                                <span class="ev-acts">
                                    <a href="{{ route('instructor.htes.edit', $listing) }}" class="ev-act" title="Edit"><i class="ti ti-pencil"></i><span class="visually-hidden">Edit</span></a>
                                    @if ($listing->interns_count === 0)
                                        <form method="POST" action="{{ route('instructor.htes.destroy', $listing) }}" class="d-inline"
                                              onsubmit="return confirm('Remove {{ addslashes($hte->name) }} from this term\'s list? The agency record itself is kept.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ev-act danger" title="Remove"><i class="ti ti-trash"></i><span class="visually-hidden">Remove</span></button>
                                        </form>
                                    @endif
                                </span>
                            @else
                                <span class="ev-pill ro" title="Entered by the {{ $listing->program->code }} instructor"><i class="ti ti-eye"></i> View only</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-5">
                            <i class="ti ti-building-community fs-2 d-block mb-1"></i>
                            @if (request()->hasAny(['search', 'moa_status']))
                                No agencies match this search or filter.
                            @else
                                <div class="mb-2">No partner agencies listed for {{ $semester }}, AY {{ $academicYear }} yet.</div>
                                @if ($hasProgram)
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <a href="{{ route('instructor.htes.create', $addQuery) }}" class="btn btn-sm btn-primary">Add the first one</a>
                                        @if ($canCopy)
                                            <form method="POST" action="{{ route('instructor.htes.copy') }}">
                                                @csrf
                                                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                                                <input type="hidden" name="semester" value="{{ $semester }}">
                                                <input type="hidden" name="program_id" value="{{ $copyProgramId }}">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Copy {{ $isCoordinator ? 'this program\'s' : 'my' }} list from {{ $previousTerm }}</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($listings->isNotEmpty() && $canCopy)
        <form method="POST" action="{{ route('instructor.htes.copy') }}" class="mt-3">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <input type="hidden" name="semester" value="{{ $semester }}">
            <input type="hidden" name="program_id" value="{{ $copyProgramId }}">
            <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">
                <i class="ti ti-copy"></i> Add any agencies from {{ $previousTerm }} that aren't on this list yet
            </button>
        </form>
    @endif

    <p class="text-secondary small mt-3 mb-0 pa-note">
        <i class="ti ti-info-circle"></i> <strong>Placed / slots</strong>: that program's students placed at the agency this term, out of the slots the HTE gave.
        Students choose from this list on their Application for OJT; a full agency can't be chosen.
        @if ($isCoordinator) As OJT Coordinator you can add, edit and remove partner agencies for every program. @endif
    </p>
</div>
@endsection
