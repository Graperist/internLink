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
        <p class="text-secondary small mb-0">Evaluations open once an intern completes their internship. Everything comes to you &mdash; no one sees the ratings about them.</p>
    </div>
    @if ($isCoordinator)
        {{-- The OJT Coordinator sees every program's evaluations. --}}
        <form method="GET" action="{{ route('instructor.evaluations') }}" class="d-flex align-items-center gap-2">
            <label for="evalProgram" class="small fw-semibold text-secondary mb-0 text-nowrap"><i class="ti ti-filter"></i> Program</label>
            <select name="program_id" id="evalProgram" class="form-select form-select-sm" style="min-width:230px;" onchange="this.form.submit()">
                <option value="all" @selected($programId === null)>All programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected($programId === $program->id)>{{ $program->code }} &ndash; {{ $program->name }}</option>
                @endforeach
            </select>
        </form>
    @endif
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<style>
    .ev-agency-card { border:1px solid #e3e8ef; border-radius:14px; overflow:hidden; margin-bottom:16px; background:#fff; }
    .ev-agency-head { background:#f6f8fb; padding:14px 18px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
    .ev-agency-forms { display:flex; gap:10px; flex-wrap:wrap; padding:12px 18px; border-bottom:1px solid #eef1f5; }
    .ev-agency-form { display:flex; align-items:center; gap:8px; flex-wrap:wrap; border:1px solid #eef1f5; border-radius:10px; padding:8px 12px; flex:1 1 320px; }
    .ev-agency-form .lbl { font-size:.78rem; font-weight:600; min-width:150px; }
    .ev-agency-form .lbl small { display:block; color:#6b7785; font-weight:400; }
</style>
{{-- At a glance --}}
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="ev-stat">
            <div class="n">{{ $evaluated }} <small>/ {{ $toEvaluate }}</small></div>
            <div class="l">Agencies you have evaluated <span class="text-secondary">(Form 23a)</span></div>
            <div class="bar"><span style="width: {{ $pct($evaluated, $toEvaluate) }}%"></span></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ev-stat">
            <div class="n">{{ $selfReceived }} <small>/ {{ $agencyRows->count() }}</small></div>
            <div class="l">Agencies that rated themselves <span class="text-secondary">(Form 23b)</span></div>
            <div class="bar"><span style="width: {{ $pct($selfReceived, $agencyRows->count()) }}%"></span></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ev-stat">
            <div class="n">{{ $formsReceived }} <small>/ {{ $formsExpected }}</small></div>
            <div class="l">Intern evaluations received <span class="text-secondary">(Forms 13, 21, 23c, 20)</span></div>
            <div class="bar"><span style="width: {{ $pct($formsReceived, $formsExpected) }}%"></span></div>
        </div>
    </div>
</div>

@if ($instructor->program)
    {{-- The editing deadline for the program you handle - the coordinator sets it for their own program only. --}}
    <form method="POST" action="{{ route('instructor.evaluations.edit-days') }}" class="il-card mb-3 py-3">
        @csrf @method('PUT')
        <div class="d-flex align-items-center flex-wrap gap-3">
            <span class="ev-icon gold"><i class="ti ti-clock-edit"></i></span>
            <div class="flex-grow-1" style="min-width: 260px;">
                <div class="fw-bold small">Editing deadline &middot; {{ $instructor->program->code }} <span class="fw-normal text-secondary">(program default)</span>@if ($isCoordinator) <span class="badge rounded-pill text-bg-primary ms-1">Your program</span>@endif</div>
                <div class="text-secondary" style="font-size:.78rem;">Students and HTEs can change a submitted evaluation until this many days after the internship ends. Set a different deadline for one agency or one intern below, or lock one sooner with <i class="ti ti-lock-open"></i>.</div>
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

@php
    // Per agency: its interns, and how many of its forms have come in.
    $agencyInterns = fn ($row) => $internRows->filter(fn ($r) => (int) $r->deployment->hte_id === (int) $row->hte->id)->values();
    $formsFor = fn ($row) => [
        'got' => ($row->self ? 1 : 0) + $agencyInterns($row)->sum(fn ($r) => $r->forms->filter()->count()),
        'of' => (isset($requirements['PARTNER_AGENCY_EVAL_SELF']) ? 1 : 0) + $agencyInterns($row)->count() * collect($internCodes)->filter(fn ($c) => isset($requirements[$c]))->count(),
    ];
    // Filter by student (?student_id=): opens their agency with only them.
    $studentId = (int) request('student_id') ?: null;
    $pickedIntern = $studentId ? $internRows->first(fn ($r) => (int) $r->student->id === $studentId) : null;
    if ($pickedIntern) { $hteId = $pickedIntern->deployment->hte_id; }
    // Which agency is open: ?hte_id=, else the first one waiting on you, else the first.
    $selected = $agencyRows->first(fn ($r) => (int) $r->hte->id === (int) $hteId);
    $selectedWaiting = $selected ? null : $waitingAgencies->first(fn ($r) => (int) $r->hte->id === (int) $hteId);
    if (! $selected && ! $selectedWaiting) {
        $selected = $agencyRows->first(fn ($r) => $r->canEvaluate && ! $r->mine) ?? $agencyRows->first();
        $selectedWaiting = $selected ? null : $waitingAgencies->first();
    }
    $keep = fn (array $extra) => route('instructor.evaluations', array_filter($extra + ['program_id' => $isCoordinator ? ($programId ?? 'all') : null]));
    $linkFor = fn ($row) => $keep(['hte_id' => $row->hte->id]);
    $deadlineText = fn ($set) => $set ? 'until '.$set->format('M d, Y g:i A') : ($instructor->program ? (($d = $instructor->program->evaluation_edit_days ?? \App\Support\EvaluationEditWindow::DEFAULT_DAYS) === 0 ? '— the program default: until the internship ends' : "— the program default: {$d} ".\Illuminate\Support\Str::plural('day', $d).' after the internship ends') : '— the program default');
@endphp

@if ($agencyRows->isEmpty() && $waitingAgencies->isEmpty())
    <div class="il-card text-center text-secondary py-5">
        <i class="ti ti-building fs-3 d-block mb-1"></i>
        No evaluations yet &mdash; they open once one of your students completes their internship.
    </div>
@else
<div class="row g-3" id="evaluations-received">
    {{-- Left: the partner agencies, like the forms list in Document Review --}}
    <div class="col-lg-4">
        <div class="il-card p-0 overflow-hidden" style="position: sticky; top: 1rem;">
            <div class="px-3 pt-3 pb-2 d-flex justify-content-between align-items-center">
                <span class="small fw-bold text-uppercase text-secondary" style="letter-spacing:.03em;">Partner agencies</span>
                <span class="small text-secondary">{{ $evaluated }} / {{ $toEvaluate }} evaluated</span>
            </div>
            <form method="GET" action="{{ route('instructor.evaluations') }}" class="px-3 pb-2 d-flex flex-column gap-2">
                @if ($isCoordinator)<input type="hidden" name="program_id" value="{{ $programId ?? 'all' }}">@endif
                <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filter by student">
                    <option value="">All students</option>
                    @foreach ($internRows->sortBy(fn ($r) => $r->student->user->name) as $r)
                        <option value="{{ $r->student->id }}" @selected($studentId === $r->student->id)>{{ $r->student->user->name }} ({{ $r->student->user->id_number }})</option>
                    @endforeach
                </select>
                <input type="search" class="form-control form-control-sm" id="evalAgencySearch" placeholder="Search agency or student…" autocomplete="off">
            </form>
            <div class="list-group list-group-flush il-form-list" id="evalAgencyList">
                @foreach ($agencyRows as $row)
                    @php
                        $isOpen = $selected && $row->hte->id === $selected->hte->id;
                        $f = $formsFor($row);
                        [$icon, $color, $note] = match (true) {
                            $row->canEvaluate && ! $row->mine => ['ti-alert-circle-filled', 'text-warning', 'To evaluate'],
                            $f['of'] > 0 && $f['got'] >= $f['of'] => ['ti-circle-check-filled', 'text-success', 'All received'],
                            default => ['ti-clock', 'text-secondary', $f['got'].' of '.$f['of'].' forms received'],
                        };
                        $searchText = \Illuminate\Support\Str::lower($row->hte->name.' '.$agencyInterns($row)->map(fn ($r) => $r->student->user->name.' '.$r->student->user->id_number)->join(' '));
                    @endphp
                    <a href="{{ $linkFor($row) }}" data-search="{{ $searchText }}"
                       class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ $isOpen ? 'active-form' : '' }}" @if ($isOpen) aria-current="true" @endif>
                        <i class="ti {{ $icon }} {{ $color }} fs-5 flex-shrink-0"></i>
                        <span class="flex-grow-1" style="min-width:0;">
                            <span class="d-block small fw-semibold" style="overflow-wrap:anywhere;">{{ $row->hte->name }}</span>
                            <span class="d-block text-secondary" style="font-size:.72rem;">{{ $agencyInterns($row)->count() }} {{ \Illuminate\Support\Str::plural('intern', $agencyInterns($row)->count()) }} finished &middot; {{ $note }}</span>
                        </span>
                        <span class="small fw-semibold flex-shrink-0 {{ $isOpen ? 'text-secondary' : 'text-primary' }}">{{ $isOpen ? 'Open' : 'View' }}</span>
                    </a>
                @endforeach
                @if ($waitingAgencies->isNotEmpty())
                    <div class="list-group-item bg-light small fw-semibold text-secondary py-1">Interns still training &middot; not evaluated yet</div>
                    @foreach ($waitingAgencies as $w)
                        @php $isOpen = $selectedWaiting && $w->hte->id === $selectedWaiting->hte->id; @endphp
                        <a href="{{ $keep(['hte_id' => $w->hte->id]) }}" data-search="{{ \Illuminate\Support\Str::lower($w->hte->name) }}"
                           class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ $isOpen ? 'active-form' : '' }}">
                            <i class="ti ti-hourglass text-secondary fs-5 flex-shrink-0"></i>
                            <span class="flex-grow-1" style="min-width:0;">
                                <span class="d-block small fw-semibold text-secondary" style="overflow-wrap:anywhere;">{{ $w->hte->name }}</span>
                                <span class="d-block text-secondary" style="font-size:.72rem;">{{ $w->training ? $w->training.' '.\Illuminate\Support\Str::plural('intern', $w->training).' still training' : 'No intern placed yet' }}</span>
                            </span>
                        </a>
                    @endforeach
                @endif
                <div class="list-group-item small text-secondary text-center d-none" id="evalAgencyNone">No partner agency or student matches.</div>
            </div>
        </div>
    </div>

    {{-- Right: only the open agency --}}
    <div class="col-lg-8">
    @if ($selectedWaiting)
        <div class="il-card">
            <p class="small fw-bold text-uppercase text-secondary mb-1" style="letter-spacing:.03em;">Partner agency</p>
            <h2 class="h5 fw-bold mb-1">{{ $selectedWaiting->hte->name }}</h2>
            <div class="text-secondary small mb-3"><i class="ti ti-map-pin"></i> {{ $selectedWaiting->hte->address ?: '—' }}</div>
            <div class="text-center text-secondary py-4">
                <i class="ti ti-hourglass fs-3 d-block mb-1"></i>
                No intern has finished here yet{{ $selectedWaiting->training ? ' — '.$selectedWaiting->training.' '.\Illuminate\Support\Str::plural('intern', $selectedWaiting->training).' still training' : '' }}.
                The evaluations for this agency open once an intern completes their internship.
            </div>
            @if ($selectedWaiting->students->isNotEmpty())
                <ul class="list-group list-group-flush border-top">
                    @foreach ($selectedWaiting->students as $st)
                        <li class="list-group-item d-flex justify-content-between align-items-center small">
                            <span><span class="fw-semibold">{{ $st->user->name }}</span> <span class="text-secondary">{{ $st->user->id_number }}</span></span>
                            <span class="text-secondary">still training</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        @php
            $row = $selected;
            $interns = $agencyInterns($row);
            if ($pickedIntern) { $interns = $interns->filter(fn ($r) => (int) $r->student->id === $studentId)->values(); }
            $agencySet = \App\Support\EvaluationEditWindow::agencyDeadline($row->hte->id, $instructor->program_id);
        @endphp
        <div class="il-card mb-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <p class="small fw-bold text-uppercase text-secondary mb-1" style="letter-spacing:.03em;">Partner agency</p>
                    <h2 class="h5 fw-bold mb-1">{{ $row->hte->name }}</h2>
                    <div class="text-secondary small"><i class="ti ti-map-pin"></i> {{ $row->hte->address ?: '—' }}</div>
                </div>
                <span class="ev-pill wait"><i class="ti ti-users"></i> {{ $agencyInterns($row)->count() }} {{ \Illuminate\Support\Str::plural('intern', $agencyInterns($row)->count()) }} finished here</span>
            </div>
        </div>

        {{-- ===== Evaluate: what you fill ===== --}}
        <div class="ev-divider"><span><i class="ti ti-pencil"></i> Evaluate</span></div>
        <div class="il-card mb-3">
            <p class="text-secondary small mb-2">What you fill in for this agency.</p>
            <div class="ev-agency-forms p-0 border-0">
                <div class="ev-agency-form">
                    <span class="lbl">Your evaluation of the agency <small>Form 23a &middot; the agency does not see it</small></span>
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
                </div>
            </div>
        </div>

        {{-- ===== Received: what comes to you ===== --}}
        <div class="ev-divider"><span><i class="ti ti-inbox"></i> Received</span></div>
        <div class="il-card mb-3">
            <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">From the agency about itself</p>
            <div class="ev-agency-forms p-0 border-0">
                <div class="ev-agency-form">
                    <span class="lbl">The agency rates itself <small>Form 23b</small></span>
                    @if ($row->self)
                        <span class="ev-pill ok"><i class="ti ti-circle-check"></i> RECEIVED</span>
                        <span class="ev-acts">
                            <a href="{{ route('instructor.evaluations.show', $row->self) }}" target="_blank" class="ev-act" title="View"><i class="ti ti-eye"></i></a>
                            <a href="{{ route('instructor.evaluations.show', ['document' => $row->self, 'download' => 1]) }}" target="_blank" class="ev-act" title="Download"><i class="ti ti-download"></i></a>
                            @include('instructor.evaluations._lock', ['doc' => $row->self, 'canLock' => $row->canEvaluate])
                        </span>
                    @else
                        <span class="ev-pill wait"><i class="ti ti-clock"></i> Not sent yet</span>
                    @endif
                </div>
            </div>
            {{-- The agency's own deadline (Forms 13, 21, 23b) --}}
            @include('instructor.evaluations._deadline', [
                'who' => 'The agency can send or edit its evaluations (Forms 13, 21, 23b)',
                'current' => $deadlineText($agencySet), 'set' => $agencySet,
                'action' => $row->canEvaluate ? route('instructor.evaluations.agency-deadline', $row->hte) : null,
                'field' => 'agency-'.$row->hte->id,
            ])
        </div>

        <div class="il-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <p class="small fw-bold text-uppercase text-secondary mb-1" style="letter-spacing:.03em;">For each intern</p>
                @if ($pickedIntern)
                    <span class="small">Showing <strong>{{ $pickedIntern->student->user->name }}</strong> only &middot; <a href="{{ $keep(['hte_id' => $row->hte->id]) }}" class="text-decoration-none">show all interns here</a></span>
                @endif
            </div>
            <p class="text-secondary small mb-3">The agency rates each intern (Forms 13, 21) and each intern rates the agency (Forms 23c, 20). Neither side sees the other's.</p>
            @forelse ($interns as $irow)
                @php
                    $got = $irow->forms->filter()->count();
                    $studentSet = $irow->deployment->internship->evaluation_deadline_at ? \Carbon\CarbonImmutable::parse($irow->deployment->internship->evaluation_deadline_at) : null;
                @endphp
                <div id="student-{{ $irow->student->id }}" class="eval-row border rounded-3 mb-2 overflow-hidden">
                    <div class="d-flex align-items-center gap-2 px-3 py-2 bg-light">
                        <span class="ev-avatar">{{ $initials($irow->student->user->name) }}</span>
                        <div class="flex-grow-1" style="min-width:0;">
                            <div class="fw-semibold">{{ $irow->student->user->name }} <span class="ev-count">{{ $got }}/{{ count($internCodes) }}</span></div>
                            <div class="text-secondary" style="font-size:.76rem;">
                                {{ $irow->student->user->id_number }}
                                @if ($isCoordinator && $irow->student->program) &middot; <span class="badge text-bg-light border">{{ $irow->student->program->code }}</span> @endif
                                @if ($irow->deployment->end_date) &middot; finished {{ $irow->deployment->end_date->format('M d, Y') }} @endif
                            </div>
                        </div>
                    </div>
                    <div class="row g-0">
                        @foreach ($internCodes as $code)
                            @php $doc = $irow->forms[$code]; $fromAgency = in_array($code, \App\Support\EvaluationRouting::HTE_INTERN_CODES, true); @endphp
                            <div class="col-md-6 px-3 py-2 border-top d-flex align-items-center gap-2 flex-wrap">
                                <span class="flex-grow-1" style="min-width:170px;">
                                    <span class="d-block small fw-semibold">{{ $titles[$code] }}</span>
                                    <span class="d-block text-secondary" style="font-size:.72rem;">{{ $short($code) }} &middot; {{ $fromAgency ? 'the agency rates the intern' : 'the intern rates the agency' }}</span>
                                </span>
                                @if ($doc)
                                    <span class="ev-pill ok"><i class="ti ti-circle-check"></i> RECEIVED</span>
                                    <span class="ev-acts">
                                        <a href="{{ route('instructor.evaluations.show', $doc) }}" target="_blank" class="ev-act" title="View"><i class="ti ti-eye"></i></a>
                                        <a href="{{ route('instructor.evaluations.show', ['document' => $doc, 'download' => 1]) }}" target="_blank" class="ev-act" title="Download"><i class="ti ti-download"></i></a>
                                        @include('instructor.evaluations._lock', ['doc' => $doc, 'canLock' => $instructor->ownsProgram($irow->deployment->internship)])
                                    </span>
                                @elseif (! isset($requirements[$code]))
                                    <span class="text-secondary">&mdash;</span>
                                @else
                                    <span class="ev-pill wait"><i class="ti ti-clock"></i> Not sent yet</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="px-3 pb-2">
                        @include('instructor.evaluations._deadline', [
                            'who' => $irow->student->user->name.' can send or edit their evaluations (Forms 23c, 20)',
                            'current' => $deadlineText($studentSet), 'set' => $studentSet,
                            'action' => $instructor->ownsProgram($irow->deployment->internship) ? route('instructor.evaluations.student-deadline', $irow->deployment->internship) : null,
                            'field' => 'student-'.$irow->student->id,
                        ])
                    </div>
                </div>
            @empty
                <p class="text-secondary small text-center my-3">No intern has finished here yet.</p>
            @endforelse
        </div>
    @endif
    </div>
</div>
<style>
    .il-form-list .list-group-item { border-left: 3px solid transparent; }
    .il-form-list .active-form { background: rgba(28, 63, 102, 0.07); border-left-color: var(--il-navy, #1c3f66); }
    .eval-row:target { box-shadow: 0 0 0 2px #f0c94a; }
    .ev-divider { display:flex; align-items:center; gap:10px; margin: 4px 0 10px; color:#1c3f66; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
    .ev-divider::before, .ev-divider::after { content:''; flex:1; height:1px; background:#d6dde6; }
    .ev-divider span { white-space:nowrap; }
</style>
<script>
(function () {
    var box = document.getElementById('evalAgencySearch');
    if (!box) return;
    box.addEventListener('input', function () {
        var q = box.value.trim().toLowerCase(), shown = 0;
        document.querySelectorAll('#evalAgencyList a').forEach(function (a) {
            var ok = !q || a.dataset.search.indexOf(q) > -1;
            a.classList.toggle('d-none', !ok); if (ok) shown++;
        });
        document.getElementById('evalAgencyNone').classList.toggle('d-none', shown > 0);
    });
})();
</script>
@endif
@endsection
