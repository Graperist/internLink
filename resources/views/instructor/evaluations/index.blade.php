@extends('layouts.instructor')

@section('title', 'Evaluations - PSU Intern-Link')

@section('content')
@include('partials.ui-list-styles')
<style>
    .eval-row:target > td { background: #fff7d6 !important; }
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
    $toEvaluate = $agencyRows->filter(fn ($r) => $r->canEvaluate || $r->mine)->count();
    $evaluated = $agencyRows->filter(fn ($r) => $r->mine)->count();
    $selfReceived = $agencyRows->filter(fn ($r) => $r->self)->count();
    $formsExpected = $internRows->count() * collect($internCodes)->filter(fn ($c) => isset($requirements[$c]))->count();
    $formsReceived = $internRows->sum(fn ($r) => $r->forms->filter()->count());
    $pct = fn ($n, $of) => $of > 0 ? (int) round($n / $of * 100) : 0;
    $initials = fn ($name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp

<div class="il-page-header d-flex justify-content-between align-items-end flex-wrap gap-3">
    <div>
        <h1 class="il-page-title">Evaluations</h1>
        <p class="text-secondary small mb-0">They open once an internship is completed. Whoever is rated never sees the rating &mdash; every evaluation comes to you.</p>
    </div>
    @if ($isCoordinator)
        {{-- The OJT Coordinator sees every program's evaluations. --}}
        <form method="GET" action="{{ route('instructor.evaluations') }}" class="d-flex align-items-center gap-2">
            <label for="evalProgram" class="small fw-semibold text-secondary mb-0 text-nowrap"><i class="ti ti-filter"></i> Program</label>
            <select name="program_id" id="evalProgram" class="form-select form-select-sm" style="min-width:230px;" onchange="this.form.submit()">
                <option value="">All programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected($programId === $program->id)>{{ $program->code }} &ndash; {{ $program->name }}</option>
                @endforeach
            </select>
        </form>
    @endif
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

{{-- At a glance --}}
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="ev-stat">
            <div class="n">{{ $evaluated }} <small>/ {{ $toEvaluate }}</small></div>
            <div class="l">Agencies you evaluated (Form 23a)</div>
            <div class="bar"><span style="width: {{ $pct($evaluated, $toEvaluate) }}%"></span></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ev-stat">
            <div class="n">{{ $selfReceived }} <small>/ {{ $agencyRows->count() }}</small></div>
            <div class="l">Agency self-ratings received (Form 23b)</div>
            <div class="bar"><span style="width: {{ $pct($selfReceived, $agencyRows->count()) }}%"></span></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ev-stat">
            <div class="n">{{ $formsReceived }} <small>/ {{ $formsExpected }}</small></div>
            <div class="l">Intern evaluations received (Forms 13, 21, 23c, 20)</div>
            <div class="bar"><span style="width: {{ $pct($formsReceived, $formsExpected) }}%"></span></div>
        </div>
    </div>
</div>

@if ($instructor->program && ! $isCoordinator)
    {{-- The editing deadline: the Subject Instructor's, for their program (not the coordinator's). --}}
    <form method="POST" action="{{ route('instructor.evaluations.edit-days') }}" class="il-card mb-3 py-3">
        @csrf @method('PUT')
        <div class="d-flex align-items-center flex-wrap gap-3">
            <span class="ev-icon gold"><i class="ti ti-clock-edit"></i></span>
            <div class="flex-grow-1" style="min-width: 260px;">
                <div class="fw-bold small">Editing deadline &middot; {{ $instructor->program->code }}</div>
                <div class="text-secondary" style="font-size:.78rem;">Students and HTEs can change a submitted evaluation until this many days after the internship ends. Lock one sooner with <i class="ti ti-lock-open"></i>.</div>
            </div>
            <div class="input-group input-group-sm" style="width: 120px;">
                <input type="number" name="evaluation_edit_days" id="evalEditDays" class="form-control" min="0" max="90" required aria-label="Days to edit after the internship ends"
                       value="{{ old('evaluation_edit_days', $instructor->program->evaluation_edit_days ?? 7) }}">
                <span class="input-group-text">days</span>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Save</button>
        </div>
    </form>
@endif

{{-- ============ Evaluate the partner agencies (Form 23a), like the faculty evaluation ============ --}}
<div class="il-card mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div class="ev-head">
            <span class="ev-icon blue"><i class="ti ti-building-community"></i></span>
            <div>
                <h2 class="h6 fw-bold mb-0">Evaluate Partner Agencies <span class="text-secondary fw-normal">(Form 23a)</span></h2>
                <div class="text-secondary small">
                    Once per agency where {{ $isCoordinator ? ($programId ? 'this program\'s' : 'any program\'s') : 'your' }} students finished. The agency does not see it.
                    @if ($isCoordinator) You evaluate only the agencies of your program{{ auth()->user()->instructor?->program ? ' ('.auth()->user()->instructor->program->code.')' : '' }}. @endif
                </div>
            </div>
        </div>
        <span class="ev-pill {{ $toEvaluate > 0 && $evaluated >= $toEvaluate ? 'ok' : 'wait' }}">{{ $evaluated }} of {{ $toEvaluate }} evaluated</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0 ui-table eval-table">
            <thead>
                <tr><th>Agency</th><th>{{ $isCoordinator ? 'Interns there' : 'Your interns there' }}</th><th>Your evaluation</th><th>Agency's self-rating <span class="text-lowercase fw-normal">(Form 23b)</span></th></tr>
            </thead>
            <tbody>
                @forelse ($agencyRows as $row)
                    <tr>
                        <td>
                            @if ($row->mine)
                                <a href="{{ route('instructor.evaluations.show', $row->mine) }}" target="_blank" class="ev-agency">{{ $row->hte->name }}</a>
                            @elseif ($row->canEvaluate)
                                <a href="{{ route('instructor.agency-evaluations.create', $row->hte) }}" class="ev-agency">{{ $row->hte->name }}</a>
                            @else
                                <span class="ev-agency">{{ $row->hte->name }}</span>
                            @endif
                            <div class="text-secondary" style="font-size:.76rem;"><i class="ti ti-map-pin"></i> {{ $row->hte->address ?: '—' }}</div>
                        </td>
                        <td class="text-nowrap">
                            @php $internCount = $row->deployments->count(); @endphp
                            <button type="button" class="ev-chip" data-bs-toggle="modal" data-bs-target="#agencyInternsModal"
                                    data-agency="{{ $row->hte->name }}" data-list="agencyInterns{{ $row->hte->id }}" title="See the interns">
                                <i class="ti ti-users"></i> <strong>{{ $internCount }}</strong> {{ \Illuminate\Support\Str::plural('intern', $internCount) }}
                            </button>
                            {{-- The list, shown in the window (kept out of the table so a big agency stays one line). --}}
                            <template id="agencyInterns{{ $row->hte->id }}">
                                @foreach ($row->deployments->sortBy(fn ($d) => $d->internship->student->user->name) as $d)
                                    @php $st = $d->internship->student; @endphp
                                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2" data-search="{{ \Illuminate\Support\Str::lower($st->user->name.' '.$st->user->id_number) }}">
                                        <span>
                                            <span class="fw-semibold d-block">{{ $st->user->name }}</span>
                                            <span class="text-secondary" style="font-size:.76rem;">{{ $st->user->id_number }}{{ $st->program ? ' · '.$st->program->code : '' }}</span>
                                        </span>
                                        <span class="text-secondary small text-nowrap">
                                            {{ $d->start_date?->format('M d, Y') }}{{ $d->end_date ? ' – '.$d->end_date->format('M d, Y') : '' }}
                                        </span>
                                    </li>
                                @endforeach
                            </template>
                        </td>
                        <td class="text-nowrap">
                            @if ($row->mine)
                                <span class="ev-pill ok"><i class="ti ti-circle-check"></i> EVALUATED</span>
                                <span class="ev-acts">
                                    <a href="{{ route('instructor.evaluations.show', $row->mine) }}" target="_blank" class="ev-act" title="View"><i class="ti ti-eye"></i></a>
                                    <a href="{{ route('instructor.evaluations.show', ['document' => $row->mine, 'download' => 1]) }}" target="_blank" class="ev-act" title="Download"><i class="ti ti-download"></i></a>
                                </span>
                            @elseif ($row->canEvaluate)
                                <a href="{{ route('instructor.agency-evaluations.create', $row->hte) }}" class="btn btn-sm btn-primary"><i class="ti ti-star"></i> Evaluate</a>
                            @else
                                <span class="ev-pill ro" title="Form 23a is filled by the subject instructor of the program whose students trained here."><i class="ti ti-eye"></i> View only &middot; not your program</span>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if ($row->self)
                                <span class="ev-pill ok"><i class="ti ti-circle-check"></i> RECEIVED</span>
                                <span class="ev-acts">
                                    <a href="{{ route('instructor.evaluations.show', $row->self) }}" target="_blank" class="ev-act" title="View"><i class="ti ti-eye"></i></a>
                                    <a href="{{ route('instructor.evaluations.show', ['document' => $row->self, 'download' => 1]) }}" target="_blank" class="ev-act" title="Download"><i class="ti ti-download"></i></a>
                                    @include('instructor.evaluations._lock', ['doc' => $row->self, 'canLock' => $row->canEvaluate])
                                </span>
                            @else
                                <span class="ev-pill wait"><i class="ti ti-clock"></i> Waiting for the agency</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-5"><i class="ti ti-building fs-3 d-block mb-1"></i>No agency to evaluate yet &mdash; this opens once one of your students completes their internship.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ============ Received about / from each intern ============ --}}
<div class="il-card" id="evaluations-received">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div class="ev-head">
            <span class="ev-icon gold"><i class="ti ti-inbox"></i></span>
            <div>
                <h2 class="h6 fw-bold mb-0">Evaluations Received</h2>
                <div class="text-secondary small">From the agency about the intern (Forms 13, 21) and from the intern about the agency (Forms 23c, 20). Neither sees the other's.</div>
            </div>
        </div>
        <span class="ev-pill wait"><i class="ti ti-users"></i> {{ $internRows->count() }} {{ \Illuminate\Support\Str::plural('intern', $internRows->count()) }}</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0 ui-table eval-table">
            <thead>
                <tr class="ev-group">
                    <th rowspan="2" class="align-bottom" style="background:#f8f9fb;">Intern</th>
                    <th colspan="2" class="text-center border-bottom-0"><i class="ti ti-building"></i> From the agency</th>
                    <th colspan="2" class="text-center border-bottom-0"><i class="ti ti-user"></i> From the intern</th>
                </tr>
                <tr>
                    @foreach ($internCodes as $code)
                        <th>{{ $short($code) }} <span class="ev-sub">{{ $titles[$code] }}</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($internRows as $row)
                    @php $got = $row->forms->filter()->count(); @endphp
                    <tr id="student-{{ $row->student->id }}" class="eval-row">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="ev-avatar">{{ $initials($row->student->user->name) }}</span>
                                <div style="min-width:0;">
                                    <div class="fw-semibold">{{ $row->student->user->name }} <span class="ev-count">{{ $got }}/{{ count($internCodes) }}</span></div>
                                    <div class="text-secondary" style="font-size:.76rem;">
                                        {{ $row->student->user->id_number }}
                                        @if ($isCoordinator && $row->student->program) &middot; <span class="badge text-bg-light border">{{ $row->student->program->code }}</span> @endif
                                        &middot; {{ $row->deployment->hte?->name }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        @foreach ($internCodes as $code)
                            @php $doc = $row->forms[$code]; @endphp
                            <td class="text-nowrap">
                                @if ($doc)
                                    <span class="ev-pill ok"><i class="ti ti-circle-check"></i> RECEIVED</span>
                                    <span class="ev-acts">
                                        <a href="{{ route('instructor.evaluations.show', $doc) }}" target="_blank" class="ev-act" title="View"><i class="ti ti-eye"></i></a>
                                        <a href="{{ route('instructor.evaluations.show', ['document' => $doc, 'download' => 1]) }}" target="_blank" class="ev-act" title="Download"><i class="ti ti-download"></i></a>
                                        @include('instructor.evaluations._lock', ['doc' => $doc, 'canLock' => $instructor->ownsProgram($row->deployment->internship)])
                                    </span>
                                @elseif (! isset($requirements[$code]))
                                    <span class="text-secondary">&mdash;</span>
                                @else
                                    <span class="ev-pill wait"><i class="ti ti-clock"></i> Waiting</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-5"><i class="ti ti-inbox fs-3 d-block mb-1"></i>No completed internships yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{-- Interns of one agency, opened from "Your interns there" --}}
<div class="modal fade" id="agencyInternsModal" tabindex="-1" aria-labelledby="agencyInternsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h6 fw-bold mb-0" id="agencyInternsTitle">Your interns there</h2>
                    <div class="small text-secondary" id="agencyInternsCount"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="px-3 pt-3">
                <input type="search" class="form-control" id="agencyInternsSearch" placeholder="Search by name or ID number" autocomplete="off">
            </div>
            <div class="modal-body pt-2">
                <ul class="list-group list-group-flush" id="agencyInternsList"></ul>
                <p class="text-secondary small text-center my-3 d-none" id="agencyInternsNone">No intern matches this search.</p>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('agencyInternsModal');
    if (!modal) return;
    var list = document.getElementById('agencyInternsList'), search = document.getElementById('agencyInternsSearch'),
        title = document.getElementById('agencyInternsTitle'), count = document.getElementById('agencyInternsCount'),
        none = document.getElementById('agencyInternsNone');
    function filter() {
        var q = search.value.trim().toLowerCase(), shown = 0;
        list.querySelectorAll('li').forEach(function (li) {
            var ok = !q || li.dataset.search.indexOf(q) > -1;
            li.classList.toggle('d-none', !ok);
            if (ok) shown++;
        });
        none.classList.toggle('d-none', shown > 0);
    }
    modal.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget, tpl = document.getElementById(btn.dataset.list);
        title.textContent = btn.dataset.agency;
        list.innerHTML = tpl ? tpl.innerHTML : '';
        var n = list.querySelectorAll('li').length;
        count.textContent = n + (n === 1 ? ' intern' : ' interns') + ' finished their internship here';
        search.value = '';
        filter();
    });
    modal.addEventListener('shown.bs.modal', function () { search.focus(); });
    search.addEventListener('input', filter);
})();
</script>
@endsection
