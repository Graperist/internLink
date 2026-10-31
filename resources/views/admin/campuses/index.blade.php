@extends('layouts.admin')

@section('title', 'Campuses - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Campuses</h1>
        <p class="text-secondary small mb-0">Every PSU campus on Intern-Link. <strong>Manage</strong> gives you the OJT Coordinator's access to that campus, under your own account.</p>
    </div>
    <a href="{{ route('admin.campuses.create') }}" class="il-btn-primary text-decoration-none"><i class="ti ti-plus"></i> Add</a>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">All Campuses</h2>
        <span class="text-secondary small">{{ $campuses->count() }} total</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="text-secondary" style="font-size: .75rem; text-transform: uppercase; letter-spacing: .03em;">
                    <th>Code</th><th>Campus</th><th>Programs</th><th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($campuses as $campus)
                    <tr>
                        <td><span class="badge rounded-pill text-bg-light border fw-bold">{{ $campus->code }}</span></td>
                        <td>
                            <div class="fw-semibold">{{ $campus->name }}</div>
                            @if ($campus->address)<div class="text-secondary" style="font-size:.78rem;"><i class="ti ti-map-pin"></i> {{ $campus->address }}</div>@endif
                        </td>
                        <td>{{ $campus->programs_count }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.campuses.edit', $campus) }}" class="il-icon-btn" title="Edit campus"><i class="ti ti-pencil"></i><span class="visually-hidden">Edit</span></a>
<form method="POST" action="{{ route('admin.campuses.archive', $campus) }}" class="d-inline"
                                  onsubmit="return confirm('Archive {{ addslashes($campus->name) }}? Nothing is deleted: its records are kept, but its coordinator, instructors, students and HTEs can no longer sign in. You can restore it any time.')">
                                @csrf
                                <button type="submit" class="il-icon-btn" title="Archive"><i class="ti ti-archive"></i><span class="visually-hidden">Archive</span></button>
                            </form>
                            <form method="POST" action="{{ route('admin.campuses.manage', $campus) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary ms-1" title="Work in this campus with the OJT Coordinator's access"><i class="ti ti-door-enter"></i> Manage</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-5">No campuses yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($archived->isNotEmpty())
<details class="il-card mt-3 ar-card">
    <summary class="d-flex justify-content-between align-items-center">
        <span class="h6 fw-bold mb-0"><i class="ti ti-archive text-secondary"></i> Archived campuses</span>
        <span class="text-secondary small">{{ $archived->count() }} archived</span>
    </summary>
    <p class="text-secondary small mt-2 mb-2">Kept with all their records. Their accounts can't sign in until the campus is restored.</p>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <tbody>
                @foreach ($archived as $campus)
                    <tr>
                        <td style="width: 70px;"><span class="badge rounded-pill text-bg-light border fw-bold">{{ $campus->code }}</span></td>
                        <td class="text-secondary">{{ $campus->name }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.campuses.restore', $campus) }}" class="d-inline"
                                  onsubmit="return confirm('Restore {{ addslashes($campus->name) }}? Its accounts will be able to sign in again.')">
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
<style>.ar-card > summary { cursor: pointer; list-style: none; } .ar-card > summary::-webkit-details-marker { display: none; }</style>
@endif
@endsection
