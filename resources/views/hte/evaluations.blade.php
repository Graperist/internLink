@extends('layouts.hte')

@section('title', 'Evaluations - PSU Intern-Link')
@section('page_title', 'Evaluations')

@section('content')
<style>
    .eval-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #6c757d; white-space: nowrap; }
    .eval-done { display: inline-flex; align-items: center; gap: .3rem; background: #e7f6ec; color: #1d6b3a; font-weight: 700; letter-spacing: .02em; font-size: .72rem; border-radius: 999px; padding: .22rem .65rem; }
    .eval-table thead .ev-sub { display: block; text-transform: none; letter-spacing: 0; font-weight: 500; color: #8a94a6; font-size: .72rem; }
    .hte-agency { border: 1px solid #e6eaf0; border-radius: 12px; background: #f8f9fb; }
    .hte-flash { border: 0; border-left: 4px solid #1f9d3a; background: #eefaf2; color: #14532d; border-radius: 10px; }
    .hte-status { display: inline-flex; align-items: center; gap: .35rem; }
    .hte-count { font-size: .7rem; font-weight: 700; border-radius: 999px; padding: .1rem .5rem; margin-left: .35rem; }
    .hte-count.done { background: #e7f6ec; color: #1d6b3a; }
    .hte-count.todo { background: #fff3d6; color: #8a5d00; }
</style>
@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 hte-flash" role="status">
        <i class="ti ti-circle-check fs-5"></i><span>{{ session('status') }}</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@php
    $codes = ['EVALUATION_FORM' => ['Form 13', 'Internship Evaluation'], 'TRAINING_SUPERVISOR_FEEDBACK' => ['Form 21', "Supervisor's Feedback"]];
    $doneStatuses = \App\Support\EvaluationRouting::SUBMITTED;
    // For the tiles: how many of the interns' forms are in, and the agency's self-rating.
    $activeCodes = collect($codes)->keys()->filter(fn ($code) => $requirements->has($code));
    $formsTotal = $deployments->count() * $activeCodes->count();
    $formsDone = $deployments->sum(fn ($d) => $activeCodes->filter(fn ($code) => in_array($d->internship->documents->firstWhere('document_requirement_id', $requirements[$code]->id)?->status, $doneStatuses, true))->count());
    $internsDone = $deployments->filter(fn ($d) => $activeCodes->every(fn ($code) => in_array($d->internship->documents->firstWhere('document_requirement_id', $requirements[$code]->id)?->status, $doneStatuses, true)))->count();
    $selfDone = $selfRequirement && \App\Support\EvaluationRouting::isSubmitted($selfEvaluation);
    $pct = $formsTotal > 0 ? (int) round($formsDone / $formsTotal * 100) : 0;
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="il-card d-flex align-items-center gap-3 py-3 h-100">
            <span class="il-tile-icon"><i class="ti ti-users"></i></span>
            <div><div class="il-stat-number">{{ $internsDone }} <span class="fs-6 text-secondary fw-semibold">/ {{ $deployments->count() }}</span></div><div class="text-secondary small">Interns fully evaluated</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="il-card py-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <span class="il-tile-icon"><i class="ti ti-clipboard-check"></i></span>
                <div><div class="il-stat-number">{{ $formsDone }} <span class="fs-6 text-secondary fw-semibold">/ {{ $formsTotal }}</span></div><div class="text-secondary small">Forms 13 &amp; 21 submitted</div></div>
            </div>
            <div class="progress mt-2" style="height:4px;"><div class="progress-bar bg-success" style="width: {{ $pct }}%"></div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="il-card d-flex align-items-center gap-3 py-3 h-100">
            <span class="il-tile-icon"><i class="ti ti-building-community"></i></span>
            <div><div class="il-stat-number fs-5">{{ $selfDone ? 'Done' : 'Pending' }}</div><div class="text-secondary small">Agency self-rating (Form 23b)</div></div>
        </div>
    </div>
</div>

<div class="il-card mb-3">
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="il-tile-icon sm"><i class="ti ti-users"></i></span>
        <div>
            <h2 class="h6 fw-bold mb-0">Your Interns</h2>
            <div class="text-secondary small">Click an intern's name to evaluate them. Your evaluation goes to their Subject Instructor; the intern does not see it.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0 eval-table">
            <thead>
                <tr>
                    <th>Intern</th>
                    <th>Program</th>
                    <th>Internship</th>
                    @foreach ($codes as [$form, $title])
                        <th>{{ $form }} <span class="ev-sub">{{ $title }}</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($deployments as $deployment)
                    @php
                        $student = $deployment->internship->student;
                        $docs = $deployment->internship->documents->keyBy('document_requirement_id');
                        $status = [];
                        $docFor = [];
                        foreach ($codes as $code => $_) {
                            $req = $requirements->get($code);
                            $status[$code] = $req && in_array($docs->get($req->id)?->status, $doneStatuses, true);
                            $docFor[$code] = $req ? $docs->get($req->id) : null;
                        }
                        // The name opens the next form to fill, or the first one to edit once all are done.
                        $next = collect($codes)->keys()->first(fn ($code) => $requirements->has($code) && ! $status[$code])
                            ?? collect($codes)->keys()->first(fn ($code) => $requirements->has($code) && \App\Support\EvaluationEditWindow::canEdit($docFor[$code]));
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="il-avatar-sm">{{ \App\Models\User::initialsOf($student->user->name) }}</span>
                                <div>
                                    @if ($next)
                                        <a href="{{ route('hte.forms.create', [$requirements[$next], $deployment]) }}" class="fw-semibold text-decoration-none">{{ $student->user->name }}</a>
                                    @else
                                        <span class="fw-semibold">{{ $student->user->name }}</span>
                                    @endif
                                    @php $doneCount = collect($status)->filter()->count(); $ofCount = $activeCodes->count(); @endphp
                                    <span class="hte-count {{ $doneCount >= $ofCount ? 'done' : 'todo' }}">{{ $doneCount }}/{{ $ofCount }}</span>
                                    <div class="text-secondary" style="font-size:.76rem;">{{ $student->user->id_number }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge rounded-pill text-bg-light">{{ $student->program->code ?? '' }}</span></td>
                        <td class="small text-secondary"><i class="ti ti-calendar"></i> {{ $deployment->start_date?->format('M d, Y') }} &ndash; {{ $deployment->end_date?->format('M d, Y') }}</td>
                        @foreach ($codes as $code => [$form, $title])
                            <td class="text-nowrap">
                                @if (! $requirements->has($code))
                                    &mdash;
                                @elseif ($status[$code])
                                    <span class="hte-status">
                                        <span class="eval-done"><i class="ti ti-circle-check"></i> EVALUATED</span>
                                        <a href="{{ route('hte.evaluations.view', $docFor[$code]) }}" class="il-icon-btn" title="View your evaluation" target="_blank"><i class="ti ti-eye"></i><span class="visually-hidden">View</span></a>
                                        @if (\App\Support\EvaluationEditWindow::canEdit($docFor[$code]))
                                            <a href="{{ route('hte.forms.create', [$requirements[$code], $deployment]) }}" class="il-icon-btn" title="Edit your answers"><i class="ti ti-pencil"></i><span class="visually-hidden">Edit</span></a>
                                        @else
                                            <span class="il-icon-btn" title="{{ \App\Support\EvaluationEditWindow::note($docFor[$code]) }}" style="background:#fff3d6;border-color:#f3d38a;color:#8a5d00;"><i class="ti ti-lock"></i></span>
                                        @endif
                                    </span>
                                    <div class="text-secondary mt-1" style="font-size:.7rem;">{{ \App\Support\EvaluationEditWindow::note($docFor[$code]) }}</div>
                                @else
                                    <a href="{{ route('hte.forms.create', [$requirements[$code], $deployment]) }}" class="btn btn-sm btn-primary text-decoration-none"><i class="ti ti-star"></i> Evaluate</a>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-5"><i class="ti ti-clipboard-list fs-2 d-block mb-1"></i>No interns to evaluate yet. An intern appears here once their internship is completed.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($selfRequirement)
    <div class="il-card">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="il-tile-icon sm"><i class="ti ti-building-community"></i></span>
            <div>
                <h2 class="h6 fw-bold mb-0">Rate Your Agency <span class="text-secondary fw-normal">(Form 23b – Self Ratee)</span></h2>
                <div class="text-secondary small">Once for your agency. It goes to the Subject Instructor.</div>
            </div>
        </div>
        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2 hte-agency">
            <div>
                <div class="fw-semibold"><i class="ti ti-building text-secondary"></i> {{ $representative->hte->name }}</div>
                @if ($representative->hte->address)<div class="text-secondary" style="font-size:.76rem;"><i class="ti ti-map-pin"></i> {{ $representative->hte->address }}</div>@endif
            </div>
            @if (\App\Support\EvaluationRouting::isSubmitted($selfEvaluation))
                <span class="text-end">
                    <span class="hte-status">
                        <span class="eval-done"><i class="ti ti-circle-check"></i> EVALUATED</span>
                        <a href="{{ route('hte.evaluations.view', $selfEvaluation) }}" class="il-icon-btn" title="View your evaluation" target="_blank"><i class="ti ti-eye"></i><span class="visually-hidden">View</span></a>
                        @if (\App\Support\EvaluationEditWindow::canEdit($selfEvaluation))
                            <a href="{{ route('hte.self-evaluation.create') }}" class="il-icon-btn" title="Edit your answers"><i class="ti ti-pencil"></i><span class="visually-hidden">Edit</span></a>
                        @else
                            <span class="il-icon-btn" title="{{ \App\Support\EvaluationEditWindow::note($selfEvaluation) }}" style="background:#fff3d6;border-color:#f3d38a;color:#8a5d00;"><i class="ti ti-lock"></i></span>
                        @endif
                    </span>
                    <div class="text-secondary mt-1" style="font-size:.7rem;">{{ \App\Support\EvaluationEditWindow::note($selfEvaluation) }}</div>
                </span>
            @elseif ($deployments->isEmpty())
                <span class="text-secondary small">Opens once one of your interns completes their internship.</span>
            @else
                <a href="{{ route('hte.self-evaluation.create') }}" class="btn btn-sm btn-primary text-decoration-none"><i class="ti ti-star"></i> Evaluate</a>
            @endif
        </div>
    </div>
@endif
@endsection
