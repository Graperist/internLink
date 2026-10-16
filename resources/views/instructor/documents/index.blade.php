@extends('layouts.instructor')

@section('title', 'Document Review - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Document review</h1>
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

@if ($advisees->isEmpty())
    <div class="il-card text-center text-secondary py-4">
        {{ auth()->user()->isCoordinator() ? 'No students are on your campus yet.' : 'No students are in your program yet.' }}
    </div>
@else
    <div class="il-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="h6 fw-bold mb-1">
                    Pre-Deployment Requirements — {{ $student->user->name }}
                    <span class="text-secondary fw-normal">{{ $student->user->id_number }}</span>
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

            <form method="GET" class="d-flex align-items-center gap-2 position-relative" id="adviseeSearchForm">
                <label class="small text-secondary mb-0" for="adviseeSearchInput">Search</label>
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
        </div>
    </div>

    <script>
    (function () {
        const advisees = @json($adviseeSearchData);
        const input = document.getElementById('adviseeSearchInput');
        const hiddenValue = document.getElementById('adviseeSearchValue');
        const results = document.getElementById('adviseeSearchResults');
        const form = document.getElementById('adviseeSearchForm');

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
                'printed' => 'Printed - Out for Signing',
                'completed' => 'Signed Copy Upload Unlocked',
                'approved' => 'Approved',
                default => 'Not Yet Filled',
            };

            $formNumber = 1;
        @endphp

        @foreach ($stageLabels as $stageKey => $stageLabel)
            @continue ($requirementsByStage->get($stageKey, collect())->isEmpty())

            <div class="il-card mb-3">
                <p class="small fw-bold text-uppercase text-secondary mb-3" style="letter-spacing: 0.03em;">
                    {{ $stageLabel }} ({{ $requirementsByStage->get($stageKey)->count() }} required)
                </p>

                <div class="row g-3">
                    @foreach ($requirementsByStage->get($stageKey) as $requirement)
                        @php
                            $document = $documentsByRequirement->get($requirement->id);
                            $status = $document?->status;
                            $structured = $requirement->hasStructuredFields();
                        @endphp
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="fw-semibold small">
                                        @if ($requirement->form_number)Form {{ $requirement->form_number }} · @else Form {{ $formNumber++ }} · @endif{{ $requirement->name }}
                                    </div>
                                    <span class="badge rounded-pill {{ $badgeColor($status) }}">
                                        {{ $structured ? ($statusLabelStructured($status)) : $label($status) }}
                                    </span>
                                </div>

                                @if ($structured && $requirement->code === 'WEEKLY_REPORT')
                            <div class="small text-secondary mb-2">Each four-week packet has its own review and signing state.</div>
                            @forelse ($form19Packets as $packet)
                                @php
                                    $packetStatus = $packet->status;
                                    $packetWeeks = \App\Support\TrainingWeeks::group($packet->entries, \App\Support\TrainingWeeks::anchorFor($packet));
                                @endphp
                                <div class="border rounded p-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-semibold small">Packet {{ $packet->packet_number ?? 1 }}</div>
                                            <div class="text-secondary" style="font-size:.75rem;">{{ $packet->entries->count() }} training days · {{ $statusLabelStructured($packetStatus) }}</div>
                                        </div>
                                        @if ($packetStatus === 'approved')
                                            <a href="{{ route('instructor.documents.download', $packet) }}" class="btn btn-sm btn-outline-secondary">Signed copy</a>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        @if (in_array($packetStatus, ['submitted', 'for_review']))
                                            <a href="{{ route('instructor.documents.printable', ['document' => $packet, 'preview' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">View printable</a>
                                            @if ($canAct)<form method="POST" action="{{ route('instructor.documents.print', $packet) }}">@csrf<button class="btn btn-sm btn-primary" type="submit">Print for signing</button></form>@endif
                                        @elseif ($packetStatus === 'printed')
                                            <a href="{{ route('instructor.documents.printable', ['document' => $packet, 'preview' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">View printable</a>
                                            @if ($canAct)<form method="POST" action="{{ route('instructor.documents.complete', $packet) }}">@csrf<button class="btn btn-sm btn-primary" type="submit">Confirm signed paper returned</button></form>@endif
                                        @elseif ($packetStatus === 'completed')
                                            <span class="text-secondary small">Awaiting student's one-time signed-copy upload.</span>
                                        @elseif ($packetStatus === 'approved')
                                            <span class="text-success small">Signed copy received.</span>
                                        @elseif ($packetStatus === 'returned')
                                            <span class="text-secondary small">Returned for correction.</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-secondary small">No Form 19 packets submitted yet.</div>
                            @endforelse
                        @elseif ($structured)
                                    {{-- Structured fill-in-system workflow --}}
                                    @if ($document)
                                        @if ($requirement->is_repeating)
                                            <div class="border rounded p-2 mb-2" style="background:#fafbfc; max-height: 220px; overflow-y: auto;">
                                                <div class="text-secondary fw-semibold mb-1" style="font-size: 0.72rem; text-transform: uppercase;">
                                                    {{ $document->entries->count() }} {{ $requirement->entry_cadence === 'weekly' ? 'week(s)' : 'day(s)' }} logged (view only)
                                                </div>
                                                @forelse ($document->entries as $entry)
                                                    <div class="border-bottom py-1" style="font-size: 0.75rem;">
                                                        <div class="fw-semibold">
                                                            {{ $entry->entry_date->format('M d, Y') }}
                                                            @if ($entry->entry_end_date) &ndash; {{ $entry->entry_end_date->format('M d, Y') }} @endif
                                                        </div>
                                                        @foreach ($requirement->fieldDefinitions as $field)
                                                            <div class="text-secondary">{{ $field->label }}: {{ \Illuminate\Support\Str::limit($entry->value($field->field_key), 60) }}</div>
                                                        @endforeach
                                                    </div>
                                                @empty
                                                    <div class="text-secondary">No entries logged yet.</div>
                                                @endforelse
                                            </div>
                                        @else
                                            @if ($requirement->code === 'OJT_RESUME')
                                                @php
                                                    $rv = fn (string $key) => $document->fieldValue($key);

                                                    $workExperienceRows = [];
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        $date = $rv("work_experience_{$i}_date");
                                                        $employer = $rv("work_experience_{$i}_employer");
                                                        $position = $rv("work_experience_{$i}_position");

                                                        if ($date || $employer || $position) {
                                                            $workExperienceRows[] = [
                                                                'date' => $date,
                                                                'employer' => $employer,
                                                                'position' => $position,
                                                            ];
                                                        }
                                                    }

                                                    $trainingRows = [];
                                                    for ($i = 1; $i <= 6; $i++) {
                                                        $title = $rv("training_{$i}_title");
                                                        $date = $rv("training_{$i}_date");

                                                        if ($title || $date) {
                                                            $trainingRows[] = [
                                                                'title' => $title,
                                                                'date' => $date,
                                                            ];
                                                        }
                                                    }

                                                    $referenceRows = [];
                                                    for ($i = 1; $i <= 3; $i++) {
                                                        $name = $rv("reference_{$i}_name");
                                                        $position = $rv("reference_{$i}_position");
                                                        $company = $rv("reference_{$i}_company");
                                                        $contact = $rv("reference_{$i}_contact");

                                                        if ($name || $position || $company || $contact) {
                                                            $referenceRows[] = [
                                                                'name' => $name,
                                                                'position' => $position,
                                                                'company' => $company,
                                                                'contact' => $contact,
                                                            ];
                                                        }
                                                    }
                                                @endphp

                                                <div class="border rounded p-2 mb-2" style="background:#fafbfc; font-size:0.78rem;">
                                                    <div class="text-secondary fw-semibold mb-2" style="font-size:0.72rem; text-transform:uppercase;">
                                                        Entered data (view only)
                                                    </div>

                                                    <div class="mb-1">
                                                        <span class="text-secondary">Address:</span>
                                                        <span class="fw-semibold">{{ $rv('address') ?: '—' }}</span>
                                                    </div>
                                                    <div class="mb-1">
                                                        <span class="text-secondary">Telephone/Mobile Number:</span>
                                                        <span class="fw-semibold">{{ $rv('contact_number') ?: '—' }}</span>
                                                    </div>
                                                    <div class="mb-1">
                                                        <span class="text-secondary">Email Address:</span>
                                                        <span class="fw-semibold">{{ $rv('email') ?: '—' }}</span>
                                                    </div>
                                                    <div class="mb-2">
                                                        <span class="text-secondary">Career Objective:</span>
                                                        <span class="fw-semibold">{{ $rv('career_objective') ?: '—' }}</span>
                                                    </div>

                                                    <div class="text-secondary fw-semibold">Work Experience:</div>
                                                    @forelse ($workExperienceRows as $row)
                                                        <div class="ms-2">
                                                            {{ $row['date'] ?: '—' }}
                                                            @if ($row['employer'] || $row['position'])
                                                                — {{ $row['employer'] ?: '—' }}
                                                                @if ($row['position']) — {{ $row['position'] }} @endif
                                                            @endif
                                                        </div>
                                                    @empty
                                                        <div class="ms-2">—</div>
                                                    @endforelse

                                                    <div class="text-secondary fw-semibold mt-2">Trainings and Seminars Attended:</div>
                                                    @forelse ($trainingRows as $row)
                                                        <div class="ms-2">
                                                            {{ $row['title'] ?: '—' }}
                                                            @if ($row['date']) — {{ $row['date'] }} @endif
                                                        </div>
                                                    @empty
                                                        <div class="ms-2">—</div>
                                                    @endforelse

                                                    <div class="text-secondary fw-semibold mt-2">Educational Background:</div>
                                                    <div class="ms-2">Elementary: {{ $rv('education_elementary_school') ?: '—' }} @if ($rv('education_elementary_date')) — {{ $rv('education_elementary_date') }} @endif</div>
                                                    <div class="ms-2">High School: {{ $rv('education_high_school_school') ?: '—' }} @if ($rv('education_high_school_date')) — {{ $rv('education_high_school_date') }} @endif</div>
                                                    <div class="ms-2">College: {{ $rv('education_college_school') ?: '—' }} @if ($rv('education_college_date')) — {{ $rv('education_college_date') }} @endif</div>

                                                    <div class="mt-2">
                                                        <span class="text-secondary">Extracurricular Activities and Awards Received:</span>
                                                        <span class="fw-semibold">{{ $rv('extracurricular_activities') ?: '—' }}</span>
                                                    </div>

                                                    <div class="text-secondary fw-semibold mt-2">Personal Background:</div>
                                                    <div class="ms-2">Nickname: {{ $rv('nickname') ?: '—' }}</div>
                                                    <div class="ms-2">Height: {{ $rv('height') ?: '—' }} &nbsp; Age: {{ $rv('age') ?: '—' }} &nbsp; Weight: {{ $rv('weight') ?: '—' }}</div>
                                                    <div class="ms-2">Gender: {{ $rv('gender') ?: '—' }} &nbsp; Civil Status: {{ $rv('civil_status') ?: '—' }} &nbsp; Religion: {{ $rv('religion') ?: '—' }}</div>
                                                    <div class="ms-2">Skills: {{ $rv('skills') ?: '—' }}</div>

                                                    <div class="text-secondary fw-semibold mt-2">References:</div>
                                                    @forelse ($referenceRows as $row)
                                                        <div class="ms-2">
                                                            {{ $row['name'] ?: '—' }}
                                                            @if ($row['position']) — {{ $row['position'] }} @endif
                                                            @if ($row['company']) — {{ $row['company'] }} @endif
                                                            @if ($row['contact']) — {{ $row['contact'] }} @endif
                                                        </div>
                                                    @empty
                                                        <div class="ms-2">—</div>
                                                    @endforelse
                                                </div>
                                            @elseif ($requirement->code === 'CERTIFICATE_OF_UNITS_EARNED')
                                                @php
                                                    $cv = fn (string $key) => $document->fieldValue($key);

                                                    $decodeYearRows = function (?string $raw, int $count) {
                                                        $decoded = json_decode($raw ?: '', true);
                                                        if (! is_array($decoded)) {
                                                            $decoded = [];
                                                        }

                                                        $defaults = [
                                                            'semester1' => '', 'units1' => '',
                                                            'semester2' => '', 'units2' => '',
                                                            'summer' => '', 'summer_units' => '',
                                                            'total' => '',
                                                        ];

                                                        $rows = [];
                                                        for ($i = 0; $i < $count; $i++) {
                                                            $row = array_merge($defaults, is_array($decoded[$i] ?? null) ? $decoded[$i] : []);
                                                            // Skip fully-blank rows - only show what the student actually entered.
                                                            if (array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                                                                $rows[] = $row;
                                                            }
                                                        }

                                                        return $rows;
                                                    };

                                                    $unitsYears = [
                                                        'First Year' => $decodeYearRows($cv('first_year_courses'), 7),
                                                        'Second Year' => $decodeYearRows($cv('second_year_courses'), 7),
                                                        'Third Year' => $decodeYearRows($cv('third_year_courses'), 7),
                                                        'Fourth Year' => $decodeYearRows($cv('fourth_year_courses'), 7),
                                                    ];

                                                    $failedRows = json_decode($cv('failed_courses') ?: '', true);
                                                    $failedRows = is_array($failedRows) ? array_filter($failedRows, fn ($r) => is_array($r) && array_filter($r, fn ($v) => trim((string) $v) !== '')) : [];
                                                @endphp

                                                <div class="border rounded p-2 mb-2" style="background:#fafbfc; max-height: 260px; overflow-y: auto; font-size: 0.75rem;">
                                                    <div class="text-secondary fw-semibold mb-2" style="font-size: 0.72rem; text-transform: uppercase;">
                                                        Entered data (view only)
                                                    </div>

                                                    <div class="mb-1"><span class="text-secondary">Name:</span> <span class="fw-semibold">{{ trim($cv('given_name').' '.$cv('middle_name').' '.$cv('surname')) ?: '—' }}</span></div>
                                                    <div class="mb-1"><span class="text-secondary">Course:</span> <span class="fw-semibold">{{ $cv('course') ?: '—' }}</span></div>
                                                    <div class="mb-2"><span class="text-secondary">Major:</span> <span class="fw-semibold">{{ $cv('major') ?: '—' }}</span></div>

                                                    @foreach ($unitsYears as $yearLabel => $rows)
                                                        @if (count($rows))
                                                            <div class="text-secondary fw-semibold mt-2 mb-1">{{ $yearLabel }}</div>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-bordered mb-1" style="font-size: 0.68rem;">
                                                                    <thead class="table-light">
                                                                        <tr>
                                                                            <th colspan="2">1st Sem</th>
                                                                            <th colspan="2">2nd Sem</th>
                                                                            <th colspan="2">Summer</th>
                                                                            <th>Total</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach ($rows as $row)
                                                                            <tr>
                                                                                <td>{{ $row['semester1'] }}</td>
                                                                                <td>{{ $row['units1'] }}</td>
                                                                                <td>{{ $row['semester2'] }}</td>
                                                                                <td>{{ $row['units2'] }}</td>
                                                                                <td>{{ $row['summer'] }}</td>
                                                                                <td>{{ $row['summer_units'] }}</td>
                                                                                <td>{{ $row['total'] }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        @endif
                                                    @endforeach

                                                    <div class="mb-1 mt-2"><span class="text-secondary">Total:</span> <span class="fw-semibold">{{ $cv('record_total') ?: '—' }}</span></div>

                                                    @if (count($failedRows))
                                                        <div class="text-secondary fw-semibold mt-2 mb-1">Courses Failed to Take</div>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-bordered mb-0" style="font-size: 0.68rem;">
                                                                <thead class="table-light">
                                                                    <tr><th>Code</th><th>Title</th><th>Units</th></tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($failedRows as $row)
                                                                        <tr>
                                                                            <td>{{ $row['code'] ?? '' }}</td>
                                                                            <td>{{ $row['title'] ?? '' }}</td>
                                                                            <td>{{ $row['units'] ?? '' }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="border rounded p-2 mb-2" style="background:#fafbfc;">
                                                    <div class="text-secondary fw-semibold" style="font-size: 0.72rem; text-transform: uppercase;">
                                                        Entered data (view only)
                                                    </div>
                                                    @foreach ($requirement->fieldDefinitions as $field)
                                                        @php $warning = $fieldWarnings[$document->id][$field->field_key] ?? null; @endphp
                                                        <div class="d-flex" style="font-size: 0.78rem;">
                                                            <div class="text-secondary" style="width: 55%;">{{ $field->label }}:</div>
                                                            <div class="fw-semibold">{{ $document->fieldValue($field->field_key) ?: '—' }}</div>
                                                        </div>
                                                        @if ($warning)
                                                            <div class="text-warning" style="font-size: 0.7rem; margin-top: -2px; margin-bottom: 4px;">
                                                                <i class="ti ti-alert-triangle"></i> {{ $warning }}
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
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
                                                @if ($requirement->workflow_mode === 'print_sign')
                                                    @if ($canAct)
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-primary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#printModal"
                                                        data-document-id="{{ $document->id }}"
                                                    >
                                                        Print for Signing
                                                    </button>
                                                    @endif
                                                @else
                                                    @if ($canAct)
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-primary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#reviewModal"
                                                        data-document-id="{{ $document->id }}"
                                                        data-student-name="{{ $student->user->name }}"
                                                        data-document-name="{{ $requirement->name }}"
                                                    >
                                                        Approve / Return
                                                    </button>
                                                    @endif
                                                @endif
                                                @if ($canAct)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#returnModal"
                                                    data-document-id="{{ $document->id }}"
                                                    data-document-name="{{ $requirement->name }}"
                                                >
                                                    Return to Student
                                                </button>
                                                @endif
                                            @elseif ($status === 'returned')
                                                <a
                                                    href="{{ route('instructor.documents.printable', ['document' => $document, 'preview' => 1]) }}"
                                                    target="_blank"
                                                    class="btn btn-sm btn-outline-secondary"
                                                >
                                                    View Printable
                                                </a>
                                                <span class="text-secondary small align-self-center">Awaiting student's corrected resubmission.</span>
                                            @elseif ($status === 'printed')
                                                <a href="{{ route('instructor.documents.printable', ['document' => $document, 'preview' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    View Printable
                                                </a>
                                                @if ($canAct)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#completeModal"
                                                    data-document-id="{{ $document->id }}"
                                                >
                                                    Approve
                                                </button>
                                                @endif
                                                @if ($canAct)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#returnModal"
                                                    data-document-id="{{ $document->id }}"
                                                    data-document-name="{{ $requirement->name }}"
                                                >
                                                    Return
                                                </button>
                                                @endif
                                            @elseif ($status === 'completed')
                                                <span class="text-secondary" style="font-size: 0.78rem;">Awaiting student's signed-copy upload.</span>
                                            @elseif ($status === 'approved')
                                                <a href="{{ route('instructor.documents.download', $document) }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="ti ti-download"></i> Signed copy
                                                </a>
                                            @endif
                                        </div>
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

                                        <div class="mt-2 d-flex gap-2">
                                            <a href="{{ route('instructor.documents.view', $document) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="ti ti-eye"></i> View
                                            </a>

                                            @if (in_array($status, ['submitted', 'for_review']))
                                                @if ($canAct)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#reviewModal"
                                                    data-document-id="{{ $document->id }}"
                                                    data-student-name="{{ $student->user->name }}"
                                                    data-document-name="{{ $requirement->name }}"
                                                >
                                                    Approve / Return
                                                </button>
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
    @endif
@endif

{{-- Shared review modal - populated per-card via the data-* attributes above --}}
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="reviewForm" action="">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold" id="reviewModalLabel">Review document</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-3" id="reviewModalContext"></p>

                    <label class="form-label small fw-semibold">Decision</label>
                    <select name="decision" id="decision" class="form-select mb-3" required>
                        <option value="approved">Approve</option>
                        <option value="returned">Return for revision</option>
                    </select>

                    <label class="form-label small fw-semibold">Remarks</label>
                    <textarea name="remarks" id="remarks" class="form-control" rows="3" placeholder="Required if returning the document"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit decision</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

{{-- Print for Signing modal (structured forms) --}}
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="printForm" action="">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold">Print for signing</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-0">
                        This confirms the entered data looks correct and marks the form as printed and out for
                        physical signature routing. The student can no longer edit it after this point.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm and print</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Approve modal (structured forms) - unlocks the student's signed-copy upload --}}
<div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="completeForm" action="">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold">Approve document</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-0">
                        Confirm the signed physical copies have been returned to you and everything checks out.
                        This unlocks the student's one-time upload of the scanned, signed document.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('reviewModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const documentId = button.getAttribute('data-document-id');
        const studentName = button.getAttribute('data-student-name');
        const documentName = button.getAttribute('data-document-name');

        document.getElementById('reviewModalContext').textContent = documentName + ' — ' + studentName;
        document.getElementById('reviewForm').action = '/instructor/documents/' + documentId + '/review';
        document.getElementById('decision').value = 'approved';
        document.getElementById('remarks').value = '';
    });

    document.getElementById('returnModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const documentId = button.getAttribute('data-document-id');
        const documentName = button.getAttribute('data-document-name');
        document.getElementById('returnModalContext').textContent = documentName;
        document.getElementById('returnForm').action = '/instructor/documents/' + documentId + '/review';
    });

    document.getElementById('printModal').addEventListener('show.bs.modal', function (event) {
        const documentId = event.relatedTarget.getAttribute('data-document-id');
        document.getElementById('printForm').action = '/instructor/documents/' + documentId + '/print';
    });

    document.getElementById('completeModal').addEventListener('show.bs.modal', function (event) {
        const documentId = event.relatedTarget.getAttribute('data-document-id');
        document.getElementById('completeForm').action = '/instructor/documents/' + documentId + '/complete';
    });
</script>
@endsection