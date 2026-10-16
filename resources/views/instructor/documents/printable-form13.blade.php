<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Internship Evaluation Form — {{ $document->internship->student->user->name }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 0.45in 0.5in 0.4in; }

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

        .intro td { text-align: center; font-size: 7.5pt; padding: 3px 8px; line-height: 1.3; }
        .hours { display: inline-block; min-width: 0.55in; border-bottom: 1px solid #000; text-align: center; font-weight: 700; }

        .info td { height: 17px; }
        .info td.right { text-align: right; }

        .instructions td { text-align: center; font-size: 7.5pt; padding: 4px 8px; line-height: 1.3; }

        /* Competency / rating grid */
        .grid thead { display: table-header-group; }
        .grid tr { break-inside: avoid; page-break-inside: avoid; }
        .grid th { font-size: 8pt; height: 17px; }
        .grid td.comp { text-align: center; font-weight: 700; text-transform: uppercase; }
        .grid td.crit { text-align: left; font-size: 7.5pt; line-height: 1.15; }
        .grid td.rate { text-align: center; font-size: 8pt; }
        .grid td.remarks { text-align: left; vertical-align: top; font-size: 7.5pt; }
        .circle {
            display: inline-block; min-width: 15px; height: 15px; line-height: 13px;
            border: 1.5px solid transparent; border-radius: 50%; text-align: center;
        }
        .circle.on { border-color: #000; font-weight: 700; }

        /* OJT grade */
        .grade td { padding: 6px 8px; }
        .grade .formula { text-align: center; line-height: 1.4; }
        .grade .formula b { font-size: 8pt; }
        .grade .result { text-align: center; font-weight: 700; font-size: 10pt; }
        .grade .pts { font-size: 7pt; font-weight: 400; }

        .discuss td { text-align: center; padding: 6px 8px; line-height: 1.35; }
        .comments td { height: 44px; vertical-align: top; padding: 4px 8px; white-space: pre-line; }

        /* Termination checklist */
        .term td { border-top: 0; border-bottom: 0; padding: 3px 8px; font-size: 8pt; vertical-align: top; }
        .term td.head { font-style: italic; font-size: 9pt; padding-top: 4px; }
        .blank { display: inline-block; min-width: 0.34in; border-bottom: 1px solid #000; text-align: center; font-weight: 700; margin-right: 3px; line-height: 1.05; }
        .blank.wide { min-width: 1.45in; text-align: left; }
        .term .big td { font-style: italic; font-size: 9pt; }

        /* Signatures */
        .sign { break-inside: avoid; page-break-inside: avoid; }
        .sign td { vertical-align: top; padding: 5px 8px; }
        .sign .date { margin-bottom: 18px; }
        .sig { width: 78%; margin: 22px auto 0; text-align: center; }
        .sig .name { border-bottom: 1px solid #000; min-height: 14px; font-weight: 700; padding: 0 4px 1px; }
        .sig .cap { font-style: italic; font-size: 7.5pt; margin-top: 2px; }
        .approve td { height: 78px; text-align: center; vertical-align: top; padding-top: 4px; }
        .approve .sig { width: 42%; margin-top: 26px; }

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
    $requirement = $document->requirement;
    $internship = $document->internship;
    $student = $internship->student;
    $campus = $student?->campus ?? $internship->campus;
    $hte = $internship->deployment?->hte;

    $values = $document->fieldValues->keyBy('field_key');
    $value = fn (string $key) => $values->get($key)?->value;
    $checked = fn (string $key) => in_array((string) $value($key), ['1', 'true', 'on'], true);

    $competencies = \App\Support\InternshipEvaluationForm::COMPETENCIES;
    $ratings = \App\Support\InternshipEvaluationForm::ratingsFrom($value);
    $points = \App\Support\InternshipEvaluationForm::totalPoints($ratings);
    $grade = \App\Support\InternshipEvaluationForm::grade($ratings);

    // "Alaminos City Campus" - campus label without the university prefix.
    $campusLabel = preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', trim((string) ($campus?->name ?? ''))) ?: '';
    if ($campusLabel !== '' && ! preg_match('/campus\s*$/i', $campusLabel)) {
        $campusLabel .= ' Campus';
    }
    $campusLabel = $campusLabel ?: '_________ Campus';

    $hours = $student?->program?->required_hours ?? $internship->required_hours ?? null;
    $evaluator = $value('supervisor_signature_name') ?: ($value('supervisor_name') ?: ($document->uploader?->name ?? ''));
    $supervisor = $value('supervisor_name') ?: $evaluator;
    $dash = fn ($v) => filled($v) ? $v : '';
    // The student's own record is the source of truth; the HTE-typed name is only a fallback.
    $studentName = $student?->user?->name ?: ($value('student_name') ?: '');
@endphp

@isset($backUrl)
    <a class="no-print" href="{{ $backUrl }}" style="margin-right: 8px;">&larr; Back</a>
@endisset
<button class="no-print" onclick="window.print()">{{ isset($backUrl) ? 'Download PDF' : 'Print' }}</button>

<div class="form-control-no">
    FM-AA-INT-11<br>
    Rev. 1<br>
    14-Oct-2024
</div>

<div class="sheet">

<table class="head">
    <colgroup><col style="width:13%"><col style="width:87%"></colgroup>
    <tr>
        <td class="logo"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU"></td>
        <td class="title">
            <h1>INTERNSHIP EVALUATION FORM</h1>
            <div class="sub">PANGASINAN STATE UNIVERSITY</div>
            <div class="sub">{{ $campusLabel }}</div>
        </td>
    </tr>
</table>

<table class="intro">
    <tr>
        <td>
            This form is to be completed by the host company or a designated supervisor. This form shall be completed and
            submitted to the office of Campus Internship Coordinator and Internship / Practicum Subject Instructor upon
            completion of <span class="hours">{{ $hours }}</span> training hours.
        </td>
    </tr>
</table>

<table class="info">
    <colgroup><col style="width:21%"><col style="width:39%"><col style="width:14%"><col style="width:26%"></colgroup>
    <tr><td class="label">Name of Student-Intern</td><td colspan="3">{{ $studentName }}</td></tr>
    <tr><td class="label">Name of Company / Firm / Agency</td><td colspan="3">{{ $dash($value('company_name')) ?: $hte?->name }}</td></tr>
    <tr><td class="label">Company Address</td><td colspan="3">{{ $dash($value('company_address')) ?: $hte?->address }}</td></tr>
    <tr>
        <td class="label">Date Started</td><td>{{ $value('date_started') }}</td>
        <td class="label right">Date Ended</td><td>{{ $value('date_ended') }}</td>
    </tr>
</table>

<table class="instructions">
    <tr>
        <td>
            <b>INSTRUCTIONS:</b> Evaluate Student-Intern fairly and honestly based on his/her performance considering their
            time in the trade. Rate all 8 competencies. There are criteria under each competency. Please rate by encircling
            the appropriate number that best describes the extent of the performance of the Student- Intern.
        </td>
    </tr>
</table>

<table class="grid">
    <colgroup><col style="width:21%"><col style="width:50%"><col style="width:8%"><col style="width:21%"></colgroup>
    <thead>
        <tr><th>COMPETENCY</th><th>CRITERIA</th><th>RATING</th><th>REMARKS</th></tr>
    </thead>
    <tbody>
    @foreach ($competencies as $key => $competency)
        @foreach ($competency['levels'] as $rating => $descriptor)
            <tr>
                @if ($loop->first)
                    <td class="comp" rowspan="{{ count($competency['levels']) }}">
                        {{ $competency['letter'] }}. {{ $competency['name'] }} ({{ $competency['weight'] }}%)
                    </td>
                @endif
                <td class="crit">{{ $descriptor }}</td>
                <td class="rate"><span class="circle {{ (string) ($ratings[$key] ?? '') === (string) $rating ? 'on' : '' }}">{{ $rating }}</span></td>
                @if ($loop->first)
                    <td class="remarks" rowspan="{{ count($competency['levels']) }}">{{ $value('remarks_'.$key) }}</td>
                @endif
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>

<table class="grade">
    <colgroup><col style="width:79%"><col style="width:21%"></colgroup>
    <tr>
        <td class="formula">
            <b>OJT GRADE</b><br>
            = Total Points (A + B + C + D + E + F + G+H x 10) / 50 x 50 + 50
            @if ($points !== null)
                <div class="pts">Total Points: {{ number_format($points, 2) }} / 50</div>
            @endif
        </td>
        <td class="result">{{ $grade !== null ? number_format($grade, 2) : '' }}</td>
    </tr>
</table>

<table class="discuss">
    <tr>
        <td>
            Please discuss strengths &amp; weaknesses with the student trainee to encourage and motivate improve performance.<br>
            NOTE: When you terminate a student-trainee for any reason, please check the items below.
        </td>
    </tr>
</table>
@if (filled($value('strengths_weaknesses')))
<table class="comments">
    <tr><td>{{ $value('strengths_weaknesses') }}</td></tr>
</table>
@endif

<table class="term">
    <colgroup><col style="width:50%"><col style="width:50%"></colgroup>
    <tr><td class="head" colspan="2">The Internship Practicum was terminated:</td></tr>
    <tr>
        <td><span class="blank">{{ $checked('termination_lack_of_work') ? "\u{2713}" : '' }}</span>due &ldquo;only&rdquo; for lack of work</td>
        <td><span class="blank">{{ $checked('termination_absences_tardiness') ? "\u{2713}" : '' }}</span>too much absences and tardiness</td>
    </tr>
    <tr>
        <td><span class="blank">{{ $checked('termination_company_rules') ? "\u{2713}" : '' }}</span>violation of Company Rules</td>
        <td><span class="blank">{{ $checked('termination_disrespectful') ? "\u{2713}" : '' }}</span>disrespectful to co-trainee or personnel</td>
    </tr>
    <tr>
        <td><span class="blank">{{ $checked('termination_unfavorable_habits') ? "\u{2713}" : '' }}</span>unfavorable work habits and practices</td>
        <td><span class="blank">{{ $checked('termination_no_interest') ? "\u{2713}" : '' }}</span>does not demonstrate interest and desire to learn</td>
    </tr>
    <tr>
        <td><span class="blank">{{ $checked('termination_altercation') ? "\u{2713}" : '' }}</span>altercation on the job</td>
        <td><span class="blank">{{ $checked('termination_other') ? "\u{2713}" : '' }}</span>other(s), please specify <span class="blank wide">{{ $value('other_termination_reason') }}</span></td>
    </tr>
    <tr class="big">
        <td colspan="2"><span class="blank">{{ $checked('future_employment') ? "\u{2713}" : '' }}</span>We would be pleased to employ this Student-Trainee in the future</td>
    </tr>
    <tr class="big">
        <td colspan="2"><span class="blank">{{ $checked('needs_improvement') ? "\u{2713}" : '' }}</span>He/She needs to improve his/her performance.</td>
    </tr>
</table>

<table class="sign">
    <colgroup><col style="width:50%"><col style="width:50%"></colgroup>
    <tr>
        <td>
            <div class="date">Date: <span class="hours" style="min-width:1.3in;font-weight:400;">{{ $value('evaluation_date') }}</span></div>
            <div class="sig">
                <div class="name">{{ $evaluator }}</div>
                <div class="cap">Printed Name and Signature of Person Completing this Form</div>
            </div>
        </td>
        <td>
            <div class="sig" style="margin-top: 40px;">
                <div class="name">{{ $studentName }}</div>
                <div class="cap">Signature of Student-Intern</div>
            </div>
            <div style="text-align:center; margin-top: 5px;">Date: <span class="hours" style="min-width:1.2in;font-weight:400;">&nbsp;</span></div>
        </td>
    </tr>
</table>

<table class="approve">
    <tr>
        <td>
            <b>APPROVED:</b>
            <div class="sig">
                <div class="name">{{ $supervisor }}</div>
                <div class="cap">Training Supervisor</div>
            </div>
        </td>
    </tr>
</table>

</div>

</body>
</html>
