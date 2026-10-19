<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student-Intern’s Feedback Form — {{ $document->internship->student->user->name }}</title>
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

        th { font-weight: 700; text-align: center; }
        .label { font-weight: 700; text-transform: uppercase; }

        /* Header */
        .head td { height: 74px; }
        .head .logo { text-align: center; padding: 2px; }
        .head .logo img { width: 58px; height: 58px; object-fit: contain; display: block; margin: 0 auto; }
        .head .title { text-align: center; }
        .head h1 { font-size: 16pt; margin: 0 0 4px; font-weight: 700; line-height: 1.1; }
        .head .sub { font-size: 7pt; line-height: 1.3; }

        .info td { height: 18px; }

        .rating th { font-size: 7.5pt; height: 26px; }
        .rating td { height: 18px; }
        .rating td.crit { text-align: left; }
        .rating td.mark { text-align: center; font-weight: 700; font-size: 10pt; }

        .free td { vertical-align: top; padding: 5px 6px; white-space: pre-line; }
        .free .title { font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 3px; }
        .free .problems { height: 2.25in; }
        .free .concerns { height: 2.1in; }

        .sign { break-inside: avoid; page-break-inside: avoid; }
        .sign td { height: 100px; vertical-align: top; padding: 4px 6px; }
        .sig { width: 38%; margin: 40px auto 0; text-align: center; }
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
    @include('partials.a4-page')
</head>
<body @if ($autoPrint ?? true) onload="window.print()" @endif>
@php
    $internship = $document->internship;
    $student = $internship->student;
    $campus = $student?->campus ?? $internship->campus;

    // "Alaminos City Campus" - campus label without the university prefix.
    $campusLabel = preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', trim((string) ($campus?->name ?? ''))) ?: 'Alaminos City Campus';
    if (! preg_match('/campus\s*$/i', $campusLabel)) {
        $campusLabel .= ' Campus';
    }

    $companyName = $internship->deployment?->hte?->name ?? '';
    $courseName = $student->program?->name ?? ($internship->program->name ?? '');

    // The student's department is not a record of its own - use the one named on
    // any form already filed for this internship; otherwise it stays blank to
    // write in.
    $department = $internship->documents()->with('fieldValues')->get()
        ->map(fn ($doc) => $doc->fieldValue('department'))
        ->filter()
        ->first() ?? '';

    $rate = fn (string $key) => $document->fieldValue($key);

    // Criteria wording exactly as printed on the official form (FM-AA-INT-17).
    $officialWording = [
        'training_aligned' => 'My training is aligned with my field of specialization',
        'training_challenging' => 'My training is challenging.',
        'learning_opportunities' => 'I have opportunities for learning.',
        'aware_of_policies' => 'I am aware with the policies of the company.',
        'positive_relationship' => 'I have a positive working relationship with my supervisor and other employees of the company.',
        'aware_of_risks' => 'I am aware of the risks and hazards of my working environment.',
        'department_safety' => 'My department is committed to ensuring the health and safety of its student-interns.',
    ];
@endphp

@isset($backUrl)
    <a class="no-print" href="{{ $backUrl }}" style="margin-right: 8px;">&larr; Back</a>
@endisset
<button class="no-print" onclick="window.print()">{{ isset($backUrl) ? 'Download PDF' : 'Print' }}</button>

<div class="form-control-no">
    FM-AA-INT-17<br>
    Rev. 1<br>
    14-Oct-2024
</div>

<div class="sheet">

<table class="head">
    <colgroup><col style="width:13%"><col style="width:87%"></colgroup>
    <tr>
        <td class="logo"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU"></td>
        <td class="title">
            <h1>STUDENT-INTERN’S FEEDBACK FORM</h1>
            <div class="sub">PANGASINAN STATE UNIVERSITY</div>
            <div class="sub">{{ $campusLabel }}</div>
        </td>
    </tr>
</table>

<table class="info">
    <colgroup><col style="width:18.7%"><col style="width:81.3%"></colgroup>
    <tr><td class="label">Name of Student-Intern</td><td>{{ $student->user->name }}</td></tr>
    <tr><td class="label">Course</td><td>{{ $courseName }}</td></tr>
    <tr><td class="label">Department</td><td>{{ $department }}</td></tr>
    <tr><td class="label">Company Name</td><td>{{ $companyName }}</td></tr>
</table>

<table class="rating">
    <colgroup>
        <col style="width:50%">
        <col style="width:10%"><col style="width:10%"><col style="width:10%"><col style="width:10%"><col style="width:10%">
    </colgroup>
    <tr>
        <th>CRITERIA</th>
        <th>Strongly<br>Agree</th>
        <th>Agree</th>
        <th>Neither Agree nor Disagree</th>
        <th>Disagree</th>
        <th>Strongly<br>Disagree</th>
    </tr>
    @foreach ($document->requirement->fieldDefinitions->where('field_type', 'rating_scale') as $field)
        <tr>
            <td class="crit">{{ $officialWording[$field->field_key] ?? $field->label }}</td>
            @foreach (['5', '4', '3', '2', '1'] as $val)
                <td class="mark">{{ $rate($field->field_key) === $val ? "\u{2713}" : '' }}</td>
            @endforeach
        </tr>
    @endforeach
</table>

<table class="free">
    <tr><td class="problems"><span class="title">Problems Met:</span>{{ $document->fieldValue('problems_met') }}</td></tr>
</table>

<table class="free">
    <tr><td class="concerns"><span class="title">Other Concerns:</span>{{ $document->fieldValue('other_concerns') }}</td></tr>
</table>

<table class="sign">
    <tr>
        <td>
            <div class="sig">
                <div class="name">{{ $student->user->name }}</div>
                <div class="cap">Signature of Student-Intern</div>
                <div class="date">Date: _______________________________</div>
            </div>
        </td>
    </tr>
</table>

</div>

</body>
</html>
