<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Endorsement Letter — {{ $document->internship->student->user->name }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 10mm 25.4mm 12mm; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            margin: 0 auto;
            max-width: 6.27in;
            line-height: 1.25;
        }

        .no-print { margin-bottom: 12px; }

        .form-control-no {
            text-align: right;
            font-size: 7pt;
            font-style: italic;
            line-height: 1.15;
        }

        .header { text-align: center; margin-bottom: 26px; }
        .header-logo { width: 83px; height: 83px; object-fit: contain; display: block; margin: 0 auto 4px; }
        .header h1 { font-size: 16pt; margin: 0 0 3px; font-weight: 700; line-height: 1.1; }
        .header .sub { font-size: 7.5pt; line-height: 1.25; }

        .line-block { width: 2.1in; margin-bottom: 8px; }
        .line-value {
            min-height: 15px;
            border-bottom: 1px solid #000;
            padding: 0 2px 1px;
            font-size: 10pt;
        }
        .line-caption { text-align: center; font-size: 8pt; }
        .date-block { margin-bottom: 22px; }
        .recipient { margin-bottom: 20px; }
        .recipient .line-block { margin-bottom: 11px; }

        .salutation { margin: 0 0 12px 0; font-weight: 700; }

        p { margin: 0 0 10px; text-align: justify; }

        .fill { text-decoration: underline; white-space: pre-wrap; }

        .venue-lines { margin: 18px 0.35in 18px; }
        .venue-lines div { height: 21px; border-bottom: 1px solid #000; }

        .closing { margin-top: 22px; }

        .signature { margin-top: 62px; display: inline-block; min-width: 2.6in; text-align: center; }
        .signature-line {
            border-bottom: 1px solid #000;
            padding: 0 8px 2px;
            min-height: 16px;
            font-weight: 700;
            font-size: 10pt;
        }
        .sig-title { font-size: 9pt; font-weight: 700; font-style: italic; margin-top: 2px; }

        @media print {
            .no-print { display: none; }
            body { max-width: none; }
        }
            /* Signatory names are printed in capital letters on every form. */
        .signature-line { text-transform: uppercase; }
    </style>
</head>
<body>
@php
    $value = fn (string $key, string $fallback = '____________________________')
        => $document->fieldValue($key) ?: $fallback;

    $internship = $document->internship;
    $student = $internship?->student;
    $studentName = $student?->user?->name ?: '____________________________';

    // The CED and campus identity must always come from the student's
    // campus settings record, not from a hardcoded signatory or the
    // reviewing instructor's campus.
    $campus = $student?->campus ?? $internship?->campus;
    $campusName = trim((string) ($campus?->name ?? 'Alaminos City Campus'));
    $campusLabel = preg_replace(
        '/^Pangasinan State University\s*[-,:]\s*/i',
        '',
        $campusName
    ) ?: 'Alaminos City Campus';

    $cedName = trim((string) ($campus?->ced_name ?? ''));
    $endorsementDate = $value('endorsement_date');
    $companyName = $value('company_name', '');
    $recipientName = $value('recipient_name');
    $recipientOffice = $value('recipient_office');
    $recipientAddress = $value('recipient_address');
    $recipientSalutation = strtoupper(trim((string) $value('recipient_salutation', 'Sir/ Madam')));
    $studentTitle = $value('student_title', 'Mr./Ms.');
    $blank = '____________________________';
    $show = fn ($v) => $v === $blank ? '' : $v;
    $course = $value('course', $student?->program?->name ?: '____________________________');
    $major = $value('major', 'N/A');

    try {
        $formattedDate = \Carbon\Carbon::parse($endorsementDate)->format('F d, Y');
    } catch (\Throwable $e) {
        $formattedDate = $endorsementDate;
    }
@endphp

<button class="no-print" onclick="window.print()">Print</button>

<div class="form-control-no">
    FM-AA-INT-06<br>
    Rev. 02<br>
    03-Oct-2024
</div>

<div class="header">
    <img class="header-logo" src="{{ asset('images/psu-logo.png') }}" alt="PSU">
    <h1>ENDORSEMENT LETTER</h1>
    <div class="sub">PANGASINAN STATE UNIVERSITY</div>
    <div class="sub">{{ $campusLabel }}</div>
</div>

<div class="line-block date-block">
    <div class="line-value">{{ $formattedDate === $blank ? '' : $formattedDate }}</div>
    <div class="line-caption">Date</div>
</div>

<div class="recipient">
    <div class="line-block"><div class="line-value">{{ $show($recipientName) }}</div></div>
    <div class="line-block"><div class="line-value">{{ $show($recipientOffice ?: $companyName) }}</div></div>
    <div class="line-block"><div class="line-value">{{ $show($recipientAddress) }}</div></div>
</div>

<div class="salutation">{{ $recipientSalutation }}:</div>

<p>
    This is to introduce the bearer, {{ $studentTitle }}
    <span class="fill">&nbsp;{{ $studentName }}&nbsp;</span>
    a bonafide student of this University who wishes to undergo Internship in your company,
    as an academic requirement for graduation leading degree
    <span class="fill">&nbsp;{{ $show($course) }}@if($major && $major !== 'N/A') major in {{ $major }}@endif&nbsp;</span>.
</p>

<p>
    While we vouch for the good moral character of the student, he/she is still subject to your rules and
    regulations especially on office decorum and discipline. Termination of internship training will be on
    your discretion.
</p>

<p>Venues of training that may be assigned to the practicum trainee maybe in any of these areas/positions:</p>

<div class="venue-lines">
    <div></div><div></div><div></div><div></div><div></div>
</div>

<p>
    If considered by your company for internship, we are attaching herewith an Internship Agreement, Resume,
    and later, an Internship Evaluation Form for final assessment of trainee’s productivity and performance
    in assigned tasks.
</p>

<p>
    We will be very grateful for whatever accommodation you may extend to our student. We hope he/she keeps up
    to your work standards and requirements.
</p>

<p>Thank you.</p>

<div class="closing">Very truly yours,</div>

<div class="signature">
    <div class="signature-line">{{ $cedName }}</div>
    <div class="sig-title">Campus Executive Director</div>
</div>

</body>
</html>
