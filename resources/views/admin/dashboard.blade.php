@extends('layouts.admin')

@section('title', 'Dashboard - PSU Intern-Link')

@section('content')
@php
    $tiles = [
        ['ti-building-community', $totals['campuses'], 'Active campuses'],
        ['ti-users', $totals['students'], 'Student interns'],
        ['ti-briefcase', $totals['deployed'], 'Deployed now'],
        ['ti-circle-check', $totals['completed'], 'Completed'],
        ['ti-file-search', $totals['waiting'], 'Documents waiting for review'],
    ];
@endphp
<style>
    .ad-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
    .ad-tile { display: flex; align-items: center; gap: .85rem; margin: 0; }
    .ad-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #5d6b80; white-space: nowrap; }
    .ad-bar { height: 6px; background: #e9edf2; border-radius: 999px; overflow: hidden; min-width: 90px; }
    .ad-bar span { display: block; height: 100%; background: #1c3f66; }
    .ad-num { font-variant-numeric: tabular-nums; font-weight: 600; }
    .ad-inactive td { color: #8a94a6; }
</style>

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Dashboard</h1>
        <p class="text-secondary small mb-0">All campuses at a glance. <strong>Manage</strong> gives you the OJT Coordinator's access to a campus.</p>
    </div>
    <span class="text-secondary small">{{ now()->format('F j, Y') }}</span>
</div>

<div class="ad-tiles">
    @foreach ($tiles as [$icon, $value, $label])
        <div class="il-card ad-tile">
            <span class="il-tile-icon"><i class="ti {{ $icon }}"></i></span>
            <div>
                <div class="il-stat-number">{{ number_format($value) }}</div>
                <div class="text-secondary small">{{ $label }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">By campus</h2>
        <a href="{{ route('admin.campuses.index') }}" class="small fw-semibold text-decoration-none">Manage campuses &rarr;</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 ad-table">
            <thead>
                <tr>
                    <th>Campus</th><th>Coordinator</th><th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($campuses as $row)
                    @php $campus = $row->campus; @endphp
                    <tr class="{{ $campus->status === 'active' ? '' : 'ad-inactive' }}">
                        <td>
                            <span class="badge rounded-pill text-bg-light border fw-bold me-1">{{ $campus->code }}</span>
                            <span class="fw-semibold">{{ $campus->name }}</span>
                            @if ($campus->status !== 'active')<span class="badge rounded-pill text-bg-secondary ms-1">Inactive</span>@endif
                        </td>
                        <td class="small">
                            @if ($row->coordinator)
                                {{ $row->coordinator->name }}
                            @else
                                <a href="{{ route('admin.coordinators.create', ['campus_id' => $campus->id]) }}" class="text-decoration-none">Add coordinator</a>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <form method="POST" action="{{ route('admin.campuses.manage', $campus) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="ti ti-door-enter"></i> Manage</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-secondary py-5">No campuses yet. <a href="{{ route('admin.campuses.create') }}">Add one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
