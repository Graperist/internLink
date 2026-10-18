@extends('layouts.student')

@section('title', $requirement->name.' - PSU Intern-Link')

@section('content')
@if ($requirement->code === 'WEEKLY_REPORT')
    @php
        $entries = $entries ?? ($document?->entries ?? collect());
        // Week 1 starts on the first DTR day. Each official Form 19 packet contains four consecutive weeks.
        $anchor = \App\Support\TrainingWeeks::anchorForInternship($internship, $entries->pluck('entry_date'));
        $weeks = $anchor ? \App\Support\TrainingWeeks::group($entries, $anchor) : collect();
        $packetStatus = fn ($packet) => $packet?->status ?? 'draft';
        $statusText = fn ($status) => match ($status) {
            'submitted', 'for_review' => 'Awaiting instructor review',
            'printed' => 'Approved — download, print, have it signed and bring it to your instructor',
            'completed' => 'Signed copy uploaded — waiting for approval',
            'approved' => 'Approved',
            'returned' => 'Returned for correction',
            default => 'Draft — editable',
        };
    @endphp

    <div class="il-page-header">
        <div>
            <h1 class="il-page-title">Form 19 – Practicum/Internship Weekly Report</h1>
            <p class="text-secondary mb-0">Add daily entries. Four consecutive weeks are grouped into one Form 19 packet. Each packet is checked by your instructor, then you print it, have it signed and upload the signed copy.</p>
        </div>
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

    <div class="alert alert-info">
        <strong>Weekly report format:</strong> Each week is one Form 19 report page. Add one row for every training day with the date, tasks accomplished, and knowledge, skills, and values learned.
    </div>

    <div class="il-card mb-3">
        <h2 class="h6 fw-bold">Form 19 packets</h2>
        <p class="small text-secondary">Each packet covers up to four consecutive training weeks. Physical signing and signed-copy upload are tracked independently for each packet.</p>
        @forelse ($packets as $packet)
            @php $packetEntries = $packet->entries; @endphp
            <div class="border rounded-3 p-3 mb-2">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <div class="fw-semibold">Form 19 · Packet {{ $packet->packet_number ?? 1 }}</div>
                        <div class="small text-secondary">{{ $packetEntries->count() }} training {{ $packetEntries->count() === 1 ? 'day' : 'days' }} · {{ $statusText($packet->status) }}</div>
                        @if ($packet->status === 'returned' && $packet->latestReview?->remarks)
                            <div class="small text-danger mt-1">Instructor remarks: {{ $packet->latestReview->remarks }}</div>
                        @endif
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        @if (in_array($packet->status, ['draft', 'returned']) && $packetEntries->isNotEmpty())
                            <form method="POST" action="{{ route('student.documents.log.packet.submit', [$requirement, $packet->packet_number]) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">Submit packet for review</button>
                            </form>
                        @elseif ($packet->status === 'printed' || ($packet->status === 'completed' && ! $packet->file_path))
                            <a href="{{ route('student.documents.printable', ['document' => $packet, 'download' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="ti ti-download"></i> Download packet</a>
                            @if ($packet->signedUploadOpen())
                            <form method="POST" action="{{ route('student.documents.upload-signed', $packet) }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-center flex-wrap">
                                @csrf
                                <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required style="max-width:250px">
                                <button type="submit" class="btn btn-sm btn-primary">Upload signed copy</button>
                            </form>
                            @else
                            <span class="small text-secondary align-self-center"><i class="ti ti-lock"></i> Bring the signed packet to your instructor; the upload opens once they confirm the signatures.</span>
                            @endif
                        @elseif ($packet->status === 'completed')
                            <span class="badge text-bg-warning">Waiting for approval</span>
                        @elseif ($packet->status === 'approved')
                            <span class="badge text-bg-success">Approved</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="small text-secondary mb-0">Your first packet will be created when you add your first training day.</p>
        @endforelse
    </div>

    <div class="il-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h2 class="h6 fw-bold mb-0">Weekly Reports</h2>
                <span class="text-secondary small">
                    {{ $weeks->count() }} {{ $weeks->count() === 1 ? 'week' : 'weeks' }} recorded
                    · {{ $entries->count() }} training {{ $entries->count() === 1 ? 'day' : 'days' }}
                </span>
            </div>

            @if ($canEdit)
                <a href="{{ route('student.documents.log.entry.create', $requirement) }}" class="il-btn-primary text-decoration-none">
                    <i class="ti ti-plus"></i> Add Day to Weekly Report
                </a>
            @endif
        </div>

        @if ($weeks->isEmpty())
            <div class="text-center py-4">
                <i class="ti ti-calendar-week" style="font-size: 2rem;"></i>
                <h3 class="h6 fw-bold mt-2">No weekly report entries yet</h3>
                <p class="text-secondary small mb-3">Start your Form 19 by adding your first training day.</p>
                @if ($canEdit)
                    <a href="{{ route('student.documents.log.entry.create', $requirement) }}" class="il-btn-primary text-decoration-none">
                        <i class="ti ti-plus"></i> Start Week 1
                    </a>
                @endif
            </div>
        @else
            @foreach ($weeks as $weekStart => $weekEntries)
                @php
                    $start = \Carbon\Carbon::parse($weekStart);
                    $end = $start->copy()->addDays(6);
                    $weekNumber = $loop->iteration;
                @endphp

                <div class="border rounded-3 mb-3 overflow-hidden">
                    <div class="p-3 bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="fw-bold">Week {{ $weekNumber }}</div>
                            <div class="text-secondary small">
                                {{ $start->format('M d, Y') }} – {{ $end->format('M d, Y') }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge text-bg-primary">
                                {{ $weekEntries->count() }} {{ $weekEntries->count() === 1 ? 'training day' : 'training days' }}
                            </span>
                            @if ($canEdit)
                                <form method="POST" action="{{ route('student.documents.log.week.destroy', [$requirement, $weekStart]) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Delete Week {{ $weekNumber }} ({{ $start->format('M d') }} – {{ $end->format('M d, Y') }}) and all {{ $weekEntries->count() }} of its entries? This cannot be undone.')">
                                        <i class="ti ti-trash"></i> Delete week
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="text-secondary" style="font-size:.72rem;text-transform:uppercase;">
                                    <th style="width:14%;">Date</th>
                                    <th style="width:40%;">Tasks Accomplished</th>
                                    <th>Knowledge, Skills, Values Learned</th>
                                    @if ($canEdit)<th style="width:1%;"></th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($weekEntries as $entry)
                                    <tr>
                                        <td class="text-nowrap">{{ $entry->entry_date->format('M d, Y') }}</td>
                                        <td style="white-space:pre-line;">{{ $entry->value('tasks_accomplished') }}</td>
                                        <td style="white-space:pre-line;">{{ $entry->value('knowledge_skills_learned') }}</td>
                                        @if ($canEdit)
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('student.documents.log.entry.destroy', [$requirement, $entry->id]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete this day"
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
                    </div>
                </div>
            @endforeach
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
