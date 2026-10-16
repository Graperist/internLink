<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Training Supervisor’s Feedback Form — {{ $document->internship->student->user->name }}</title>
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
            padding-right: 0.1in;
        }

        .sheet { border: 1.5px solid #000; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th {
            border: 1px solid #000;
            padding: 2px 5px;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }
        .sheet table:first-child tr:first-child > * { border-top: 0; }
        .sheet td:first-child, .sheet th:first-child { border-left: 0; }
        .sheet td:last-child, .sheet th:last-child { border-right: 0; }
        .sheet table + table { margin-top: -1px; }

        th { font-weight: 400; text-align: center; }
        .label { font-weight: 700; text-transform: uppercase; }

        /* Header */
        .head td { height: 78px; }
        .head .logo { text-align: center; padding: 2px; }
        .head .logo img { width: 62px; height: 62px; object-fit: contain; display: block; margin: 0 auto; }
        .head .title { text-align: center; vertical-align: top; padding-top: 4px; }
        .head h1 { font-size: 16pt; margin: 0 0 3px; font-weight: 700; line-height: 1.1; }
        .head .sub { font-size: 7pt; line-height: 1.3; }

        .info td { height: 19px; }

        .rating th { font-size: 7.5pt; height: 30px; }
        .rating th.crit-head { font-weight: 700; }
        .rating td { height: 19px; }
        .rating td.crit { text-align: left; }
        .rating td.mark { text-align: center; font-weight: 700; font-size: 10pt; }

        .free td { vertical-align: top; padding: 5px 6px; white-space: pre-line; }
        .free .title { font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 3px; }
        .free .comments { height: 4.6in; }

        .sign { break-inside: avoid; page-break-inside: avoid; }
        .sign td { height: 0.95in; vertical-align: top; padding: 4px 6px; }
        .sig { width: 38%; margin: 24px auto 0; text-align: center; }
        .sig .name { border-bottom: 1px solid #000; min-height: 14px; font-weight: 700; font-size: 8.5pt; padding: 0 4px 1px; }
        .sig .cap { font-style: italic; font-size: 7.5pt; margin-top: 2px; }
        .sig .date { font-style: italic; font-size: 7.5pt; margin-top: 14px; }

        @media print {
            .no-print { display: none; }
            body { max-width: none; }
        }
            /* Signatory names are printed in capital letters on every form. */
        .sig .name { text-transform: uppercase; }
    </style>
</head>
<body @if ($autoPrint ?? true) onload="window.print()" @endif>
@php
    $internship = $document->internship;
    $student = $internship->student;
    $campus = $student?->campus ?? $internship->campus;

    // "Alaminos City" - the campus name without the university prefix or the
    // word "Campus", which the form already prints after the blank.
    $campusName = preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', trim((string) ($campus?->name ?? '')));
    $campusName = trim(preg_replace('/\s*campus\s*$/i', '', $campusName));

    $field = fn (string $key) => $document->fieldValue($key);

    // Fall back to the records we already hold when the supervisor left a box empty.
    $supervisorName = $field('supervisor_name');
    $department = $field('department');
    $companyName = $field('company_name') ?: ($internship->deployment?->hte?->name ?? '');
    $studentName = $field('student_name') ?: $student->user->name;

    // Criteria wording exactly as printed on the official form (FM-AA-INT-18).
    $criteria = [
        'punctual' => 'The student-trainee is punctual in attending works and assignments',
        'sufficient_knowledge' => 'The student-trainee has sufficient knowledge to contribute in the organization',
        'teamwork' => 'The student-trainee knows how to work with the group.',
        'training_plan_tasks' => 'The student-trainee performs tasks as prescribed in the Internship Training Plan',
        'company_policies' => 'The student-trainee follows and abides with the policies of the company.',
        'upright_conduct' => 'The student-trainee maintains an upright conduct while in the company.',
        'desirable_traits' => 'The student-trainee shows desirable traits, virtues, and work habits.',
    ];
@endphp

@isset($backUrl)
    <a class="no-print" href="{{ $backUrl }}" style="margin-right: 8px;">&larr; Back</a>
@endisset
<button class="no-print" onclick="window.print()">{{ isset($backUrl) ? 'Download PDF' : 'Print' }}</button>

<div class="form-control-no">
    FM-AA-INT-18<br>
    Rev. 1<br>
    14-Oct-2024
</div>

<div class="sheet">

<table class="head">
    <colgroup><col style="width:13%"><col style="width:87%"></colgroup>
    <tr>
        <td class="logo"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU"></td>
        <td class="title">
            <h1>TRAINING SUPERVISOR’S FEEDBACK FORM</h1>
            <div class="sub">PANGASINAN STATE UNIVERSITY</div>
            <div class="sub">{{ $campusName !== '' ? $campusName : '______' }} Campus</div>
        </td>
    </tr>
</table>

<table class="info">
    <colgroup><col style="width:27.7%"><col style="width:72.3%"></colgroup>
    <tr><td class="label">Name of Training Supervisor</td><td>{{ $supervisorName }}</td></tr>
    <tr><td class="label">Department</td><td>{{ $department }}</td></tr>
    <tr><td class="label">Company Name</td><td>{{ $companyName }}</td></tr>
    <tr><td class="label">Name of Student-Intern</td><td>{{ $studentName }}</td></tr>
</table>

<table class="rating">
    <colgroup>
        <col style="width:49.5%">
        <col style="width:10.1%"><col style="width:10.1%"><col style="width:10.1%"><col style="width:10.1%"><col style="width:10.1%">
    </colgroup>
    <tr>
        <th class="crit-head">CRITERIA</th>
        <th>Strongly<br>Agree</th>
        <th>Agree</th>
        <th>Neither Agree nor Disagree</th>
        <th>Disagree</th>
        <th>Strongly<br>Disagree</th>
    </tr>
    @foreach ($criteria as $key => $wording)
        <tr>
            <td class="crit">{{ $wording }}</td>
            @foreach (['5', '4', '3', '2', '1'] as $val)
                <td class="mark">{{ $field($key) === $val ? "\u{2713}" : '' }}</td>
            @endforeach
        </tr>
    @endforeach
</table>

<table class="free">
    <tr><td class="comments"><span class="title">Other Comments and Suggestions</span>{{ $field('other_comments') }}</td></tr>
</table>

<table class="sign">
    <tr>
        <td>
            <div class="sig">
                <div class="name">{{ $supervisorName }}</div>
                <div class="cap">Signature of Training Supervisor</div>
                <div class="date">Date: _______________________________</div>
            </div>
        </td>
    </tr>
</table>

</div>

</body>
</html>
