@extends('layouts.coordinator')

@section('title', 'Reports - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Reports</h1>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="il-card text-center">
            <div class="il-stat-number">{{ $programStats->sum('total') }}</div>
            <div class="text-secondary small">Total Enrolled Interns</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="il-card text-center">
            <div class="il-stat-number">{{ $hteCount }}</div>
            <div class="text-secondary small">Partner HTEs</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="il-card text-center">
            <div class="il-stat-number">{{ $activeMoaCount }}</div>
            <div class="text-secondary small">Active MOAs</div>
        </div>
    </div>
</div>

<div class="il-card mb-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Program Summary</h2>
        <a href="{{ route('coordinator.reports.export-roster') }}" class="small fw-semibold text-decoration-none">
            <i class="ti ti-download"></i> Export Intern Roster (CSV)
        </a>
    </div>

    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Program</th>
                <th>Total interns</th>
                <th>Deployed</th>
                <th>Pending</th>
                <th>Incomplete docs</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($programStats as $row)
                <tr>
                    <td class="fw-semibold">{{ $row['program']->code }}</td>
                    <td>{{ $row['total'] }}</td>
                    <td>{{ $row['deployed'] }}</td>
                    <td>{{ $row['pending'] }}</td>
                    <td>{{ $row['incomplete'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-secondary py-4">No programs set up for this campus yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="il-card mb-3">
    <h2 class="h6 fw-bold mb-3">Generate Reports</h2>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <p class="fw-semibold small mb-1">Evaluation Summary</p>
                <p class="text-secondary small mb-3">Grades and evaluation data per student, all programs</p>
                <a href="{{ route('coordinator.reports.export-evaluations') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-download"></i> Generate &amp; Download
                </a>
            </div>
        </div>

        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <p class="fw-semibold small mb-1">HTE Directory</p>
                <p class="text-secondary small mb-3">List of all partner agencies and contacts</p>
                <a href="{{ route('coordinator.reports.export-hte') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-download"></i> Generate &amp; Download
                </a>
            </div>
        </div>

        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <p class="fw-semibold small mb-1">Documents Compliance Report</p>
                <p class="text-secondary small mb-3">Submission status of all required documents, all programs</p>
                <a href="{{ route('coordinator.reports.export-compliance') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-download"></i> Generate &amp; Download
                </a>
            </div>
        </div>
    </div>
</div>

<div class="il-card mb-3">
    <div class="d-flex justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h6 fw-bold mb-1">Approved Student Documents</h2>
            <p class="text-secondary small mb-0">Download all documents that have been approved for students in your campus as one ZIP archive.</p>
        </div>
        <a href="{{ route('coordinator.reports.export-approved-documents') }}" class="il-btn-primary text-decoration-none text-nowrap">
            <i class="ti ti-download"></i> Download Approved Documents
        </a>
    </div>
    <div class="alert alert-light border mt-3 mb-0 small">
        The ZIP is organized by student and document type. Only <strong>approved</strong> documents with an uploaded file are included; a <strong>MANIFEST.csv</strong> lists all approved records.
    </div>
</div>

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Partner HTE Summary</h2>
        <a href="{{ route('coordinator.reports.export-hte') }}" class="small fw-semibold text-decoration-none">
            <i class="ti ti-download"></i> Export HTE Partnership (CSV)
        </a>
    </div>
    <p class="text-secondary small mb-0">
        {{ $hteCount }} partner HTE{{ $hteCount === 1 ? '' : 's' }} on file,
        {{ $activeMoaCount }} with an active MOA.
        See <a href="{{ route('coordinator.htes') }}">Partner HTEs</a> for the full breakdown per establishment.
    </p>
</div>
@endsection
