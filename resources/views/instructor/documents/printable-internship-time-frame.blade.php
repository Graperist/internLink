<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Internship Time Frame — {{ $document->internship->student->user->name }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 0.5in; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #000;
            margin: 0 auto;
            max-width: 7.27in;
            line-height: 1.15;
        }

        .no-print { margin-bottom: 8px; }

        .form-control-no {
            text-align: right;
            font-size: 7pt;
            font-style: italic;
            line-height: 1.15;
            margin-bottom: 2px;
        }

        /* One outer box; each section is a table inside it so the grid
           matches the official single-table layout. */
        .sheet { border: 1.5px solid #000; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th {
            border: 1px solid #000;
            padding: 2px 5px;
            vertical-align: middle;
            height: 19px;
            overflow-wrap: anywhere;
        }
        .sheet table:first-child tr:first-child > * { border-top: 0; }
        .sheet td:first-child, .sheet th:first-child { border-left: 0; }
        .sheet td:last-child, .sheet th:last-child { border-right: 0; }
        .sheet table + table { margin-top: -1px; }

        th { font-weight: 700; text-align: center; }
        .label { font-weight: 700; text-transform: uppercase; }
        .center { text-align: center; }

        /* Header */
        .head td { height: 74px; }
        .head .logo { text-align: center; padding: 2px; }
        .head .logo img { width: 58px; height: 58px; object-fit: contain; display: block; margin: 0 auto; }
        .head .title { text-align: center; }
        .head h1 { font-size: 16pt; margin: 0 0 4px; font-weight: 700; line-height: 1.1; }
        .head .sub { font-size: 7pt; line-height: 1.3; }

        .hours td { page-break-inside: avoid; }
        .total td { font-weight: 700; }
        .total td.r { text-align: right; }

        /* Signatures */
        .sign { break-inside: avoid; page-break-inside: avoid; }
        .sign td { height: 88px; vertical-align: top; padding: 4px 6px; }
        .role { font-weight: 700; text-transform: uppercase; display: block; }
        .sig { width: 76%; margin: 34px auto 0; text-align: center; }
        .sig .name { border-bottom: 1px solid #000; min-height: 14px; font-weight: 700; font-size: 8.5pt; padding: 0 4px 1px; }
        .sig .cap { font-style: italic; font-size: 7.5pt; margin-top: 2px; }
        .sig .date { font-style: italic; font-size: 7.5pt; margin-top: 7px; }

        @media print {
            .no-print { display: none; }
            body { max-width: none; }
        }
            /* Signatory names are printed in capital letters on every form. */
        .sig .name { text-transform: uppercase; }
    </style>
    @include('partials.a4-page')
</head>
<body onload="window.print()">
@php
    $student = $document->internship->student;
    $internship = $document->internship;
    $deployment = $internship->deployment;
    $hte = $deployment?->hte;
    $campus = $student?->campus ?? $internship->campus;

    // "Alaminos City Campus" - campus label without the university prefix.
    $campusLabel = preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', trim((string) ($campus?->name ?? ''))) ?: 'Alaminos City Campus';
    if (! preg_match('/campus\s*$/i', $campusLabel)) {
        $campusLabel .= ' Campus';
    }

    $requiredHours = $internship->requiredHours();
    // The days the HTE supervisor approved in the student's Time Frame (DTR).
    $entries = $deployment
        ? \App\Models\Dtr::where('deployment_id', $deployment->id)->withApprovedTime()->orderBy('log_date')->get()
        : collect();
    // Download by week (?week=...): only that week's days.
    if ($onlyWeek = \App\Support\TrainingWeeks::requested($internship)) {
        $weekEnd = $onlyWeek->copy()->addDays(6)->endOfDay();
        $entries = $entries->filter(fn ($day) => $day->log_date->between($onlyWeek, $weekEnd))->values();
        $periodLabel = 'Week '.\App\Support\TrainingWeeks::number($onlyWeek, \App\Support\TrainingWeeks::anchorForInternship($internship))
            .' ('.$onlyWeek->format('M d').' – '.$onlyWeek->copy()->addDays(6)->format('M d, Y').')';
    } elseif ($onlyMonth = \App\Support\TrainingWeeks::requestedMonth()) {
        // By month (?month=Y-m): only that month's days.
        $entries = $entries->filter(fn ($day) => $day->log_date->isSameMonth($onlyMonth))->values();
        $periodLabel = $onlyMonth->format('F Y');
    }
    $total = $entries->sum(fn ($day) => $day->countedHours());
    $blankRows = max(0, 28 - $entries->count()); // the paper form has 28 rows
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

    // Subject Instructor: the one handling this student's program on this campus
    // (same lookup the Internship Agreement and Release Form use). A directly
    // assigned instructor wins when there is one.
    $programId = $internship->program_id ?: $student?->program_id;
    $subjectInstructor = $internship->instructor ?? $student->instructor;
    if (! $subjectInstructor?->user && $campus?->id && $programId) {
        $subjectInstructor = \App\Models\Instructor::with('user')
            ->where('campus_id', $campus->id)
            ->where('program_id', $programId)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->orderBy('id')
            ->first();
    }
    $instructorName = $subjectInstructor?->user?->name ?? '';
@endphp

<button class="no-print" onclick="window.print()">Print</button>

<div class="form-control-no">
    FM-AA-INT-14<br>
    Rev. 1<br>
    14-Oct-2024
</div>

<div class="sheet">

<table class="head">
    <colgroup><col style="width:13%"><col style="width:87%"></colgroup>
    <tr>
        <td class="logo"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU"></td>
        <td class="title">
            <h1>INTERNSHIP TIME FRAME</h1>
            <div class="sub">PANGASINAN STATE UNIVERSITY</div>
            <div class="sub">{{ $campusLabel }}</div>
            @if (! empty($periodLabel))
                <div class="sub" style="margin-top:3px;font-weight:bold;">{{ $periodLabel }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="info">
    <colgroup><col style="width:17.5%"><col style="width:82.5%"></colgroup>
    <tr><td class="label">Name of Student-Intern</td><td>{{ $student->user->name }}</td></tr>
    <tr><td class="label">Year and Course</td><td>4th Year – {{ $student->program?->name }}</td></tr>
    <tr><td class="label">Company Name</td><td>{{ $hte?->name }}</td></tr>
    <tr><td class="label">Company Address</td><td>{{ $hte?->address }}</td></tr>
    <tr><td class="label">Number of Hours</td><td>{{ $requiredHours ? $fmt($requiredHours) : '' }}</td></tr>
</table>

<table class="hours">
    <colgroup><col style="width:37%"><col style="width:31%"><col style="width:32%"></colgroup>
    <tr><th>DATE</th><th>DAY</th><th>NUMBER OF HOURS</th></tr>
    @foreach ($entries as $entry)
        <tr>
            <td class="center">{{ $entry->log_date->format('M d, Y') }}</td>
            <td class="center">{{ $entry->log_date->format('l') }}</td>
            <td class="center">{{ $fmt($entry->countedHours()) }}</td>
        </tr>
    @endforeach
    @for ($i = 0; $i < $blankRows; $i++)
        <tr><td>&nbsp;</td><td></td><td></td></tr>
    @endfor
    <tr class="total">
        <td colspan="2" class="r">TOTAL NUMBER OF HOURS</td>
        <td class="center">{{ $entries->isNotEmpty() ? $fmt($total) : '' }}</td>
    </tr>
</table>

<table class="sign">
    <tr>
        <td>
            <span class="role">Prepared by:</span>
            <div class="sig" style="width:38%;">
                <div class="name">{{ $student->user->name }}</div>
                <div class="cap">Student-Intern</div>
                <div class="date">Date: _______________________________</div>
            </div>
        </td>
    </tr>
</table>

<table class="sign">
    <colgroup><col style="width:50%"><col style="width:50%"></colgroup>
    <tr>
        <td>
            <span class="role">Noted by:</span>
            <div class="sig">
                <div class="name">{{ $instructorName }}</div>
                <div class="cap">Internship / Practicum Subject Instructor</div>
                <div class="date">Date: _______________________________</div>
            </div>
        </td>
        <td>
            <span class="role">Approved by:</span>
            <div class="sig">
                <div class="name">{{ $hte?->contact_person }}</div>
                <div class="cap">Company’s Authorized Representative</div>
                <div class="date">Date: _______________________________</div>
            </div>
        </td>
    </tr>
</table>

</div>

</body>
</html>
