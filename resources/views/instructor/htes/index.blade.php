@extends('layouts.instructor')

@section('title', 'Partner Agencies - PSU Intern-Link')

@section('content')
@php
    $isCoordinator = $instructor->isCoordinator();
    $hasProgram = $instructor->program_id !== null;
    $termQuery = ['academic_year' => $academicYear, 'semester' => $semester];
    $moaColors = ['pending' => 'text-bg-warning', 'active' => 'text-bg-primary', 'expired' => 'text-bg-danger'];
    $totalInterns = $listings->sum('interns_count');
@endphp

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
            <a href="{{ route('instructor.htes.create', $termQuery) }}" class="il-btn-primary text-decoration-none">
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
@if (! $hasProgram)
    <div class="alert alert-warning small">
        To add partner agencies for the program you teach, <a href="{{ route('instructor.profile') }}" class="alert-link">set your program in My Profile</a>.
    </div>
@endif

<div class="il-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1" for="f_ay">Academic year</label>
            <select name="academic_year" id="f_ay" class="form-select" onchange="this.form.submit()">
                @foreach ($academicYears as $year)
                    <option value="{{ $year }}" @selected($year === $academicYear)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1" for="f_sem">Semester</label>
            <select name="semester" id="f_sem" class="form-select" onchange="this.form.submit()">
                @foreach ($semesters as $option)
                    <option value="{{ $option }}" @selected($option === $semester)>{{ $option }}</option>
                @endforeach
            </select>
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
        <div class="{{ $isCoordinator ? 'col-md-2' : 'col-md-4' }}">
            <label class="form-label small fw-semibold mb-1" for="f_search">Search</label>
            <input type="text" name="search" id="f_search" class="form-control" placeholder="Agency name..." value="{{ request('search') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1" for="f_moa">MOA</label>
            <select name="moa_status" id="f_moa" class="form-select" onchange="this.form.submit()">
                <option value="">Any</option>
                @foreach (['pending' => 'Pending', 'active' => 'Active', 'expired' => 'Expired'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('moa_status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </form>
</div>

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h6 fw-bold mb-0">
            {{ $semester }}, AY {{ $academicYear }}
            &middot;
            @if ($isCoordinator && ! $programId)
                All programs
            @else
                {{ optional($programs->firstWhere('id', $programId))->code ?? $instructor->program?->code }}
            @endif
        </h2>
        <span class="text-secondary small">
            {{ $listings->count() }} {{ \Illuminate\Support\Str::plural('agency', $listings->count()) }}
            &middot; {{ $totalInterns }} {{ \Illuminate\Support\Str::plural('intern', $totalInterns) }}
        </span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                    <th>Agency</th>
                    <th>Contact person</th>
                    <th>Contact number</th>
                    @if ($isCoordinator && ! $programId)<th>Program</th>@endif
                    <th>MOA</th>
                    <th class="text-center">Interns</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($listings as $listing)
                    @php $hte = $listing->hte; $canManage = $instructor->canManageListing($listing); @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $hte->name }}</div>
                            <div class="text-secondary" style="font-size: 0.78rem;">{{ $hte->address ?? '—' }}</div>
                            @if ($listing->remarks)
                                <div class="text-secondary fst-italic" style="font-size: 0.75rem;">{{ $listing->remarks }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $hte->contact_person ?? '—' }}
                            @if ($hte->representative_position)
                                <div class="text-secondary" style="font-size: 0.78rem;">{{ $hte->representative_position }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $hte->contact_phone ?? '—' }}
                            @if ($hte->contact_email)
                                <div class="text-secondary" style="font-size: 0.78rem;">{{ $hte->contact_email }}</div>
                            @endif
                        </td>
                        @if ($isCoordinator && ! $programId)
                            <td><span class="badge rounded-pill text-bg-light text-secondary">{{ $listing->program->code }}</span></td>
                        @endif
                        <td>
                            <span class="badge rounded-pill {{ $moaColors[$hte->moa_status] ?? 'text-bg-light' }}">{{ ucfirst($hte->moa_status) }}</span>
                        </td>
                        <td class="text-center">
                            <span class="fw-semibold">{{ $listing->interns_count }}</span>
                            @if ($listing->slots)
                                <span class="text-secondary small">/ {{ $listing->slots }}</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($canManage)
                                <a href="{{ route('instructor.htes.edit', $listing) }}" class="small fw-semibold text-decoration-none me-2">Edit</a>
                                @if ($listing->interns_count === 0)
                                    <form method="POST" action="{{ route('instructor.htes.destroy', $listing) }}" class="d-inline"
                                          onsubmit="return confirm('Remove {{ addslashes($hte->name) }} from this term\'s list? The agency record itself is kept.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm p-0 small text-danger text-decoration-none">Remove</button>
                                    </form>
                                @endif
                            @else
                                <span class="text-secondary small" title="Entered by the {{ $listing->program->code }} instructor"><i class="ti ti-eye"></i> View only</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-5">
                            @if (request()->hasAny(['search', 'moa_status']))
                                No agencies match this search or filter.
                            @else
                                <div class="mb-2">No partner agencies listed for {{ $semester }}, AY {{ $academicYear }} yet.</div>
                                @if ($hasProgram)
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <a href="{{ route('instructor.htes.create', $termQuery) }}" class="btn btn-sm btn-primary">Add the first one</a>
                                        @if ($canCopy)
                                            <form method="POST" action="{{ route('instructor.htes.copy') }}">
                                                @csrf
                                                <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                                                <input type="hidden" name="semester" value="{{ $semester }}">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Copy my list from {{ $previousTerm }}</button>
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
            <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">
                <i class="ti ti-copy"></i> Add any agencies from {{ $previousTerm }} that aren't on this list yet
            </button>
        </form>
    @endif

    <p class="text-secondary small mt-3 mb-0">
        <strong>Interns</strong> counts students of that program placed at the agency this term{{ $listings->contains(fn ($l) => $l->slots) ? ' (out of the slots it can take)' : '' }}.
        Students choose from this list on their Application for OJT.
        @if ($isCoordinator) You can view every program's list; add or edit entries for {{ $instructor->program?->code ?? 'the program you teach' }}. @endif
    </p>
</div>
@endsection
