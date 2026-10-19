@extends('layouts.instructor')

@section('title', 'Evaluations - PSU Intern-Link')

@section('content')
<style>
    .eval-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #6c757d; white-space: nowrap; }
    .eval-done { color: #1f9d3a; font-weight: 700; letter-spacing: .02em; font-size: .8rem; }
    .eval-wait { color: #8a94a6; font-size: .8rem; }
    .eval-row:target > td { background: #fff7d6; }
    .eval-row { scroll-margin-top: 90px; }
</style>
@php
    $short = fn ($code) => \App\Support\EvaluationRouting::FORMS[$code]['form'];
    $titles = [
        'EVALUATION_FORM' => 'Internship Evaluation',
        'TRAINING_SUPERVISOR_FEEDBACK' => "Supervisor's Feedback",
        'STUDENT_HTE_EVALUATION' => 'Evaluation of the Agency',
        'STUDENT_FEEDBACK_FORM' => "Intern's Feedback",
    ];
@endphp

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Evaluations</h1>
        <p class="text-secondary small mb-0">Evaluations open once an internship is completed. Whoever is rated never sees the rating &mdash; every evaluation comes to you.</p>
    </div>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

{{-- ============ Evaluate the partner agencies (Form 23a), like the faculty evaluation ============ --}}
<div class="il-card mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
        <h2 class="h6 fw-bold mb-0">Evaluate Partner Agencies <span class="text-secondary fw-normal">(Form 23a)</span></h2>
        <span class="small text-secondary">{{ $agencyRows->filter(fn ($r) => $r->mine)->count() }} of {{ $agencyRows->count() }} evaluated</span>
    </div>
    <p class="text-secondary small mb-3">The agencies where your students finished their internship. Click an agency's name to evaluate it &mdash; once per agency. The agency does not see your evaluation.</p>

    <div class="table-responsive">
        <table class="table align-middle mb-0 eval-table">
            <thead>
                <tr><th>Agency</th><th>Your interns there</th><th>Status</th><th>Agency's self-rating <span class="text-lowercase fw-normal">(Form 23b)</span></th></tr>
            </thead>
            <tbody>
                @forelse ($agencyRows as $row)
                    <tr>
                        <td>
                            @if ($row->mine)
                                <a href="{{ route('instructor.evaluations.show', $row->mine) }}" target="_blank" class="fw-semibold text-decoration-none">{{ $row->hte->name }}</a>
                            @else
                                <a href="{{ route('instructor.agency-evaluations.create', $row->hte) }}" class="fw-semibold">{{ $row->hte->name }}</a>
                            @endif
                            <div class="text-secondary" style="font-size:.76rem;">{{ $row->hte->address }}</div>
                        </td>
                        <td class="small">{{ $row->deployments->map(fn ($d) => $d->internship->student->user->name)->join(', ') }}</td>
                        <td class="text-nowrap">
                            @if ($row->mine)
                                <span class="eval-done">EVALUATED</span>
                                <div class="mt-1">
                                    <a href="{{ route('instructor.evaluations.show', $row->mine) }}" target="_blank" class="small me-2"><i class="ti ti-eye"></i> View</a>
                                    <a href="{{ route('instructor.evaluations.show', ['document' => $row->mine, 'download' => 1]) }}" target="_blank" class="small"><i class="ti ti-download"></i> Download</a>
                                </div>
                            @else
                                <a href="{{ route('instructor.agency-evaluations.create', $row->hte) }}" class="btn btn-sm btn-primary"><i class="ti ti-star"></i> Evaluate</a>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if ($row->self)
                                <span class="eval-done">RECEIVED</span>
                                <div class="mt-1">
                                    <a href="{{ route('instructor.evaluations.show', $row->self) }}" target="_blank" class="small me-2"><i class="ti ti-eye"></i> View</a>
                                    <a href="{{ route('instructor.evaluations.show', ['document' => $row->self, 'download' => 1]) }}" target="_blank" class="small"><i class="ti ti-download"></i> Download</a>
                                </div>
                            @else
                                <span class="eval-wait">Waiting for the agency</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-4">No agency to evaluate yet &mdash; this opens once one of your students completes their internship.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ============ Received about / from each intern ============ --}}
<div class="il-card" id="evaluations-received">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
        <h2 class="h6 fw-bold mb-0">Evaluations Received</h2>
        <span class="small text-secondary">{{ $internRows->count() }} {{ \Illuminate\Support\Str::plural('intern', $internRows->count()) }}</span>
    </div>
    <p class="text-secondary small mb-3">What each agency submitted about your intern (Forms 13 and 21), and what each intern submitted about their agency (Forms 23c and 20). The intern and the agency do not see each other's evaluations.</p>

    <div class="table-responsive">
        <table class="table align-middle mb-0 eval-table">
            <thead>
                <tr>
                    <th rowspan="2" class="align-bottom">Intern</th>
                    <th colspan="2" class="text-center border-bottom-0">From the agency</th>
                    <th colspan="2" class="text-center border-bottom-0">From the intern</th>
                </tr>
                <tr>
                    @foreach ($internCodes as $code)
                        <th>{{ $short($code) }} <span class="text-lowercase fw-normal d-block">{{ $titles[$code] }}</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($internRows as $row)
                    <tr id="student-{{ $row->student->id }}" class="eval-row">
                        <td>
                            <div class="fw-semibold">{{ $row->student->user->name }}</div>
                            <div class="text-secondary" style="font-size:.76rem;">{{ $row->student->user->id_number }} &middot; {{ $row->deployment->hte?->name }}</div>
                        </td>
                        @foreach ($internCodes as $code)
                            @php $doc = $row->forms[$code]; @endphp
                            <td class="text-nowrap">
                                @if ($doc)
                                    <span class="eval-done">RECEIVED</span>
                                    <div class="mt-1">
                                        <a href="{{ route('instructor.evaluations.show', $doc) }}" target="_blank" class="small me-2"><i class="ti ti-eye"></i> View</a>
                                        <a href="{{ route('instructor.evaluations.show', ['document' => $doc, 'download' => 1]) }}" target="_blank" class="small" title="Download"><i class="ti ti-download"></i></a>
                                    </div>
                                @elseif (! isset($requirements[$code]))
                                    <span class="eval-wait">&mdash;</span>
                                @else
                                    <span class="eval-wait">Waiting</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">No completed internships yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
