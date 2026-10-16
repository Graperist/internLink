<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Practicum/Internship Weekly Report — {{ $document->internship?->student?->user?->name ?? 'Student' }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 8mm; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #000;
            margin: 0 auto;
            padding: 0;
            max-width: 850px;
            line-height: 1.2;
        }

        .no-print { margin: 0 0 8px; padding: 5px 10px; }

        .report-page {
            width: 100%;
            page-break-after: always;
        }

        .report-page:last-child { page-break-after: auto; }

        .outer { border: 1px solid #000; }

        .header-row {
            display: flex;
            align-items: center;
            border-bottom: 1px solid #000;
            padding: 5px 7px;
            position: relative;
            min-height: 48px;
        }

        .header-row img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            margin-right: 8px;
        }

        .header-title {
            flex: 1;
            text-align: center;
        }

        .header-title h1 {
            font-size: 14px;
            margin: 0 0 1px;
            font-weight: 700;
        }

        .header-title div { font-size: 8px; }

        .form-control-no {
            position: absolute;
            top: 4px;
            right: 6px;
            text-align: right;
            font-size: 7px;
            line-height: 1.15;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            border-bottom: 1px solid #555;
            padding: 3px 5px;
            height: 18px;
        }

        .info-table td.label {
            width: 22%;
            font-weight: bold;
            border-right: 1px solid #555;
            font-size: 8px;
        }

        .info-table td.value {
            font-weight: 600;
            font-size: 8.5px;
        }

        .entries-table { table-layout: fixed; }

        .entries-table th,
        .entries-table td {
            border: 1px solid #555;
            padding: 4px 5px;
            vertical-align: top;
        }

        .entries-table th {
            font-size: 8px;
            text-align: center;
            height: 20px;
        }

        .entries-table td {
            font-size: 8px;
            white-space: pre-line;
            min-height: 24mm;
        }

        .entries-table .date-cell {
            width: 17%;
            font-weight: 600;
        }

        .entries-table .tasks-cell { width: 41.5%; }
        .entries-table .knowledge-cell { width: 41.5%; }

        .blank-row td { height: 24mm; }

        .sig-grid {
            display: flex;
            justify-content: space-between;
            padding: 10px 7px 7px;
        }

        .sig-col {
            width: 46%;
            font-size: 8px;
        }

        .sig-line {
            border-bottom: 1px solid #000;
            height: 20px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            font-weight: 700;
            font-size: 8.5px;
        }

        .sig-title {
            text-align: center;
            font-size: 7.5px;
            margin-top: 2px;
        }

        .sig-date { margin-top: 7px; }

        @media print {
            .no-print { display: none; }
            body { max-width: none; }
        }
            /* Signatory names are printed in capital letters on every form. */
        .sig-line { text-transform: uppercase; }
    </style>
</head>
<body>

@php
    $internship = $document->internship;
    $student = $internship?->student;

    $studentName = $student?->user?->name ?? '';

    /*
     * Faculty Adviser = the active instructor assigned to the student's
     * campus + academic program. This is the same resolution used by the
     * Internship Agreement printable.
     */
    $programId = $internship?->program_id ?: $student?->program_id;
    $campusId = $internship?->campus_id ?: $student?->campus_id;

    $facultyAdviser = null;

    if ($campusId && $programId) {
        $facultyAdviser = \App\Models\Instructor::with('user')
            ->where('campus_id', $campusId)
            ->where('program_id', $programId)
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->first();
    }

    $adviserName = $facultyAdviser?->user?->name ?? '';

    $deployment = $internship?->deployment;
    $companyName = $deployment?->hte?->name ?? '';

    /*
     * Form 19 is a weekly report. Each document entry is one training day.
     * Group the saved daily entries by Monday-Sunday so one printed page
     * represents one weekly report.
     */
    $allEntries = $document->entries
        ->sortBy('entry_date')
        ->values();

    // Weeks start on the day the student first logged their DTR (7 days each).
    $weeks = \App\Support\TrainingWeeks::group($allEntries, \App\Support\TrainingWeeks::anchorFor($document));

    /*
     * Keep the header fields if they exist from older Form 19 records.
     * The actual weekly date range comes from the entries on each page.
     */
    $jobDescription = $document->fieldValue('job_description') ?? '';

    $internshipStart = $document->fieldValue('start_date')
        ?? $deployment?->start_date?->format('Y-m-d');

    $internshipEnd = $document->fieldValue('end_date')
        ?? $deployment?->end_date?->format('Y-m-d');

    /*
     * Do not show the student's 486-hour requirement as the weekly hours.
     * The weekly form should show the hours represented by that week's
     * training records. If the system has a weekly total field, use it;
     * otherwise leave it blank rather than displaying the overall requirement.
     */
    $storedTotalHours = $document->fieldValue('total_hours');
@endphp

<button class="no-print" onclick="window.print()">Print</button>

@if ($weeks->isEmpty())
    <div class="outer" style="padding:20px;">
        No weekly report entries have been saved.
    </div>
@else

    @foreach ($weeks as $weekStart => $weekEntries)
        @php
            $weekStartDate = \Carbon\Carbon::parse($weekStart);
            $weekEndDate = $weekStartDate->copy()->addDays(6);

            /*
             * Only use actual dates present in the report for the displayed
             * week range. This avoids showing an internship-wide date range
             * as if it were the weekly report period.
             */
            $actualDates = $weekEntries
                ->map(fn ($entry) => \Carbon\Carbon::parse($entry->entry_date))
                ->sort();

            $displayStart = $actualDates->first() ?? $weekStartDate;
            $displayEnd = $actualDates->last() ?? $weekEndDate;
        @endphp

        <div class="report-page">
            <div class="outer">

                <div class="header-row">
                    <img src="{{ asset('images/psu-logo.png') }}" alt="PSU">

                    <div class="header-title">
                        <h1>PRACTICUM/INTERNSHIP WEEKLY REPORT</h1>
                        <div>PANGASINAN STATE UNIVERSITY</div>
                    </div>

                    <div class="form-control-no">
                        FM-AA-INT-19<br>
                        Rev. 0<br>
                        01-Feb-2017
                    </div>
                </div>

                <table class="info-table">
                    <tr>
                        <td class="label">NAME OF STUDENT-INTERN</td>
                        <td class="value">{{ $studentName }}</td>
                    </tr>

                    <tr>
                        <td class="label">FACULTY ADVISER</td>
                        <td class="value">{{ $adviserName }}</td>
                    </tr>

                    <tr>
                        <td class="label">NAME OF COMPANY</td>
                        <td class="value">{{ $companyName }}</td>
                    </tr>

                    <tr>
                        <td class="label">DESCRIPTION</td>
                        <td class="value">{{ $jobDescription }}</td>
                    </tr>

                    <tr>
                        <td class="label">DATE</td>
                        <td class="value">
                            {{ $displayStart->format('F d, Y') }}
                            @if ($displayStart->toDateString() !== $displayEnd->toDateString())
                                – {{ $displayEnd->format('F d, Y') }}
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td class="label">NUMBER OF HOURS</td>
                        <td class="value">{{ $storedTotalHours ? $storedTotalHours : '' }}</td>
                    </tr>
                </table>

                <table class="entries-table">
                    <thead>
                        <tr>
                            <th class="date-cell">DATE</th>
                            <th class="tasks-cell">TASKS ACCOMPLISHED</th>
                            <th class="knowledge-cell">
                                KNOWLEDGE, SKILLS, VALUES LEARNED
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($weekEntries as $entry)
                            @php
                                $date = $entry->entry_date
                                    ? \Carbon\Carbon::parse($entry->entry_date)
                                    : null;

                                $tasks = $entry->value('tasks_accomplished') ?? '';
                                $knowledge = $entry->value('knowledge_skills_learned') ?? '';
                            @endphp

                            <tr>
                                <td class="date-cell">
                                    {{ $date?->format('F d, Y') }}
                                </td>

                                <td class="tasks-cell">
                                    {{ $tasks }}
                                </td>

                                <td class="knowledge-cell">
                                    {{ $knowledge }}
                                </td>
                            </tr>
                        @endforeach

                        {{-- Keep the official five-row weekly-report appearance. --}}
                        @for ($blank = $weekEntries->count(); $blank < 5; $blank++)
                            <tr class="blank-row">
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                <div class="sig-grid">
                    <div class="sig-col">
                        <strong>PREPARED BY:</strong>

                        <div class="sig-line">{{ $studentName }}</div>

                        <div class="sig-title">
                            Signature of Student-Intern
                        </div>

                        <div class="sig-date">
                            Date: _______________________________
                        </div>
                    </div>

                    <div class="sig-col">
                        <strong>NOTED BY:</strong>

                        {{-- On-site supervisor = the HTE's contact person on record --}}
                        <div class="sig-line">{{ $deployment?->hte?->contact_person }}</div>

                        <div class="sig-title">
                            Signature over printed name of On-Site Supervisor
                        </div>

                        <div class="sig-date">
                            Date: _______________________________
                        </div>
                    </div>
                </div>

            </div>
        </div>
    @endforeach

@endif

</body>
</html>
