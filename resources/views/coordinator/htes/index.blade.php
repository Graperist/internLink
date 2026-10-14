@extends('layouts.coordinator')

@section('title', 'Partner HTEs - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Partner HTEs</h1>
    <a href="{{ route('coordinator.htes.create') }}" class="il-btn-primary text-decoration-none">
        <i class="ti ti-plus"></i> Add HTE
    </a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
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
                placeholder="Search by HTE name..."
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
                <a href="{{ route('coordinator.htes') }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Host training establishment</th>
                <th>Contact person</th>
                <th>MOA status</th>
                <th>Active interns</th>
                <th>Total interns placed</th>
                <th></th>
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
                        @if ($hte->contact_email)
                            <div class="text-secondary" style="font-size: 0.78rem;">{{ $hte->contact_email }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge rounded-pill {{ $moaColors[$hte->moa_status] ?? 'text-bg-light' }}">
                            {{ ucfirst($hte->moa_status) }}
                        </span>
                    </td>
                    <td>{{ $hte->active_interns_count }}</td>
                    <td>{{ $hte->total_interns_count }}</td>
                    <td class="text-end">
                        <a href="{{ route('coordinator.htes.edit', $hte) }}" class="btn btn-sm btn-outline-secondary">
                            Edit
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-secondary py-4">
                        @if (request()->hasAny(['search', 'moa_status']))
                            No HTEs match this search or filter.
                        @else
                            No partner HTEs yet. Click "Add HTE" to create the first one, or assign one from the Deployment screen.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-3">
    {{ $htes->links() }}
</div>
@endsection
