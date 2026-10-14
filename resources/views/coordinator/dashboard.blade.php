@extends('layouts.coordinator')

@section('title', 'Dashboard Overview - PSU Intern-Link')

@section('content')
<style>
    .il-card-link {
        display: block;
        text-decoration: none;
        color: inherit;
        transition: box-shadow .15s ease, transform .15s ease;
    }
    .il-card-link:hover,
    .il-card-link:focus {
        box-shadow: 0 .35rem .9rem rgba(0, 0, 0, .08);
        transform: translateY(-1px);
        color: inherit;
    }
    .il-row-link {
        text-decoration: none;
        color: inherit;
    }
    .il-row-link:hover {
        color: var(--bs-primary, #1769e0);
        text-decoration: underline;
    }
    .il-action-link {
        display: block;
        text-decoration: none;
        color: inherit;
        border-radius: .375rem;
        margin: 0 -.5rem;
        padding-left: .5rem;
        padding-right: .5rem;
        transition: background-color .15s ease;
    }
    .il-action-link:hover {
        background-color: rgba(0, 0, 0, .035);
        color: inherit;
    }
</style>

<div class="il-page-header">
    <h1 class="il-page-title">Dashboard Overview</h1>
    <span class="il-page-date">{{ now()->format('F d, Y') }}</span>
</div>

{{-- Greeting banner --}}
<div class="il-hero mb-3">
    <p class="il-hero-title">Good morning, {{ auth()->user()->name }}!</p>
    <p class="il-hero-subtitle">
        You have {{ $pendingActions->count() }} pending action{{ $pendingActions->count() === 1 ? '' : 's' }}.
    </p>
</div>

{{-- Stat cards - each one links to the page that explains the number --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <a href="{{ route('coordinator.students.index') }}" class="il-card il-card-link text-center">
            <div class="il-stat-number">{{ $counts['total'] }}</div>
            <div class="text-secondary small">Total Enrolled Interns</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('coordinator.deployments.index') }}" class="il-card il-card-link text-center">
            <div class="il-stat-number">{{ $counts['deployed'] }}</div>
            <div class="text-secondary small">Deployed Interns</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('coordinator.deployments.index') }}" class="il-card il-card-link text-center">
            <div class="il-stat-number">{{ $counts['pending'] }}</div>
            <div class="text-secondary small">Pending Deployment</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('coordinator.documents') }}" class="il-card il-card-link text-center">
            <div class="il-stat-number">{{ $counts['incomplete'] }}</div>
            <div class="text-secondary small">Incomplete Documents</div>
        </a>
    </div>
</div>

<div class="row g-3">
    {{-- Recent Intern Activity --}}
    <div class="col-lg-8">
        <div class="il-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0">Recent Intern Activity</h2>
                <a href="{{ route('coordinator.students.index') }}" class="small text-decoration-none">View All &rarr;</a>
            </div>

            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase;">
                        <th>Student</th>
                        <th>Program</th>
                        <th>Host training establishment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentActivity as $student)
                        <tr>
                            <td>
                                <a href="{{ route('coordinator.students.index', ['search' => $student->user->id_number]) }}" class="il-row-link">
                                    <div class="fw-semibold">{{ $student->user->name }}</div>
                                    <div class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</div>
                                </a>
                            </td>
                            <td>{{ $student->program->code }}</td>
                            <td>{{ $student->currentInternship?->deployment?->hte?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-secondary py-4">
                                No interns yet.
                                <a href="{{ route('coordinator.students.create') }}">Add your first one</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pending Actions + Deployment Process --}}
    <div class="col-lg-4">
        <div class="il-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0">Pending Actions</h2>
                <span class="badge rounded-pill text-bg-danger">{{ $pendingActions->count() }} items</span>
            </div>

            @forelse ($pendingActions as $action)
                @if (!empty($action['route']))
                    <a href="{{ $action['route'] }}" class="il-action-link pb-2 mb-2 border-bottom">
                        <div class="small fw-semibold">{{ $action['title'] }}</div>
                        <div class="small text-secondary">{{ $action['subtitle'] }}</div>
                    </a>
                @else
                    <div class="pb-2 mb-2 border-bottom">
                        <div class="small fw-semibold">{{ $action['title'] }}</div>
                        <div class="small text-secondary">{{ $action['subtitle'] }}</div>
                    </div>
                @endif
            @empty
                <p class="small text-secondary mb-0">Nothing needs your attention right now.</p>
            @endforelse
        </div>

        <a href="{{ route('coordinator.documents') }}" class="il-card il-card-link">
            <h2 class="h6 fw-bold mb-3">Deployment Process</h2>
            <div class="d-flex justify-content-between small mb-1">
                <span>Pre-OJT Forms Compliance</span>
                <span class="text-secondary">{{ $complianceRate }}%</span>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-success" style="width: {{ $complianceRate }}%"></div>
            </div>
        </a>
    </div>
</div>
@endsection
