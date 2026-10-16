<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evaluation Instrument of PSU Partner Agencies (Subject Instructor) — {{ $document->internship->student->user->name }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 0.5in 0.5in 0.3in; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #000;
            margin: 0 auto;
            max-width: 7.27in;
            line-height: 1.2;
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
        .head td { height: 62px; }
        .head .logo { text-align: center; padding: 2px; }
        .head .logo img { width: 54px; height: 54px; object-fit: contain; display: block; margin: 0 auto; }
        .head .title { text-align: center; }
        .head h1 { font-size: 15pt; margin: 0 0 3px; font-weight: 700; line-height: 1.1; }
        .head .sub { font-size: 7pt; line-height: 1.3; }

        .info td { height: 17px; }
        .rater td { height: 19px; }
        .rater .opt { display: inline-block; margin-right: 0.3in; font-weight: 700; }
        .box { display: inline-block; width: 9px; height: 9px; border: 1px solid #000; margin-right: 5px; vertical-align: -1px; text-align: center; line-height: 8px; font-size: 8pt; }

        .direction td { text-align: center; font-style: italic; font-size: 7.5pt; padding: 4px 8px; line-height: 1.5; }
        .direction b { font-style: normal; }

        .rating td, .rating th { height: 28px; }
        .rating th { font-size: 8pt; }
        .rating td.crit { text-align: left; padding-left: 8px; }
        .rating td.mark { text-align: center; font-weight: 700; font-size: 10pt; }
        .rating tr.group td { font-weight: 700; text-transform: uppercase; height: 18px; padding: 1px 5px; }

        .sign { break-inside: avoid; page-break-inside: avoid; }
        .sign td { height: 82px; vertical-align: top; padding: 4px 6px; }
        .sig { width: 46%; margin: 36px auto 0; text-align: center; }
        .sig .name { border-bottom: 1px solid #000; min-height: 14px; font-weight: 700; font-size: 8.5pt; padding: 0 4px 1px; }
        .sig .cap { font-style: italic; font-size: 7.5pt; margin-top: 2px; }

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

    // "Alaminos City Campus" - campus label without the university prefix.
    $campusLabel = preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', trim((string) ($campus?->name ?? ''))) ?: 'Alaminos City Campus';
    if (! preg_match('/campus\s*$/i', $campusLabel)) {
        $campusLabel .= ' Campus';
    }

    $hte = $internship->deployment?->hte;
    $rate = fn (string $key) => $document->fieldValue($key);

    $evaluator = $rate('rater_name') ?: ($document->uploader?->name ?? '');
    $name = $evaluator;
    $agency = $rate('agency_name') ?: ($hte?->name ?? '');
    $unit = $rate('unit_division') ?: '';
    $sex = $rate('sex_gender') ?: '';

    // The paper form says "Faculty" where the system option says "Subject Instructor".
    $raterType = match ($rate('rater_type') ?: 'Subject Instructor') {
        'Self Ratee' => 'self',
        'Subject Instructor', 'Faculty' => 'faculty',
        default => 'student',
    };

    $groups = $document->requirement->groupedFieldDefinitions()
        ->filter(fn ($fields, $group) => $group && $fields->contains('field_type', 'rating_scale'));
@endphp

@isset($backUrl)
    <a class="no-print" href="{{ $backUrl }}" style="margin-right: 8px;">&larr; Back</a>
@endisset
<button class="no-print" onclick="window.print()">{{ isset($backUrl) ? 'Download PDF' : 'Print' }}</button>

<div class="form-control-no">
    FM-AA-INT-19a<br>
    Rev. 1<br>
    14-Oct-2024
</div>

<div class="sheet">

<table class="head">
    <colgroup><col style="width:13%"><col style="width:87%"></colgroup>
    <tr>
        <td class="logo"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU"></td>
        <td class="title">
            <h1>EVALUATION INSTRUMENT OF PSU PARTNER AGENCIES</h1>
            <div class="sub">PANGASINAN STATE UNIVERSITY</div>
            <div class="sub">{{ $campusLabel }}</div>
        </td>
    </tr>
</table>

<table class="info">
    <colgroup><col style="width:17.5%"><col style="width:46%"><col style="width:12.5%"><col style="width:24%"></colgroup>
    <tr><td class="label">Name</td><td colspan="3">{{ $name }}</td></tr>
    <tr><td class="label">Agency / Institution</td><td colspan="3">{{ $agency }}</td></tr>
    <tr><td class="label">Unit/Division</td><td colspan="3">{{ $unit }}</td></tr>
    <tr><td class="label">Sex - Gender</td><td colspan="3">{{ $sex }}</td></tr>
</table>

<table class="rater">
    <colgroup><col style="width:17.5%"><col style="width:82.5%"></colgroup>
    <tr>
        <td class="label">Type of Rater</td>
        <td>
            <span class="opt"><span class="box">{{ $raterType === 'self' ? "\u{2713}" : '' }}</span>Self Ratee (Representative of HTE)</span>
            <span class="opt"><span class="box">{{ $raterType === 'faculty' ? "\u{2713}" : '' }}</span>Faculty</span>
            <span class="opt"><span class="box">{{ $raterType === 'student' ? "\u{2713}" : '' }}</span>Student</span>
        </td>
    </tr>
</table>

<table class="direction">
    <tr>
        <td>
            <b>DIRECTION</b>: Please put a check mark (&#10003;) under the appropriate column under level of satisfaction and opposite each criterion which corresponds to your answer. Use the following scale to your answer:<br>
            <b><i>5 - Extremely Satisfied, 4 - Highly Satisfied, 3 - Satisfied, 2 - Moderately Satisfied, 1 - Not Satisfied</i></b>
        </td>
    </tr>
</table>

<table class="rating">
    <colgroup>
        <col style="width:75%">
        <col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%">
    </colgroup>
    <tr>
        <th>CRITERIA</th><th>5</th><th>4</th><th>3</th><th>2</th><th>1</th>
    </tr>
    @foreach ($groups as $groupLabel => $fields)
        <tr class="group"><td colspan="6">{{ $groupLabel }}</td></tr>
        @foreach ($fields->where('field_type', 'rating_scale')->values() as $i => $field)
            <tr>
                <td class="crit">{{ $i + 1 }}. {{ $field->label }}</td>
                @foreach (['5', '4', '3', '2', '1'] as $val)
                    <td class="mark">{{ $rate($field->field_key) === $val ? "\u{2713}" : '' }}</td>
                @endforeach
            </tr>
        @endforeach
    @endforeach
</table>

<table class="sign">
    <tr>
        <td>
            <span class="label">Evaluated by:</span>
            <div class="sig">
                <div class="name">{{ $evaluator }}</div>
                <div class="cap">Internship / Practicum Subject Instructor</div>
            </div>
        </td>
    </tr>
</table>

</div>

</body>
</html>
