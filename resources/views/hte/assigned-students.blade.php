@extends('layouts.hte')

@section('title', 'My Assigned Interns - PSU Intern-Link')
@section('page_title', 'My Assigned Interns')

@section('content')
<div class="il-card p-0 overflow-hidden">
    <div class="hte-section-heading">My Assigned Interns</div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle hte-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Deployment Date</th>
                    <th>Hours Rendered</th>
                    <th>Required Hours</th>
                    <th>Evaluation</th>
                    <th>DTR History</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deployments as $deployment)
                    @php
                        $student = $deployment->internship->student;
                        $initials = collect(explode(' ', $student->user->name))->map(fn($p) => $p[0] ?? '')->join('');
                        $evaluation = $deployment->evaluation;
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="il-row-avatar">{{ substr($initials, 0, 2) }}</div>
                                <div>
                                    <div class="fw-semibold">{{ $student->user->name }}</div>
                                    <div class="text-secondary" style="font-size:.76rem;">{{ $student->user->id_number }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $student->program->code ?? '—' }}</td>
                        <td>{{ $deployment->start_date?->format('M d, Y') ?? '—' }}</td>
                        <td class="fw-semibold text-primary">{{ number_format($student->hours_rendered ?? 0, 0) }}h</td>
                        <td>{{ number_format($deployment->internship->required_hours ?? $student->program->required_hours ?? 0, 0) }}h</td>
                        <td>
                            @if($evaluation?->rating !== null)
                                <span class="il-status-pill approved">Submitted</span>
                            @else
                                <span class="il-status-pill pending">Not Yet Submitted</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('hte.students.dtr-history', $deployment) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="ti ti-history"></i> View History
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-5">No interns are currently assigned to your HTE.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
