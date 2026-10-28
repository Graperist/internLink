@extends('layouts.admin')

@section('title', 'Campuses - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Campuses</h1>
        <p class="text-secondary small mb-0">Every PSU campus on Intern-Link. <strong>Manage</strong> opens a campus as its OJT Coordinator, with everything the coordinator can do there.</p>
    </div>
    <a href="{{ route('admin.campuses.create') }}" class="il-btn-primary text-decoration-none"><i class="ti ti-plus"></i> Add campus</a>
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
                    <th>Code</th><th>Campus</th><th>Programs</th><th>Coordinator</th><th>Students</th><th>Status</th><th class="text-end"></th>
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
                        <td>
                            @if ($campus->coordinators_count)
                                <a href="{{ route('admin.coordinators.index', ['campus_id' => $campus->id]) }}" class="text-decoration-none">{{ $campus->coordinators_count }} {{ \Illuminate\Support\Str::plural('account', $campus->coordinators_count) }}</a>
                            @else
                                <a href="{{ route('admin.coordinators.create', ['campus_id' => $campus->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-user-plus"></i> Add coordinator</a>
                            @endif
                        </td>
                        <td>{{ $campus->students_count }}</td>
                        <td>
                            <span class="badge rounded-pill {{ $campus->status === 'active' ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ ucfirst($campus->status) }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.campuses.edit', $campus) }}" class="il-icon-btn" title="Edit campus"><i class="ti ti-pencil"></i><span class="visually-hidden">Edit</span></a>
                            @include('partials.admin-delete-button', [
                                'action' => route('admin.campuses.destroy', $campus),
                                'message' => "Delete {$campus->name}? This deletes EVERYTHING on it: its coordinator, instructors, students and HTE accounts, their documents and files, programs and partner agencies. This cannot be undone.",
                                'confirmCode' => $campus->code,
                            ])
                            @if ($campus->active_coordinator_id)
                                <form method="POST" action="{{ route('admin.campuses.manage', $campus) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary ms-1" title="Open this campus as its OJT Coordinator"><i class="ti ti-door-enter"></i> Manage</button>
                                </form>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-1" disabled title="Add an active OJT Coordinator first"><i class="ti ti-door-enter"></i> Manage</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-5">No campuses yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
