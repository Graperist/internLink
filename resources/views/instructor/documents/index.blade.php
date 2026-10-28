@extends('layouts.instructor')

@section('title', 'Document Review - PSU Intern-Link')

@section('content')
@php
    // Opened from Document Tracking: the student's forms stay in that module.
    $tracking = $fromTracking ?? false;
    if ($tracking) { view()->share('inTracking', true); }
    $formUrl = fn ($params) => $tracking
        ? route('coordinator.documents.student', $params)
        : route('instructor.documents', $params);
@endphp
<div class="il-page-header">
    <div>
        @if ($fromTracking ?? false)
            <a href="{{ route('coordinator.documents') }}" class="small text-decoration-none"><i class="ti ti-arrow-left"></i> Repository</a>
        @else
            <a href="{{ route('instructor.documents') }}" class="small text-decoration-none"><i class="ti ti-arrow-left"></i> All students</a>
        @endif
        <h1 class="il-page-title mt-1">Document review</h1>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        @include('instructor.documents._form19-deadline')
        @unless ($fromTracking ?? false)
        @if ($prevStudent)
            <a href="{{ route('instructor.documents', ['student' => $prevStudent->id]) }}" class="btn btn-sm btn-outline-secondary" title="Previous: {{ $prevStudent->user->name }}"><i class="ti ti-chevron-left"></i></a>
        @else
            <span class="btn btn-sm btn-outline-secondary disabled"><i class="ti ti-chevron-left"></i></span>
        @endif
        <span class="small text-secondary">Student {{ $studentPosition['index'] }} of {{ $studentPosition['total'] }}</span>
        @if ($nextStudent)
            <a href="{{ route('instructor.documents', ['student' => $nextStudent->id]) }}" class="btn btn-sm btn-outline-secondary" title="Next: {{ $nextStudent->user->name }}"><i class="ti ti-chevron-right"></i></a>
        @else
            <span class="btn btn-sm btn-outline-secondary disabled"><i class="ti ti-chevron-right"></i></span>
        @endif
        @endunless
    </div>
</div>

@include('instructor.documents._form19-deadline-form')

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

@if ($advisees->isEmpty() && ! $student)
    <div class="il-card text-center text-secondary py-4">
        {{ auth()->user()->isCoordinator() ? 'No students are on your campus yet.' : 'No students are in your program yet.' }}
    </div>
@else
    <div class="il-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="h5 fw-bold mb-1">
                    {{ $student->user->name }}
                    <span class="text-secondary fw-normal small">{{ $student->user->id_number }}</span>
                    <span class="badge rounded-pill text-bg-light text-secondary fw-semibold">{{ $student->program->code ?? '' }}</span>
                </h2>
                @unless ($canAct)
                    <div class="small text-secondary mt-1"><i class="ti ti-eye"></i> View only &mdash; this student is in another program. Their subject instructor approves or returns these forms.</div>
                @endunless

                {{-- Static/informational only - no per-signatory approval state exists anywhere in the schema. --}}
                <div class="d-flex flex-wrap gap-1 mt-2">
                    <span class="text-secondary small me-1">Required signatories:</span>
                    @foreach (['Subject Adviser', 'Registrar', 'OJT Coordinator', 'Dean', 'CTE'] as $signatory)
                        <span class="badge rounded-pill text-bg-light text-secondary">{{ $signatory }}</span>
                    @endforeach
                </div>
            </div>

            @php
                // Built as its own variable, then passed to @json below -
                // @json splits its raw argument on every comma with no
                // bracket-awareness, so a multi-key array literal handed
                // to it directly gets silently truncated mid-expression.
                $adviseeSearchData = $advisees->map(fn ($advisee) => [
                    'id' => $advisee->id,
                    'name' => $advisee->user->name,
                    'id_number' => $advisee->user->id_number.($advisee->program ? ' · '.$advisee->program->code : ''),
                    'pending' => $advisee->pending_documents_count,
                ])->values();
            @endphp

            @unless ($tracking)
            <form method="GET" class="d-flex align-items-center gap-2 position-relative" id="adviseeSearchForm">
                <label class="small text-secondary mb-0" for="adviseeSearchInput">Jump to</label>
                <input type="hidden" name="student" id="adviseeSearchValue" value="{{ $student->id }}">
                <input
                    type="text"
                    id="adviseeSearchInput"
                    class="form-control form-control-sm"
                    style="width: 260px;"
                    placeholder="Search by name or ID number…"
                    value="{{ $student->user->name }} ({{ $student->user->id_number }})"
                    autocomplete="off"
                >
                <div
                    id="adviseeSearchResults"
                    class="list-group shadow-sm d-none"
                    style="position: absolute; top: 100%; right: 0; z-index: 1050; width: 320px; max-height: 320px; overflow-y: auto;"
                ></div>
            </form>
            @endunless
        </div>
    </div>

    <script>
    (function () {
        const advisees = @json($adviseeSearchData);
        const input = document.getElementById('adviseeSearchInput');
        const hiddenValue = document.getElementById('adviseeSearchValue');
        const results = document.getElementById('adviseeSearchResults');
        const form = document.getElementById('adviseeSearchForm');
        if (!input) return;   // no Jump to box (opened from Document Tracking)

        function label(advisee) {
            let text = advisee.name + ' (' + advisee.id_number + ')';
            if (advisee.pending > 0) {
                text += ' — ' + advisee.pending + ' pending';
            }
            return text;
        }

        function render(list) {
            results.innerHTML = '';

            if (list.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'list-group-item small text-secondary';
                empty.textContent = 'No matching students.';
                results.appendChild(empty);
                results.classList.remove('d-none');
                return;
            }

            list.slice(0, 20).forEach(function (advisee) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action small';
                item.textContent = label(advisee);
                item.addEventListener('click', function () {
                    hiddenValue.value = advisee.id;
                    input.value = advisee.name + ' (' + advisee.id_number + ')';
                    results.classList.add('d-none');
                    form.submit();
                });
                results.appendChild(item);
            });

            results.classList.remove('d-none');
        }

        function search(query) {
            const q = query.trim().toLowerCase();
            if (!q) {
                render(advisees);
                return;
            }
            render(advisees.filter(function (advisee) {
                return advisee.name.toLowerCase().includes(q) || advisee.id_number.toLowerCase().includes(q);
            }));
        }

        input.addEventListener('focus', function () {
            search(input.value);
        });

        input.addEventListener('input', function () {
            search(input.value);
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) {
                results.classList.add('d-none');
            }
        });
    })();
    </script>

    @if (! $internship)
        <div class="il-card text-center text-secondary py-4">
            This student has no internship record yet, so there's nothing to review.
        </div>
    @else
        @php
            $stageLabels = [
                'pre_deployment' => 'Pre-Deployment Forms',
                'active' => 'Active Forms',
                'post_deployment' => 'Post-Deployment Forms',
            ];

            $badgeColor = fn ($status) => match ($status) {
                'approved' => 'text-bg-primary',
                'submitted', 'for_review' => 'text-bg-warning',
                'returned' => 'text-bg-danger',
                default => 'text-bg-light',
            };
            $label = fn ($status) => match ($status) {
                'for_review' => 'For Review',
                'submitted' => 'For Review',
                null => 'Missing',
                default => ucfirst($status),
            };
            $statusLabelStructured = fn ($status) => match ($status) {
                'submitted', 'for_review' => 'Filled Online - For Review',
                'returned' => 'Returned',
                'printed' => 'Approved for Printing - With Student',
                'completed' => 'Signed Copy Uploaded - For Approval',
                'approved' => 'Approved',
                default => 'Not Yet Filled',
            };

            // Form labels ("Form 1", "Form 2", ...) computed once so the list
            // and the open form agree.
            $formLabels = [];
            $formNumber = 1;
            foreach ($stageLabels as $stageKey => $stageLabel) {
                foreach ($requirementsByStage->get($stageKey, collect()) as $req) {
                    $formLabels[$req->id] = 'Form '.($req->form_number ?: $formNumber++);
                }
            }

            // Which form is open: ?form=, else the first one waiting on the
            // reviewer, else the first form.
            $allRequirements = $requirementsByStage->flatten(1);
            $selectedRequirementId = (int) request('form');
            if (! $allRequirements->contains('id', $selectedRequirementId)) {
                $waiting = $allRequirements->first(fn ($r) => in_array($documentsByRequirement->get($r->id)?->status, ['submitted', 'for_review', 'completed'], true));
                $selectedRequirementId = ($waiting ?? $allRequirements->first())?->id;
            }

            $statusIcon = fn ($status) => match ($status) {
                'approved' => ['ti-circle-check-filled', 'text-success'],
                'submitted', 'for_review' => ['ti-alert-circle-filled', 'text-warning'],
                'printed' => ['ti-printer', 'text-primary'],
                'completed' => ['ti-alert-circle-filled', 'text-warning'],
                'returned' => ['ti-arrow-back-up', 'text-danger'],
                default => ['ti-circle', 'text-secondary'],
            };
            $approvedCount = $allRequirements->filter(fn ($r) => $documentsByRequirement->get($r->id)?->status === 'approved')->count();
        @endphp

        <div class="row g-3">
            {{-- Left: this student's forms, like the Teams assignment list --}}
            <div class="col-lg-4">
                <div class="il-card p-0 overflow-hidden" style="position: sticky; top: 1rem;">
                    <div class="px-3 pt-3 pb-2 d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-uppercase text-secondary" style="letter-spacing:.03em;">Forms</span>
                        <span class="small text-secondary">{{ $approvedCount }} / {{ $allRequirements->count() }} approved</span>
                    </div>
                    <div class="list-group list-group-flush il-form-list">
                        @foreach ($stageLabels as $stageKey => $stageLabel)
                            @continue ($requirementsByStage->get($stageKey, collect())->isEmpty())
                            <div class="list-group-item bg-light small fw-semibold text-secondary py-1">{{ $stageLabel }}</div>
                            @foreach ($requirementsByStage->get($stageKey) as $req)
                                @php
                                    $reqDoc = $documentsByRequirement->get($req->id);
                                    // Form 19: one per week - show what needs the instructor first.
                                    $reqStatus = $req->code === 'WEEKLY_REPORT'
                                        ? match (true) {
                                            $form19Packets->contains(fn ($p) => in_array($p->status, ['submitted', 'for_review'], true)) => 'submitted',
                                            $form19Packets->contains(fn ($p) => $p->status === 'completed' && $p->file_path) => 'completed',
                                            $form19Packets->isNotEmpty() && $form19Packets->every(fn ($p) => $p->status === 'approved') => 'approved',
                                            default => $form19Packets->last()?->status,
                                        }
                                        : $reqDoc?->status;
                                    [$icon, $iconColor] = $statusIcon($reqStatus);
                                    $isOpen = $req->id === $selectedRequirementId;
                                @endphp
                                <a href="{{ $formUrl(['student' => $student->id, 'form' => $req->id]) }}"
                                   class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ $isOpen ? 'active-form' : '' }}"
                                   @if ($isOpen) aria-current="true" @endif>
                                    <i class="ti {{ $icon }} {{ $iconColor }} fs-5 flex-shrink-0"></i>
                                    <span class="flex-grow-1" style="min-width: 0;">
                                        <span class="d-block small fw-semibold" style="overflow-wrap: anywhere;">@unless (\Illuminate\Support\Str::startsWith($req->name, 'Form ')){{ $formLabels[$req->id] }} &middot; @endunless{{ $req->name }}</span>
                                        <span class="d-block text-secondary" style="font-size: 0.72rem;">
                                            {{ $req->hasStructuredFields() ? $statusLabelStructured($reqStatus) : $label($reqStatus) }}
                                        </span>
                                    </span>
                                    <span class="small fw-semibold flex-shrink-0 {{ $isOpen ? 'text-secondary' : 'text-primary' }}">{{ $isOpen ? 'Open' : 'View' }}</span>
                                </a>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right: only the open form --}}
            <div class="col-lg-8">
        @foreach ($stageLabels as $stageKey => $stageLabel)
            @continue ($requirementsByStage->get($stageKey, collect())->isEmpty())
            @continue (! $requirementsByStage->get($stageKey)->contains('id', $selectedRequirementId))

            <div class="il-card mb-3">
                <p class="small fw-bold text-uppercase text-secondary mb-3" style="letter-spacing: 0.03em;">
                    {{ $stageLabel }}
                </p>

                <div class="row g-3">
                    @foreach ($requirementsByStage->get($stageKey) as $requirement)
                        @continue ($requirement->id !== $selectedRequirementId)
                        @php
                            $document = $documentsByRequirement->get($requirement->id);
                            $status = $document?->status;
                            $structured = $requirement->hasStructuredFields() || $requirement->code === 'INTERNSHIP_TIME_FRAME'; // Form 17 is built from the Time Frame days
                        @endphp
                        <div class="col-12">
                            <div class="p-1">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="fw-bold">
                                        @unless (\Illuminate\Support\Str::startsWith($requirement->name, 'Form ')){{ $formLabels[$requirement->id] }} · @endunless{{ $requirement->name }}
                                    </div>
                                    @unless ($requirement->code === 'WEEKLY_REPORT') {{-- each week has its own status below --}}
                                    <span class="badge rounded-pill {{ $badgeColor($status) }}">
                                        {{ $structured ? ($statusLabelStructured($status)) : $label($status) }}
                                    </span>
                                    @endunless
                                </div>

                                @if ($requirement->code === 'WEEKLY_REPORT')
                            @php
                                // Each week is its own Form 19, checked as soon as it is finished.
                                // Weeks are grouped by month to keep a 486-hour internship readable.
                                // An empty draft is not a week yet (nothing logged in it).
                                $form19Packets = $form19Packets->reject(fn ($p) => $p->status === 'draft' && $p->entries->isEmpty())->values();
                                $f19Anchor = $internship ? \App\Support\TrainingWeeks::anchorForInternship($internship) : null;
                                $f19Months = $form19Packets->sortBy('packet_number')->groupBy(fn ($p) => $f19Anchor
                                    ? $f19Anchor->copy()->addDays(((int) $p->packet_number - 1) * 7)->format('Y-m') : 'all');
                                $toCheck = fn ($p) => in_array($p->status, ['submitted', 'for_review'], true) || ($p->status === 'completed' && $p->file_path) || ($p->status === 'printed' && ! $p->signatures_confirmed_at);
                            @endphp
                            <div class="small text-secondary mb-2">
                                Each week is checked on its own as soon as the student submits it.
                                {{ $form19Packets->where('status', 'approved')->count() }} of {{ $form19Packets->count() }} {{ \Illuminate\Support\Str::plural('week', $form19Packets->count()) }} approved
                                @if ($n = $form19Packets->filter(fn ($p) => in_array($p->status, ['submitted', 'for_review'], true))->count())
                                    &middot; <span class="text-warning fw-semibold">{{ $n }} waiting for your review</span>
                                @endif
                                @php
                                    $f19Late = $f19Anchor && $internship ? $form19Packets->countBy(fn ($p) => \App\Support\TrainingWeeks::lateness($p, \App\Support\TrainingWeeks::dueDate($f19Anchor->copy()->addDays(7 * ((int) $p->packet_number - 1)), $internship))) : collect();
                                @endphp
                                @if ($f19Late->get('late'))
                                    &middot; <span class="text-warning fw-semibold">{{ $f19Late->get('late') }} late</span>
                                @endif
                                @if ($f19Late->get('missing'))
                                    &middot; <span class="text-danger fw-semibold">{{ $f19Late->get('missing') }} missing</span>
                                @endif
                            </div>
                            @if ($f19Anchor && $internship)
                                @php
                                    // Weeks so far plus the next one, for setting a due date like an MS Teams assignment.
                                    $f19Today = now()->startOfDay()->gte($f19Anchor) ? \App\Support\TrainingWeeks::number(\App\Support\TrainingWeeks::weekStart(now(), $f19Anchor), $f19Anchor) : 0;
                                    $f19LastWeek = max($f19Today + 1, (int) $form19Packets->max('packet_number'), 1);
                                    $f19Set = \App\Support\TrainingWeeks::setDueDates($internship);
                                    $f19Pick = (int) old('week_number', $f19Today ?: 1);
                                @endphp
                                @if ($canAct)
                                    <form method="POST" action="{{ route('instructor.documents.week-due', $student) }}" class="border rounded p-2 mb-2 d-flex flex-wrap align-items-end gap-2" style="background:#fafbfc;">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="form-label small fw-semibold mb-1" for="f19Week"><i class="ti ti-calendar-due"></i> Due date for</label>
                                            <select name="week_number" id="f19Week" class="form-select form-select-sm" style="min-width: 220px;">
                                                @for ($w = 1; $w <= $f19LastWeek; $w++)
                                                    @php $ws = $f19Anchor->copy()->addDays(7 * ($w - 1)); $wd = \App\Support\TrainingWeeks::dueDate($ws, $internship); @endphp
                                                    <option value="{{ $w }}" data-due="{{ $wd->format('Y-m-d\TH:i') }}" @selected($w === $f19Pick)>
                                                        Week {{ $w }} ({{ $ws->format('M d') }} – {{ $ws->copy()->addDays(6)->format('M d') }}){{ isset($f19Set[$w]) ? ' · set' : '' }}
                                                    </option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label small fw-semibold mb-1" for="f19DueAt">Date and time</label>
                                            <input type="datetime-local" name="due_at" id="f19DueAt" class="form-control form-control-sm @error('due_at') is-invalid @enderror"
                                                   value="{{ old('due_at', \App\Support\TrainingWeeks::dueDate($f19Anchor->copy()->addDays(7 * ($f19Pick - 1)), $internship)->format('Y-m-d\TH:i')) }}">
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary">Save due date</button>
                                        <button type="submit" name="clear" value="1" class="btn btn-sm btn-link text-secondary px-1" formnovalidate title="Use the default due date for this week">Use default</button>
                                        @error('due_at')<div class="w-100 text-danger small">{{ $message }}</div>@enderror
                                        <div class="w-100 text-secondary" style="font-size:.72rem;">The intern is notified. Weeks without their own due date use the default in the "Form 19 due" button above.</div>
                                    </form>
                                    <script>
                                        document.getElementById('f19Week')?.addEventListener('change', function () {
                                            var due = this.selectedOptions[0]?.dataset.due;
                                            if (due) document.getElementById('f19DueAt').value = due;
                                        });
                                    </script>
                                @endif
                            @endif
                            @forelse ($f19Months as $month => $monthWeeks)
                                @php $open = $loop->last || $monthWeeks->contains($toCheck); $mid = 'f19-'.$month; @endphp
                                <div class="border rounded mb-2 overflow-hidden f19-month">
                                    <button type="button" class="w-100 btn btn-link text-reset text-decoration-none px-2 py-1 d-flex align-items-center gap-2 text-start f19-toggle {{ $open ? '' : 'collapsed' }}"
                                            style="background:#eef2f7;" data-bs-toggle="collapse" data-bs-target="#{{ $mid }}" aria-expanded="{{ $open ? 'true' : 'false' }}">
                                        <i class="ti ti-chevron-right f19-chevron text-secondary"></i>
                                        <span class="fw-semibold small flex-grow-1">{{ $month === 'all' ? 'Weeks' : \Carbon\Carbon::parse($month.'-01')->format('F Y') }}</span>
                                        <span class="text-secondary" style="font-size:.72rem;">{{ $monthWeeks->count() }} {{ \Illuminate\Support\Str::plural('week', $monthWeeks->count()) }} &middot; {{ $monthWeeks->where('status', 'approved')->count() }} approved</span>
                                    </button>
                                    <div class="collapse {{ $open ? 'show' : '' }}" id="{{ $mid }}">
                                        @foreach ($monthWeeks as $packet)
                                            @php
                                                $packetStatus = $packet->status;
                                                $weekStart = $f19Anchor?->copy()->addDays(((int) $packet->packet_number - 1) * 7);
                                                $weekName = 'Week '.($packet->packet_number ?? 1);
                                                $due = $weekStart && $internship ? \App\Support\TrainingWeeks::dueDate($weekStart, $internship) : null;
                                                $late = $due ? \App\Support\TrainingWeeks::lateness($packet, $due) : null;
                                            @endphp
                                            <div class="border-top px-2 py-2">
                                                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                                    <div>
                                                        <span class="fw-semibold small">{{ $weekName }}</span>
                                                        <span class="text-secondary" style="font-size:.75rem;">
                                                            @if ($weekStart) {{ $weekStart->format('M d') }} – {{ $weekStart->copy()->addDays(6)->format('M d') }} &middot; @endif
                                                            {{ $packet->entries->count() }} {{ \Illuminate\Support\Str::plural('day', $packet->entries->count()) }}
                                                            @if ($due) &middot; due {{ $due->format('M d, g:i A') }}@if (\App\Support\TrainingWeeks::dueDateIsSet($weekStart, $internship)) <i class="ti ti-calendar-check text-primary" title="Due date set by the instructor"></i>@endif @endif
                                                        </span>
                                                    </div>
                                                    <span class="d-flex gap-1 align-items-center">
                                                    @if ($late === 'late')
                                                        <span class="badge rounded-pill text-bg-warning" title="First submitted {{ ($packet->first_submitted_at ?? $packet->submitted_at)?->format('M d, Y g:i A') }}">Late</span>
                                                    @elseif ($late === 'missing')
                                                        <span class="badge rounded-pill text-bg-danger" title="Past the due date and not submitted yet">Missing</span>
                                                    @endif
                                                    <span class="badge rounded-pill {{ $badgeColor($packetStatus) }}">{{ $packetStatus === 'draft' ? 'Not submitted yet' : $statusLabelStructured($packetStatus) }}</span>
                                                    </span>
                                                </div>
                                                @if ($packetStatus !== 'draft')
                                                <div class="d-flex gap-2 flex-wrap mt-1 align-items-center">
                                                    <a href="{{ route('instructor.documents.show', $packet) }}" class="btn btn-sm btn-outline-primary py-0">Open</a>
                                                    <a href="{{ route('instructor.documents.printable', ['document' => $packet, 'preview' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0">View</a>
                                                    <a href="{{ route('instructor.documents.printable', ['document' => $packet, 'download' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" title="Download"><i class="ti ti-download"></i></a>
                                                    @if (in_array($packetStatus, ['submitted', 'for_review']))
                                                        @if ($canAct)@include('partials.approve-button', ['step' => 'print', 'document' => $packet, 'name' => $weekName, 'class' => 'btn btn-sm btn-primary py-0'])@endif
                                                    @elseif ($packetStatus === 'printed')
                                                        @if ($packet->signatures_confirmed_at)
                                                            <span class="text-secondary small"><i class="ti ti-circle-check text-success"></i> Signatures confirmed &middot; waiting for the upload.</span>
                                                        @else
                                                            @if ($canAct)@include('partials.approve-button', ['step' => 'signatures', 'document' => $packet, 'name' => $weekName, 'class' => 'btn btn-sm btn-primary py-0'])@endif
                                                        @endif
                                                    @elseif ($packetStatus === 'completed')
                                                        @if ($packet->file_path)
                                                            <a href="{{ route('instructor.documents.view', $packet) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0">Signed copy</a>
                                                            @if ($canAct)@include('partials.approve-button', ['step' => 'signed', 'document' => $packet, 'name' => $weekName, 'class' => 'btn btn-sm btn-primary py-0'])@endif
                                                        @else
                                                            <span class="text-secondary small">Waiting for the signed-copy upload.</span>
                                                        @endif
                                                    @elseif ($packetStatus === 'approved')
                                                        <a href="{{ route('instructor.documents.download', $packet) }}" class="btn btn-sm btn-outline-secondary py-0">Signed copy</a>
                                                    @elseif ($packetStatus === 'returned')
                                                        <span class="text-secondary small">Returned for correction.</span>
                                                    @endif
                                                </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="text-secondary small">No weekly reports yet.</div>
                            @endforelse
                            <style>
                                .f19-chevron { transition: transform .15s ease; }
                                .f19-toggle[aria-expanded="true"] > .f19-chevron { transform: rotate(90deg); }
                            </style>
                        @elseif ($structured)
                                    {{-- Structured fill-in-system workflow --}}
                                    @if ($document)
                                        @if ($requirement->code === 'INTERNSHIP_TIME_FRAME')
                                        {{-- Form 17 is filled from the days the HTE supervisor approved in Time Frame (DTR) --}}
                                        @php
                                            $tfDays = $internship?->deployment
                                                ? \App\Models\Dtr::where('deployment_id', $internship->deployment->id)->withApprovedTime()->orderBy('log_date')->get()
                                                : collect();
                                            $tfApproved = $tfDays; // only supervisor-approved days reach the instructor
                                        @endphp
                                        <div class="border rounded p-2 mb-2" style="background:#fafbfc; max-height: 320px; overflow-y: auto;">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-secondary fw-semibold" style="font-size: 0.72rem; text-transform: uppercase;">
                                                    {{ $tfApproved->count() }} {{ \Illuminate\Support\Str::plural('day', $tfApproved->count()) }} &middot; {{ \App\Support\TimeFrame::format($tfApproved->sum(fn ($d) => $d->countedHours())) }} hours
                                                </span>
                                                <a href="{{ route('instructor.monitoring', ['student' => $student->id]) }}" class="small">Open in Time Frame</a>
                                            </div>
                                            <table class="table table-sm mb-0" style="font-size: 0.75rem;">
                                                <thead><tr class="text-secondary"><th>Date</th><th>Note</th><th class="text-end">Hours</th></tr></thead>
                                                <tbody>
                                                    @forelse ($tfApproved as $day)
                                                        <tr>
                                                            <td class="text-nowrap">{{ $day->log_date->format('M d, Y') }} <span class="text-secondary">{{ $day->log_date->format('D') }}</span></td>
                                                            <td class="text-secondary">{{ $day->notes ?: '—' }}</td>
                                                            <td class="text-end fw-semibold">{{ \App\Support\TimeFrame::format($day->countedHours()) }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="3" class="text-secondary">No days yet.</td></tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                            @include('partials.week-downloads', [
                                                'weeks' => \App\Support\TrainingWeeks::list($tfApproved->pluck('log_date'), \App\Support\TrainingWeeks::anchorForInternship($internship)),
                                                'route' => 'instructor.documents.printable', 'document' => $document,
                                            ])
                                        </div>
                                        @else
                                            {{-- The form as it prints, with what the student filled in --}}
                                            @include('instructor.documents._printable-preview', ['document' => $document])
                                        @endif

                                        @if ($status === 'returned' && $document->latestReview?->remarks)
                                            <div class="text-danger mb-2" style="font-size: 0.78rem;">
                                                Returned: {{ $document->latestReview->remarks }}
                                            </div>
                                        @endif

                                        <div class="d-flex gap-2 flex-wrap">
                                            @if (in_array($status, ['submitted', 'for_review']))
                                                <a
                                                    href="{{ route('instructor.documents.printable', ['document' => $document, 'preview' => 1]) }}"
                                                    target="_blank"
                                                    class="btn btn-sm btn-outline-secondary"
                                                >
                                                    View Printable
                                                </a>
                                                <a href="{{ route('instructor.documents.printable', ['document' => $document, 'download' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="ti ti-download"></i> Download</a>
                                                @if ($requirement->workflow_mode === 'print_sign')
                                                    @if ($canAct)
                                                    @include('partials.approve-button', ['step' => 'print', 'document' => $document, 'name' => $requirement->name])
                                                    @endif
                                                @else
                                                    @if ($canAct)
                                                    @include('partials.approve-button', ['step' => 'approve', 'document' => $document, 'name' => $requirement->name])
                                                    @endif
                                                @endif
                                            @elseif ($status === 'returned')
                                                <a
                                                    href="{{ route('instructor.documents.printable', ['document' => $document, 'preview' => 1]) }}"
                                                    target="_blank"
                                                    class="btn btn-sm btn-outline-secondary"
                                                >
                                                    View Printable
                                                </a>
                                                <a href="{{ route('instructor.documents.printable', ['document' => $document, 'download' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="ti ti-download"></i> Download</a>
                                                <span class="text-secondary small align-self-center">Awaiting student's corrected resubmission.</span>
                                            @elseif ($status === 'printed')
                                                <a href="{{ route('instructor.documents.printable', ['document' => $document, 'preview' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    View Printable
                                                </a>
                                                <a href="{{ route('instructor.documents.printable', ['document' => $document, 'download' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="ti ti-download"></i> Download</a>
                                                @if ($document->signatures_confirmed_at)
                                                    <span class="text-secondary align-self-center" style="font-size: 0.78rem;"><i class="ti ti-circle-check text-success"></i> Signatures confirmed {{ $document->signatures_confirmed_at->format('M d') }} &middot; waiting for the student's upload.</span>
                                                @else
                                                    @if ($canAct)
                                                        @include('partials.approve-button', ['step' => 'signatures', 'document' => $document, 'name' => $requirement->name])
                                                    @endif
                                                    <span class="text-secondary align-self-center" style="font-size: 0.78rem;">With the student for printing and signatures. When they bring the signed form back, confirm the signatures to open their upload.</span>
                                                @endif
                                            @elseif ($status === 'completed')
                                                @if ($document->file_path)
                                                    <a href="{{ route('instructor.documents.view', $document) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                        <i class="ti ti-file-check"></i> View signed copy
                                                    </a>
                                                    @if ($canAct)
                                                    @include('partials.approve-button', ['step' => 'signed', 'document' => $document, 'name' => $requirement->name])
                                                    @endif
                                                @else
                                                    <span class="text-secondary" style="font-size: 0.78rem;">Waiting for the student's signed-copy upload.</span>
                                                @endif
                                            @elseif ($status === 'approved')
                                                <a href="{{ route('instructor.documents.download', $document) }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="ti ti-download"></i> Signed copy
                                                </a>
                                            @endif
                                        </div>
                                        @if ($kinds = \App\Models\DocumentAttachment::kindsFor($requirement->code))
                                            <div class="mt-2 d-flex flex-wrap gap-2" style="font-size: 0.78rem;">
                                                @foreach ($kinds as $kind => $kindLabel)
                                                    @php $attachment = $document->attachment($kind); @endphp
                                                    @if ($attachment)
                                                        <a href="{{ route('instructor.documents.attachment', $attachment) }}" target="_blank" class="badge rounded-pill text-bg-light border text-decoration-none fw-normal py-2 px-3" style="font-size: 0.78rem;">
                                                            <i class="ti ti-paperclip"></i> {{ $kindLabel }}
                                                        </a>
                                                    @else
                                                        <span class="badge rounded-pill text-bg-light border text-danger fw-normal py-2 px-3" style="font-size: 0.78rem;"><i class="ti ti-paperclip-off"></i> No {{ $kindLabel }}</span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-secondary" style="font-size: 0.78rem;">Not yet filled out by student.</div>
                                    @endif
                                @else
                                    {{-- Original plain-upload workflow, unchanged --}}
                                    @if ($document)
                                        <div class="text-secondary" style="font-size: 0.78rem;">
                                            {{ $document->original_filename }}
                                            @if ($document->submitted_at)
                                                &middot; {{ $document->submitted_at->format('M d, Y') }}
                                            @endif
                                        </div>

                                        @if ($status === 'returned' && $document->latestReview?->remarks)
                                            <div class="text-danger mt-1" style="font-size: 0.78rem;">
                                                Returned: {{ $document->latestReview->remarks }}
                                            </div>
                                        @endif

                                        @if ($document->attachments->isNotEmpty())
                                            <div class="mt-2 d-flex flex-wrap gap-2">
                                                @foreach ($document->attachments->sortBy(fn ($a) => (int) preg_replace('/\D/', '', $a->kind)) as $attachment)
                                                    <a href="{{ route('instructor.documents.attachment', $attachment) }}" target="_blank" class="badge rounded-pill text-bg-light border text-decoration-none fw-normal py-2 px-3" style="font-size: 0.78rem;" title="{{ $attachment->original_filename }}">
                                                        <i class="ti ti-paperclip"></i> {{ $attachment->label() }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="mt-2 d-flex gap-2">
                                            @if ($document->file_path)
                                            <a href="{{ route('instructor.documents.view', $document) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="ti ti-eye"></i> View
                                            </a>
                                            @endif

                                            @if (in_array($status, ['submitted', 'for_review']))
                                                @if ($canAct)
                                                @include('partials.approve-button', ['step' => 'approve', 'document' => $document, 'name' => $requirement->name])
                                                @endif
                                            @elseif ($status === 'returned')
                                                <span class="text-secondary small align-self-center">{{ $requirement->audience === 'coordinator' ? "Awaiting the coordinator's corrected upload." : "Awaiting student's corrected resubmission." }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-secondary" style="font-size: 0.78rem;">{{ $requirement->audience === 'coordinator' ? 'Waiting for the coordinator to upload this (Repository).' : 'Not yet submitted.' }}</div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Return right here, or open the full Classroom-style view --}}
        @php
            $openRequirement = $allRequirements->firstWhere('id', $selectedRequirementId);
            $openDocument = $openRequirement && $openRequirement->code !== 'WEEKLY_REPORT'
                ? $documentsByRequirement->get($selectedRequirementId) : null;
            $openReturnable = $openDocument && in_array($openDocument->status, ['submitted', 'for_review', 'printed', 'completed'], true);
        @endphp
        @if ($openDocument)
            <div class="il-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 {{ $canAct && $openReturnable ? 'mb-3' : '' }}">
                    <span class="small fw-bold text-uppercase text-secondary" style="letter-spacing:.03em;">Review</span>
                    <a href="{{ route('instructor.documents.show', $openDocument) }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-arrows-maximize"></i> Open full view
                    </a>
                </div>
                @if ($canAct && $openReturnable)
                    <form method="POST" action="{{ route('instructor.documents.review', $openDocument) }}">
                        @csrf
                        <input type="hidden" name="decision" value="returned">
                        <label class="form-label small fw-semibold" for="inline_remarks">Return to student with remarks</label>
                        <textarea name="remarks" id="inline_remarks" class="form-control mb-2" rows="3" maxlength="2000"
                                  placeholder="What needs to be corrected?" required></textarea>
                        <button type="submit" class="btn btn-sm btn-danger"><i class="ti ti-arrow-back-up"></i> Return to student</button>
                    </form>
                @endif
            </div>
        @endif
            </div>
        </div>
    @endif
@endif

<style>
    .il-form-list .list-group-item { border-left: 3px solid transparent; }
    .il-form-list .active-form { background: rgba(28, 63, 102, 0.07); border-left-color: var(--il-navy, #1c3f66); }
</style>

{{-- Return to Student modal (structured forms) --}}
<div class="modal fade" id="returnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="returnForm" action="">
                @csrf
                <input type="hidden" name="decision" value="returned">
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold" id="returnModalLabel">Return to student</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-3" id="returnModalContext"></p>
                    <label class="form-label small fw-semibold">What needs to be corrected?</label>
                    <textarea name="remarks" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Return to student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>


    document.getElementById('returnModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const documentId = button.getAttribute('data-document-id');
        const documentName = button.getAttribute('data-document-name');
        document.getElementById('returnModalContext').textContent = documentName;
        document.getElementById('returnForm').action = '/instructor/documents/' + documentId + '/review';
    });




</script>

@include('partials.approve-modal')
@endsection