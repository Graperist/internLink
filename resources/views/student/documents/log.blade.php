@extends('layouts.student')

@section('title', $requirement->name.' - PSU Intern-Link')

@section('content')
@if ($requirement->code === 'WEEKLY_REPORT')
    @php
        $entries = $entries ?? ($document?->entries ?? collect());
        // Week 1 starts on the first DTR day (or an earlier entry). Each week is
        // its own Form 19, reviewed by the instructor as soon as it is finished.
        $anchor = \App\Support\TrainingWeeks::anchorForInternship($internship, $entries->pluck('entry_date'));
        $weeks = $anchor ? \App\Support\TrainingWeeks::group($entries, $anchor) : collect();
        $weekDocs = $packets->keyBy('packet_number');
        // Days fold into weeks, weeks into months.
        $months = $weeks->groupBy(fn ($weekEntries, $weekStart) => \Carbon\Carbon::parse($weekStart)->format('Y-m'), true);
        $status = fn ($doc) => match ($doc?->status) {
            'submitted', 'for_review' => ['With your instructor', 'text-bg-warning'],
            'printed' => [$doc->signatures_confirmed_at ? 'Upload the signed copy' : 'Approved for printing', 'text-bg-info'],
            'completed' => [filled($doc->file_path) ? 'Signed copy uploaded' : 'Upload the signed copy', 'text-bg-primary'],
            'approved' => ['Approved', 'text-bg-success'],
            'returned' => ['Returned', 'text-bg-danger'],
            default => ['Not submitted', 'text-bg-light text-secondary border'],
        };
        $editable = fn ($doc) => ! $doc || in_array($doc->status, ['draft', 'returned'], true);
    @endphp

    <div class="il-page-header">
        <div>
            <h1 class="il-page-title">Form 19 – Practicum/Internship Weekly Report</h1>
            <p class="text-secondary mb-0">Add a row for every training day. Each week is its own Form 19: submit it when the week is finished, your instructor checks it, then you print it, have it signed and upload the signed copy.</p>
        </div>
        <a href="{{ route('student.documents') }}" class="il-btn-secondary text-decoration-none">
            <i class="ti ti-arrow-left"></i> Back to My Documents
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="il-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h2 class="h6 fw-bold mb-0">Weekly Reports</h2>
                <span class="text-secondary small">
                    {{ $weeks->count() }} {{ $weeks->count() === 1 ? 'week' : 'weeks' }}
                    · {{ $entries->count() }} training {{ $entries->count() === 1 ? 'day' : 'days' }}
                    · {{ $packets->where('status', 'approved')->count() }} approved
                </span>
            </div>
            <a href="{{ route('student.documents.log.entry.create', $requirement) }}" class="il-btn-primary text-decoration-none">
                <i class="ti ti-plus"></i> Add Day to Weekly Report
            </a>
        </div>

        @php
            $todayStart = $anchor && now()->startOfDay()->gte($anchor) ? \App\Support\TrainingWeeks::weekStart(now(), $anchor) : null;
            $todayNumber = $todayStart ? \App\Support\TrainingWeeks::number($todayStart, $anchor) : null;
            $todayDoc = $todayNumber ? $weekDocs->get($todayNumber) : null;
        @endphp
        @if ($todayDoc && ! $editable($todayDoc))
            <div class="alert alert-info small py-2">
                <i class="ti ti-info-circle"></i>
                This week (Week {{ $todayNumber }}, {{ $todayStart->format('M d') }} – {{ $todayStart->copy()->addDays(6)->format('M d, Y') }}) has already been sent to your instructor, so no more days can be added to it.
                You can add days again from <strong>{{ $todayStart->copy()->addDays(7)->format('M d, Y') }}</strong>, when Week {{ $todayNumber + 1 }} starts.
            </div>
        @endif

        @if ($weeks->isEmpty())
            <div class="text-center py-4">
                <i class="ti ti-calendar-week" style="font-size: 2rem;"></i>
                <h3 class="h6 fw-bold mt-2">No weekly report entries yet</h3>
                <p class="text-secondary small mb-3">Start your Form 19 by adding your first training day.</p>
                <a href="{{ route('student.documents.log.entry.create', $requirement) }}" class="il-btn-primary text-decoration-none">
                    <i class="ti ti-plus"></i> Start Week 1
                </a>
            </div>
        @else
            @foreach ($months as $month => $monthWeeks)
                @php
                    $monthId = 'month-'.$month;
                    $monthOpen = $loop->last;
                    $monthDocs = $monthWeeks->keys()->map(fn ($ws) => $weekDocs->get(\App\Support\TrainingWeeks::number(\Carbon\Carbon::parse($ws), $anchor)));
                @endphp
                <div class="border rounded-3 mb-2 overflow-hidden log-month">
                    <button type="button" class="w-100 btn btn-link text-reset text-decoration-none px-3 py-2 d-flex align-items-center gap-2 text-start log-toggle {{ $monthOpen ? '' : 'collapsed' }}"
                            style="background:#eef2f7;" data-bs-toggle="collapse" data-bs-target="#{{ $monthId }}" aria-expanded="{{ $monthOpen ? 'true' : 'false' }}">
                        <i class="ti ti-chevron-right log-chevron fs-5 text-secondary"></i>
                        <span class="fw-bold flex-grow-1">{{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }}</span>
                        <span class="small text-secondary">
                            {{ $monthWeeks->count() }} {{ \Illuminate\Support\Str::plural('week', $monthWeeks->count()) }}
                            · {{ $monthDocs->filter(fn ($d) => $d?->status === 'approved')->count() }} approved
                        </span>
                    </button>
                    <div class="collapse {{ $monthOpen ? 'show' : '' }}" id="{{ $monthId }}">
                        <div class="p-2">
                            @foreach ($monthWeeks as $weekStart => $weekEntries)
                                @php
                                    $start = \Carbon\Carbon::parse($weekStart);
                                    $end = $start->copy()->addDays(6);
                                    $weekNumber = \App\Support\TrainingWeeks::number($start, $anchor);
                                    $doc = $weekNumber >= 1 ? $weekDocs->get($weekNumber) : $weekEntries->first()?->document;
                                    $beforeWeekOne = $weekNumber < 1;
                                    $finished = \App\Support\TrainingWeeks::weekFinished($start, $internship);
                                    $due = \App\Support\TrainingWeeks::dueDate($start, $internship);
                                    $late = $beforeWeekOne ? null : \App\Support\TrainingWeeks::lateness($doc, $due);
                                    [$label, $color] = $status($doc);
                                    $canEditWeek = $editable($doc);
                                    $isOpen = $monthOpen && $loop->last;
                                    $weekId = 'week-'.$weekStart;
                                @endphp
                                <div class="border rounded-3 mb-2 overflow-hidden log-week bg-white">
                                    <div class="px-3 py-2 bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <button type="button" class="btn btn-link text-reset text-decoration-none p-0 d-flex align-items-center gap-2 text-start flex-grow-1 log-toggle {{ $isOpen ? '' : 'collapsed' }}"
                                                data-bs-toggle="collapse" data-bs-target="#{{ $weekId }}" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="{{ $weekId }}">
                                            <i class="ti ti-chevron-right log-chevron fs-5 text-secondary"></i>
                                            <span>
                                                <span class="fw-bold d-block">{{ $beforeWeekOne ? 'Before Week 1' : 'Week '.$weekNumber }}</span>
                                                <span class="text-secondary small">{{ $start->format('M d') }} – {{ $end->format('M d, Y') }} · {{ $weekEntries->count() }} {{ $weekEntries->count() === 1 ? 'day' : 'days' }}@unless ($beforeWeekOne) · <span class="{{ $late === 'missing' ? 'text-danger fw-semibold' : '' }}">Due {{ $due->format('M d, g:i A') }}</span>@endunless</span>
                                            </span>
                                        </button>
                                        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                                            @if ($late === 'late')
                                                <span class="badge rounded-pill text-bg-warning" title="Submitted after the due date">Late</span>
                                            @elseif ($late === 'missing')
                                                <span class="badge rounded-pill text-bg-danger" title="Past the due date and not submitted yet">Missing</span>
                                            @endif
                                            <span class="badge rounded-pill {{ $color }}">{{ $label }}</span>
                                            @if ($beforeWeekOne)
                                                <span class="small text-secondary">Before your Week 1 – delete these days</span>
                                            @elseif ($doc && $canEditWeek && $weekEntries->isNotEmpty() && ! $finished)
                                                <span class="small text-secondary" title="A week is sent to your instructor once it is finished">Submit on {{ $end->format('M d') }}</span>
                                            @elseif ($doc && $canEditWeek && $weekEntries->isNotEmpty())
                                                <form method="POST" action="{{ route('student.documents.log.packet.submit', [$requirement, $weekNumber]) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-send"></i> Submit week</button>
                                                </form>
                                            @endif
                                            {{-- Every week can be downloaded (Form 19 for that week); printing for signatures waits for the instructor. --}}
                                            @if ($doc && ! $beforeWeekOne && $weekEntries->isNotEmpty())
                                                @php $forSigning = in_array($doc->status, ['printed', 'completed'], true) && ! filled($doc->file_path); @endphp
                                                <a href="{{ route('student.documents.printable', ['document' => $doc, 'download' => 1]) }}" target="_blank"
                                                   class="btn btn-sm {{ $forSigning ? 'btn-outline-primary' : 'btn-outline-secondary' }}"
                                                   title="{{ $forSigning ? 'Download, print and have it signed' : 'Download a copy of this week' }}"><i class="ti ti-download"></i> Download</a>
                                            @endif
                                            @if ($canEditWeek)
                                                <form method="POST" action="{{ route('student.documents.log.week.destroy', [$requirement, $weekStart]) }}" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Delete week" aria-label="Delete week"
                                                            onclick="return confirm('Delete {{ $beforeWeekOne ? 'these days' : 'Week '.$weekNumber }} ({{ $start->format('M d') }} – {{ $end->format('M d, Y') }}) and all {{ $weekEntries->count() }} of its entries? This cannot be undone.')">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="collapse {{ $isOpen ? 'show' : '' }}" id="{{ $weekId }}">
                                        @if ($doc?->status === 'returned' && $doc->latestReview?->remarks)
                                            <div class="alert alert-danger small py-2 m-2 mb-0"><strong>Returned by your instructor:</strong> {{ $doc->latestReview->remarks }}</div>
                                        @endif
                                        @if ($doc && in_array($doc->status, ['printed', 'completed'], true) && ! filled($doc->file_path))
                                            <div class="small m-2 mb-0 p-2 rounded" style="background:#f4f6f9;">
                                                @if ($doc->signedUploadOpen())
                                                    <form method="POST" action="{{ route('student.documents.upload-signed', $doc) }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-center flex-wrap">
                                                        @csrf
                                                        <span>Your instructor confirmed the signatures. Upload the signed copy:</span>
                                                        <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required style="max-width:250px">
                                                        <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-upload"></i> Upload signed copy</button>
                                                    </form>
                                                @else
                                                    <i class="ti ti-lock"></i> Download and print this week, have it signed, then bring it to your instructor. The upload opens once they confirm the signatures.
                                                @endif
                                            </div>
                                        @endif
                                        <div class="table-responsive">
                                            <table class="table align-middle mb-0">
                                                <thead>
                                                    <tr class="text-secondary" style="font-size:.72rem;text-transform:uppercase;">
                                                        <th style="width:14%;">Date</th>
                                                        <th style="width:40%;">Tasks Accomplished</th>
                                                        <th>Knowledge, Skills, Values Learned</th>
                                                        @if ($canEditWeek)<th style="width:1%;"></th>@endif
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($weekEntries as $entry)
                                                        <tr>
                                                            <td class="text-nowrap">{{ $entry->entry_date->format('M d, Y') }}</td>
                                                            <td style="white-space:pre-line;">{{ $entry->value('tasks_accomplished') }}</td>
                                                            <td style="white-space:pre-line;">{{ $entry->value('knowledge_skills_learned') }}</td>
                                                            @if ($canEditWeek)
                                                                <td class="text-end">
                                                                    <form method="POST" action="{{ route('student.documents.log.entry.destroy', [$requirement, $entry->id]) }}">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Delete this day" aria-label="Delete this day"
                                                                                onclick="return confirm('Delete the entry for {{ $entry->entry_date->format('M d, Y') }}? This cannot be undone.')">
                                                                            <i class="ti ti-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="d-flex gap-3 small mt-2">
                <a href="#" class="text-decoration-none" onclick="document.querySelectorAll('.log-month > .collapse, .log-week .collapse').forEach(el => bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show()); return false;">Expand all</a>
                <a href="#" class="text-decoration-none" onclick="document.querySelectorAll('.log-week .collapse, .log-month > .collapse').forEach(el => bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide()); return false;">Collapse all</a>
            </div>
            <style>
                .log-chevron { transition: transform .15s ease; }
                .log-toggle[aria-expanded="true"] > .log-chevron { transform: rotate(90deg); }
            </style>
        @endif
    </div>

    @if ($requirement->code !== 'WEEKLY_REPORT' && $canEdit && $document && $entries->isNotEmpty() && in_array($document->status, ['draft', 'returned']))
        <form method="POST" action="{{ route('student.documents.log.submit', $requirement) }}" class="mt-3">
            @csrf
            <button type="submit" class="il-btn-primary">
                Submit Form 19 for instructor review ({{ $weeks->count() }} {{ $weeks->count() === 1 ? 'weekly report' : 'weekly reports' }})
            </button>
        </form>
    @elseif ($requirement->code !== 'WEEKLY_REPORT' && $document && in_array($document->status, ['submitted', 'for_review']))
        <p class="text-secondary small mt-3">Form 19 submitted — awaiting instructor review.</p>
    @elseif ($requirement->code !== 'WEEKLY_REPORT' && $document && in_array($document->status, ['printed', 'completed', 'approved']))
        <p class="text-secondary small mt-3">Form 19 is locked because it has already entered the print/signing workflow.</p>
    @endif
@else
    {{-- Generic repeating form (e.g. Internship Time Frame) --}}
    <div class="il-page-header">
        <h1 class="il-page-title">{{ $requirement->name }}</h1>
        <a href="{{ route('student.documents') }}" class="il-btn-secondary text-decoration-none">
            <i class="ti ti-arrow-left"></i> Back to My Documents
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($document && $document->status === 'returned' && $document->latestReview?->remarks)
        <div class="alert alert-danger">
            <strong>Returned for correction:</strong> {{ $document->latestReview->remarks }}
        </div>
    @endif

    <div class="il-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h6 fw-bold mb-0">Entries logged so far</h2>
                <span class="text-secondary small">
                    {{ $document?->entries->count() ?? 0 }} {{ $requirement->entry_cadence === 'weekly' ? 'week(s)' : 'day(s)' }} logged
                </span>
            </div>
            @if ($canEdit)
                <a href="{{ route('student.documents.log.entry.create', $requirement) }}" class="il-btn-primary text-decoration-none">
                    <i class="ti ti-plus"></i> Add entry
                </a>
            @endif
        </div>

        @if (! $document || $document->entries->isEmpty())
            <p class="text-secondary small mb-0">No entries yet - add your first one above.</p>
        @else
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase;">
                        <th>{{ $requirement->entry_cadence === 'weekly' ? 'Week' : 'Date' }}</th>
                        @foreach ($requirement->fieldDefinitions as $field)
                            <th>{{ $field->label }}</th>
                        @endforeach
                        @if ($canEdit)<th style="width:1%;"></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($document->entries as $entry)
                        <tr>
                            <td class="text-nowrap">
                                {{ $entry->entry_date->format('M d, Y') }}
                                @if ($entry->entry_end_date)
                                    &ndash; {{ $entry->entry_end_date->format('M d, Y') }}
                                @endif
                            </td>
                            @foreach ($requirement->fieldDefinitions as $field)
                                <td style="font-size: 0.85rem; max-width: 240px;">
                                    {{ \Illuminate\Support\Str::limit($entry->value($field->field_key), 80) }}
                                </td>
                            @endforeach
                            @if ($canEdit)
                                <td class="text-end">
                                    <form method="POST" action="{{ route('student.documents.log.entry.destroy', [$requirement, $entry->id]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete this entry"
                                                onclick="return confirm('Delete the entry for {{ $entry->entry_date->format('M d, Y') }}? This cannot be undone.')">
                                            <i class="ti ti-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($canEdit && $document && $document->entries->isNotEmpty() && in_array($document->status, ['draft', 'returned']))
        <form method="POST" action="{{ route('student.documents.log.submit', $requirement) }}" class="mt-3">
            @csrf
            <button type="submit" class="il-btn-primary">
                Submit log for review ({{ $document->entries->count() }} {{ $requirement->entry_cadence === 'weekly' ? 'week(s)' : 'day(s)' }})
            </button>
        </form>
    @elseif ($document && $document->status === 'submitted')
        <p class="text-secondary small mt-3">Submitted &mdash; awaiting your instructor's review. The log is locked until it is returned for correction.</p>
    @endif
@endif
@endsection
