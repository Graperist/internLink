<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Internship Release Form — {{ $document->internship->student->user->name }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 14mm 25.4mm 14mm; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10.5pt;
            color: #000;
            margin: 0 auto;
            max-width: 6.27in;
            line-height: 1.35;
        }

        .no-print { margin-bottom: 10px; }

        table.page { width: 100%; border-collapse: collapse; }
        table.page > thead > tr > td,
        table.page > tbody > tr > td { padding: 0; border: 0; }
        table.page > thead { display: table-header-group; }

        .form-control-no { text-align: right; font-size: 7pt; font-style: italic; line-height: 1.15; margin-bottom: 4px; }

        .header { position: relative; text-align: center; margin: 4px 0 6px; min-height: 66px; }
        .header h1 { font-size: 16pt; margin: 14px 0 3px; font-weight: 700; line-height: 1.1; }
        .header .sub { font-size: 7pt; }
        .header img { position: absolute; right: 0.05in; top: 0; width: 62px; height: 62px; object-fit: contain; }
        .campus-line { text-align: center; font-size: 7pt; margin: 6px 0 22px; }

        .u { display: inline-block; border-bottom: 1px solid #000; padding: 0 4px; text-align: center; line-height: 1.2; min-width: 1in; }

        .addr { width: 3.6in; margin: 0 0 14px; }
        .addr .line { border-bottom: 1px solid #000; min-height: 18px; padding: 2px 2px 0; font-size: 10pt; }
        .addr .cap { font-size: 7pt; text-align: center; margin-top: 1px; }

        .field { margin: 0 0 10px; }
        .field b { font-weight: 700; }
        .field .u { text-align: left; }

        p { margin: 0 0 12px; }
        .body-text { margin-top: 18px; }

        .sigs { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 54px; }
        .sigs td { vertical-align: bottom; padding: 0 0.15in; text-align: center; }
        .sig .name { border-bottom: 1px solid #000; min-height: 22px; padding: 4px 4px 1px; font-size: 9.5pt; font-weight: 700; }
        .sig .cap { font-size: 7.5pt; font-style: italic; margin-top: 2px; }

        .noted { margin-top: 38px; }
        .noted .sig { width: 2.8in; margin-top: 34px; }

        @media print {
            .no-print { display: none; }
            body { max-width: none; }
        }
    </style>
</head>
<body onload="window.print()">
@php
    $v = fn (string $key) => (string) ($document->fieldValue($key) ?: '');
    $student = $document->internship->student;
    $campus = $student?->campus ?? $document->internship->campus;
    $campusName = $campus?->name ?: 'Pangasinan State University';

    $campusLabel = preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', trim((string) $campusName)) ?: 'Alaminos City Campus';
    $campusShort = trim(preg_replace('/\s*Campus\s*$/i', '', $campusLabel));
    $cedName = trim((string) ($campus?->ced_name ?: ''));

    $studentName = $student?->user?->name ?? '';
    $major = $v('major');
    $requiredHours = $v('required_hours') ?: ($document->internship->required_hours ?? '');

    // Date of the memo: the day it is printed.
    $memoDate = ($document->printed_at ?: now())->format('F j, Y');

    $fmt = function (string $raw, string $format) {
        if ($raw === '') return '';
        try { return \Carbon\Carbon::parse($raw)->format($format); } catch (\Throwable $e) { return $raw; }
    };
    $startMD = $fmt($v('start_date'), 'F j');
    $startY  = $fmt($v('start_date'), 'Y');
    $endMD   = $fmt($v('end_date'), 'F j');
    $endY    = $fmt($v('end_date'), 'Y');

    // Subject Instructor of the student's program on this campus.
    $programId = $document->internship->program_id ?: $student?->program_id;
    $subjectInstructor = null;
    if ($campus?->id && $programId) {
        $subjectInstructor = \App\Models\Instructor::with('user')
            ->where('campus_id', $campus->id)
            ->where('program_id', $programId)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->first();
    }
    $subjectInstructorName = $subjectInstructor?->user?->name ?: '';

    // Campus Internship Coordinator.
    $campusCoordinator = null;
    if ($campus?->id) {
        $campusCoordinator = \App\Models\User::with('role')
            ->where('campus_id', $campus->id)
            ->whereHas('role', fn ($q) => $q->where('code', 'coordinator'))
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
    }
@endphp

<button class="no-print" onclick="window.print()">Print</button>

<table class="page">
<thead><tr><td><div class="form-control-no">FM-AA-INT-15<br>Rev. 0<br>14-Oct-2024</div></td></tr></thead>
<tbody><tr><td>

<div class="header">
    <h1>INTERNSHIP RELEASE FORM</h1>
    <div class="sub">PANGASINAN STATE UNIVERSITY</div>
    <img src="{{ asset('images/psu-logo.png') }}" alt="PSU">
</div>

<div class="campus-line"><span class="u" style="min-width:0.9in;">{{ $campusShort }}</span> Campus</div>

<div class="addr">
    <div class="line">{{ $memoDate }}</div>
    <div class="cap">Date</div>
</div>

<div class="addr" style="margin-top:34px;">
    <div class="line">{{ $studentName }}</div>
</div>
<div class="addr">
    <div class="line">Student-Intern</div>
</div>
<div class="addr">
    <div class="line">Pangasinan State University – {{ $campusLabel }}</div>
</div>

<div class="field" style="margin-top:26px;"><b>Course:</b> <span class="u" style="min-width:2.1in;">{{ $v('course') }}</span></div>
<div class="field"><b>Major:</b>&nbsp;&nbsp; <span class="u" style="min-width:2.1in;">{{ $major }}</span></div>

<p class="body-text">
    Upon completion and submission of all your required documents for your Internship / Practicum activity,
    you are hereby released to undergo <span class="u" style="min-width:0.6in;"><strong>{{ $requiredHours }}</strong></span>
    hours Internship / Practicum at
</p>

<div class="field"><b>Cooperating Company/Agency/Firm:</b> <span class="u" style="min-width:3.3in;">{{ $v('company_name') }}</span></div>
<div class="field"><b>Company/Agency/Firm Address:</b> <span class="u" style="min-width:3.1in;">{{ $v('company_address') }}</span></div>

<p style="margin-top:6px;">
    From <span class="u" style="min-width:1.1in;">{{ $startMD }}</span>,
    <span class="u" style="min-width:0.7in;">{{ $startY }}</span> to
    <span class="u" style="min-width:1.1in;">{{ $endMD }}</span>,
    <span class="u" style="min-width:0.7in;">{{ $endY }}</span>.
</p>

<p style="margin-top:22px;">
    Please be guided with the school and company Internship / Practicum policies and guidelines
    throughout the duration of your Internship / Practicum Activity
</p>

<table class="sigs">
    <tr>
        <td><div class="sig"><div class="name">{{ $subjectInstructorName }}</div><div class="cap">Internship / Practicum Subject Instructor</div></div></td>
        <td><div class="sig"><div class="name">{{ $campusCoordinator?->name ?: '' }}</div><div class="cap">Campus Internship Coordinator</div></div></td>
    </tr>
</table>

<div class="noted">
    Noted:
    <div class="sig"><div class="name">{{ $cedName }}</div><div class="cap">Campus Executive Director</div></div>
</div>

</td></tr></tbody></table>

</body>
</html>
