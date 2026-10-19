@extends('layouts.hte')

@section('title', 'Evaluate Interns - PSU Intern-Link')
@section('page_title', 'Evaluate Interns')

@section('content')
<style>
    .eval-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #6c757d; white-space: nowrap; }
    .eval-done { color: #1f9d3a; font-weight: 700; letter-spacing: .02em; font-size: .8rem; }
</style>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@php
    $codes = ['EVALUATION_FORM' => ['Form 13', 'Internship Evaluation'], 'TRAINING_SUPERVISOR_FEEDBACK' => ['Form 21', "Supervisor's Feedback"]];
    $doneStatuses = \App\Support\EvaluationRouting::SUBMITTED;
@endphp

<div class="il-card p-0 overflow-hidden mb-3">
    <div class="hte-section-heading d-flex justify-content-between align-items-center gap-3">
        <span>Your Interns</span>
        <span class="text-secondary fw-normal" style="font-size:.78rem;">Click an intern's name to evaluate them. Your evaluation goes to their Subject Instructor; the intern does not see it.</span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle hte-table eval-table">
            <thead>
                <tr>
                    <th>Intern</th>
                    <th>Program</th>
                    <th>Internship</th>
                    @foreach ($codes as [$form, $title])
                        <th>{{ $form }} <span class="text-lowercase fw-normal d-block">{{ $title }}</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($deployments as $deployment)
                    @php
                        $student = $deployment->internship->student;
                        $docs = $deployment->internship->documents->keyBy('document_requirement_id');
                        $status = [];
                        foreach ($codes as $code => $_) {
                            $req = $requirements->get($code);
                            $status[$code] = $req && in_array($docs->get($req->id)?->status, $doneStatuses, true);
                        }
                        $next = collect($codes)->keys()->first(fn ($code) => $requirements->has($code) && ! $status[$code]);
                    @endphp
                    <tr>
                        <td>
                            @if ($next)
                                <a href="{{ route('hte.forms.create', [$requirements[$next], $deployment]) }}" class="fw-semibold">{{ $student->user->name }}</a>
                            @else
                                <span class="fw-semibold">{{ $student->user->name }}</span>
                            @endif
                            <div class="text-secondary" style="font-size:.76rem;">{{ $student->user->id_number }}</div>
                        </td>
                        <td>{{ $student->program->code ?? '' }}</td>
                        <td class="small text-secondary">{{ $deployment->start_date?->format('M d, Y') }} &ndash; {{ $deployment->end_date?->format('M d, Y') }}</td>
                        @foreach ($codes as $code => [$form, $title])
                            <td class="text-nowrap">
                                @if (! $requirements->has($code))
                                    &mdash;
                                @elseif ($status[$code])
                                    <span class="eval-done">EVALUATED</span>
                                @else
                                    <a href="{{ route('hte.forms.create', [$requirements[$code], $deployment]) }}" class="hte-action-btn verify text-decoration-none">Evaluate</a>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-5">No interns to evaluate yet. An intern appears here once their internship is completed.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($selfRequirement)
    <div class="il-card p-0 overflow-hidden">
        <div class="hte-section-heading d-flex justify-content-between align-items-center gap-3">
            <span>Rate Your Agency <span class="text-secondary fw-normal">(Form 23b – Self Ratee)</span></span>
            <span class="text-secondary fw-normal" style="font-size:.78rem;">Once for your agency. It goes to the Subject Instructor.</span>
        </div>
        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="fw-semibold">{{ $representative->hte->name }}</div>
            @if (\App\Support\EvaluationRouting::isSubmitted($selfEvaluation))
                <span class="eval-done">EVALUATED</span>
            @elseif ($deployments->isEmpty())
                <span class="text-secondary small">Opens once one of your interns completes their internship.</span>
            @else
                <a href="{{ route('hte.self-evaluation.create') }}" class="hte-action-btn verify text-decoration-none">Evaluate</a>
            @endif
        </div>
    </div>
@endif
@endsection
