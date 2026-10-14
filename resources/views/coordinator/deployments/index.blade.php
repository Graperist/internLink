@extends('layouts.coordinator')

@section('title', 'Deployment Management - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Deployment Management</h1>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="il-card">
    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Student</th>
                <th>Program</th>
                <th>Host training establishment</th>
                <th>Pre-OJT clearance</th>
                <th>Deployment status</th>
                <th>Dates</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                @php
                    $deployment = $student->currentInternship?->deployment;
                    $hte = $deployment?->hte;
                    $statusColors = [
                        'pending' => 'text-bg-warning',
                        'active' => 'text-bg-success',
                        'completed' => 'text-bg-secondary',
                        'terminated' => 'text-bg-danger',
                    ];
                    $internship = $student->currentInternship;
                    $preOjtComplete = $internship ? \App\Support\PreDeployment::isComplete($internship) : false;
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $student->user->name }}</div>
                        <div class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</div>
                    </td>
                    <td>{{ $student->program->code }}</td>
                    <td>{{ $hte->name ?? '—' }}</td>
                    <td>
                        @if ($preOjtComplete)
                            <span class="badge rounded-pill text-bg-success">Cleared</span>
                        @else
                            <span class="badge rounded-pill text-bg-warning">Requirements pending</span>
                        @endif
                    </td>
                    <td>
                        @if ($deployment)
                            <span class="badge rounded-pill {{ $statusColors[$deployment->status] ?? 'text-bg-light' }}">
                                {{ ucfirst($deployment->status) }}
                            </span>
                        @else
                            <span class="badge rounded-pill text-bg-light text-secondary">Not deployed</span>
                        @endif
                    </td>
                    <td class="text-secondary" style="font-size: 0.82rem;">
                        {{ $deployment?->start_date?->format('M d, Y') ?? '—' }}
                        &ndash;
                        {{ $deployment?->end_date?->format('M d, Y') ?? '—' }}
                    </td>
                    <td class="text-end">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#assignModal"
                            data-student-id="{{ $student->id }}"
                            data-student-name="{{ $student->user->name }}"
                            data-hte-id="{{ $hte->id ?? '' }}"
                            data-start-date="{{ $deployment?->start_date?->format('Y-m-d') }}"
                            data-end-date="{{ $deployment?->end_date?->format('Y-m-d') }}"
                            data-status="{{ $deployment->status ?? 'pending' }}"
                        >
                            {{ $deployment ? 'Edit' : 'Assign' }}
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-secondary py-4">
                        No interns yet. Add interns from the Intern List page first.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-3">
    {{ $students->links() }}
</div>

{{-- Shared assign/edit modal - populated per-row via the data-* attributes above --}}
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="assignForm" action="">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold" id="assignModalLabel">Assign deployment</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small fw-semibold">Host training establishment</label>
                    <select name="hte_id" id="hte_id" class="form-select mb-2">
                        <option value="">-- Select existing HTE --</option>
                        @foreach ($htes as $hte)
                            <option value="{{ $hte->id }}">{{ $hte->name }}</option>
                        @endforeach
                    </select>

                    <div class="small text-secondary mb-1">Or add a new one:</div>
                    <input type="text" name="new_hte_name" class="form-control mb-2" placeholder="New HTE name">
                    <input type="text" name="new_hte_address" class="form-control mb-3" placeholder="New HTE address (optional)">

                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label small fw-semibold">Start date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label small fw-semibold">End date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control">
                        </div>
                    </div>

                    <label class="form-label small fw-semibold">Deployment status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="active">Activate (requires pre-OJT clearance)</option>
                        <option value="terminated">Terminated</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save deployment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('assignModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const studentId = button.getAttribute('data-student-id');
        const studentName = button.getAttribute('data-student-name');

        document.getElementById('assignModalLabel').textContent = 'Assign deployment — ' + studentName;
        document.getElementById('assignForm').action = '/coordinator/deployments/' + studentId;

        document.getElementById('hte_id').value = button.getAttribute('data-hte-id') || '';
        document.getElementById('start_date').value = button.getAttribute('data-start-date') || '';
        document.getElementById('end_date').value = button.getAttribute('data-end-date') || '';
        document.getElementById('status').value = button.getAttribute('data-status') || 'pending';
    });
</script>
@endsection
