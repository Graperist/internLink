@extends('layouts.admin')

@section('title', 'Campuses - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Campuses</h1>
        <p class="text-secondary small mb-0">Every PSU campus on Intern-Link. <strong>Manage</strong> gives you the OJT Coordinator's access to that campus, under your own account.</p>
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
                            @include('partials.admin-delete-button', [
                                'action' => route('admin.campuses.destroy', $campus),
                                'message' => "Delete {$campus->name}? This deletes EVERYTHING on it: its coordinator, instructors, students and HTE accounts, their documents and files, programs and partner agencies. This cannot be undone.",
                                'confirmCode' => $campus->code,
                            ])
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
@endsection
