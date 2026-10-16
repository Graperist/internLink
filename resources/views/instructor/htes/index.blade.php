@extends('layouts.instructor')

@section('title', 'Partner Agencies - PSU Intern-Link')

@section('content')
@php $isCoordinator = auth()->user()->isCoordinator(); @endphp
<div class="il-page-header">
    <h1 class="il-page-title">Partner agencies</h1>
    @if ($isCoordinator)
        <a href="{{ route('coordinator.htes.create') }}" class="il-btn-primary text-decoration-none">
            <i class="ti ti-plus"></i> Add partner HTE
        </a>
    @endif
</div>

@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Partner Host Training Establishments</h2>
        <span class="text-secondary small">{{ $htes->total() }} total</span>
    </div>

    {{-- Search + filter --}}
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-8">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by agency name..."
                value="{{ request('search') }}"
            >
        </div>
        <div class="col-md-4">
            <select name="moa_status" class="form-select" onchange="this.form.submit()">
                <option value="">All MOA Status</option>
                <option value="pending" @selected(request('moa_status') === 'pending')>Pending</option>
                <option value="active" @selected(request('moa_status') === 'active')>Active</option>
                <option value="expired" @selected(request('moa_status') === 'expired')>Expired</option>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-search"></i> Search
            </button>
            @if (request()->hasAny(['search', 'moa_status']))
                <a href="{{ route('instructor.htes') }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                    <th>Agency</th>
                    <th>Contact person</th>
                    <th>Phone</th>
                    <th>MOA status</th>
                    <th>{{ $isCoordinator ? 'Students' : 'My students' }}</th>
                    @if ($isCoordinator)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @php
                    $moaColors = [
                        'pending' => 'text-bg-warning',
                        'active' => 'text-bg-primary',
                        'expired' => 'text-bg-danger',
                    ];
                @endphp
                @forelse ($htes as $hte)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $hte->name }}</div>
                            <div class="text-secondary" style="font-size: 0.78rem;">{{ $hte->address ?? '—' }}</div>
                        </td>
                        <td>
                            {{ $hte->contact_person ?? '—' }}
                            @if ($hte->representative_position)
                                <div class="text-secondary" style="font-size: 0.78rem;">{{ $hte->representative_position }}</div>
                            @endif
                            @if ($hte->contact_email)
                                <div class="text-secondary" style="font-size: 0.78rem;">{{ $hte->contact_email }}</div>
                            @endif
                        </td>
                        <td>{{ $hte->contact_phone ?? '—' }}</td>
                        <td>
                            <span class="badge rounded-pill {{ $moaColors[$hte->moa_status] ?? 'text-bg-light' }}">
                                {{ ucfirst($hte->moa_status) }}
                            </span>
                        </td>
                        <td>{{ $hte->my_students_count }}</td>
                        @if ($isCoordinator)
                            <td class="text-end"><a href="{{ route('coordinator.htes.edit', $hte) }}" class="small fw-semibold text-decoration-none">Edit</a></td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isCoordinator ? 6 : 5 }}" class="text-center text-secondary py-4">
                            @if (request()->hasAny(['search', 'moa_status']))
                                No agencies match this search or filter.
                            @else
                                No partner agencies have been added for your campus yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @unless ($isCoordinator)
        <p class="text-secondary small mt-3 mb-0">
            This list is view-only. Ask your OJT Coordinator to add or update an agency.
        </p>
    @endunless
</div>

<div class="mt-3">
    {{ $htes->links() }}
</div>
@endsection
