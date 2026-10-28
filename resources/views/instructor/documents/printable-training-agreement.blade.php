<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Training Agreement and Liability Waiver — {{ $document->internship->student->user->name }}</title>
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

        .header { position: relative; text-align: center; margin: 4px 0 34px; min-height: 100px; }
        .header .sub { font-size: 7.5pt; margin-top: 3px; }
        .header h1 { font-size: 16pt; margin: 12px 0 0; font-weight: 700; line-height: 1.25; padding: 0 0.9in; }
        .header img { position: absolute; right: 0.05in; top: 0; width: 70px; height: 70px; object-fit: contain; }

        .u { display: inline-block; border-bottom: 1px solid #000; padding: 0 4px; text-align: center; line-height: 1.2; }

        p { margin: 0 0 10px; text-align: justify; line-height: 1.6; }

        ol.terms { list-style-type: lower-alpha; margin: 4px 0 0; padding-left: 0.5in; }
        ol.terms li { margin-bottom: 2px; text-align: left; line-height: 1.4; padding-left: 0.05in; }

        .sig { width: 2.7in; }
        .sig .name { border-bottom: 1px solid #000; min-height: 20px; padding: 3px 4px 1px; font-size: 10pt; font-weight: 700; text-align: center; }
        .sig .cap { font-size: 7.5pt; font-style: italic; text-align: center; margin-top: 2px; }

        .rc { margin-top: 14px; line-height: 1.5; }
        .rc div { white-space: nowrap; }
        .rc .u { min-width: 1.35in; text-align: left; }

        .two { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .two td { vertical-align: top; padding: 0; }

        .sworn { margin-top: 56px; text-align: left; line-height: 2.4; font-size: 10pt; }
        .sworn .nw { white-space: nowrap; }

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
    $v = fn (string $key) => (string) ($document->fieldValue($key) ?: '');
    $student = $document->internship->student;
    $campus = $student?->campus ?? $document->internship->campus;

    $campusShort = trim(preg_replace('/\s*Campus\s*$/i', '', preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', trim((string) ($campus?->name ?? '')))));
    $campusShort = $campusShort !== '' ? $campusShort : 'Alaminos City';

    $studentName = $v('student_name') ?: ($student?->user?->name ?? '');
    $companyName = $v('company_name');

    // Parent/guardian: entered on this form; otherwise from the student's application.
    $parentGuardianName = $v('parent_guardian_name');
    if (blank($parentGuardianName)) {
        $applicationDocument = $document->internship->documents()
            ->whereHas('requirement', fn ($q) => $q->where('code', 'APPLICATION_FOR_OJT'))
            ->with('fieldValues')
            ->first();
        $parentGuardianName = (string) ($applicationDocument?->fieldValue('parent_guardian_name') ?: '');
    }

    // Company Representative: the contact person of the student's HTE.
    $hte = $document->internship->deployment?->hte;
    if (! $hte && filled($companyName)) {
        $hte = \App\Models\Hte::where('campus_id', $campus?->id)->where('name', $companyName)->first();
    }
    $companyRepresentative = trim((string) ($hte?->contact_person ?? ''));

    // "SUBSCRIBED AND SWORN BEFORE ME this __ day of ____, ____ at ____"
    $signedDay = $signedMonth = $signedYear = '';
    if (filled($v('signed_date'))) {
        try {
            $d = \Carbon\Carbon::parse($v('signed_date'));
            $signedDay = $d->format('jS');
            $signedMonth = $d->format('F');
            $signedYear = $d->format('Y');
        } catch (\Throwable $e) {}
    }
    $signedCity = $v('signed_city');
@endphp

<button class="no-print" onclick="window.print()">Print</button>

<table class="page">
<thead><tr><td><div class="form-control-no">FM-AA-INT-18<br>Rev. 1<br>14-Oct-2024</div></td></tr></thead>
<tbody><tr><td>

<div class="header">
    <h1>TRAINING AGREEMENT AND<br>LIABILITY WAIVER FORM<br>OVERTIME FORM</h1>
    <div class="sub">PANGASINAN STATE UNIVERSITY</div>
    <div class="sub"><span class="u" style="min-width:1.1in;">{{ $campusShort }}</span> Campus</div>
    <img src="{{ asset('images/psu-logo.png') }}" alt="PSU">
</div>

<p>
    I, <span class="u" style="min-width:2.4in;">{{ $studentName }}</span>, a student of Pangasinan State University,
    hereby voluntarily agree to undergo overtime training in my Internship Training at
    <span class="u" style="min-width:2.3in;">{{ $companyName }}</span> under the following terms and conditions:
</p>

<ol class="terms">
    <li>That I have consent from my parents/guardians for the said overtime.</li>
    <li>That the company/establishment where I am undergoing my training has knowledge of the overtime.</li>
    <li>That I fully understand that the Pangasinan State University and
        <span class="u" style="min-width:2.1in;">{{ $companyName }}</span>
        is no longer responsible/liable for any injury or incident that may occur during the said overtime.</li>
</ol>

<div style="margin-top:50px;">
    <div class="sig"><div class="name">{{ $studentName }}</div><div class="cap">Student-Intern</div></div>
    <div class="rc">
        <div>Res. Cert No.: <span class="u">&nbsp;</span></div>
        <div>Issued on: <span class="u">&nbsp;</span></div>
        <div>Issued at: <span class="u">&nbsp;</span></div>
    </div>
</div>

<p style="margin-top:46px; text-align:left;">With our consent and approval:</p>

<table class="two" style="margin-top:38px;">
    <tr>
        <td>
            <div class="sig"><div class="name">{{ $parentGuardianName }}</div><div class="cap">Parent/Guardian</div></div>
            <div class="rc">
                <div>Res. Cert No.: <span class="u">&nbsp;</span></div>
                <div>Issued on: <span class="u">&nbsp;</span></div>
                <div>Issued at: <span class="u">&nbsp;</span></div>
            </div>
        </td>
        <td>
            <div class="sig" style="margin-left:auto;"><div class="name">{{ $companyRepresentative }}</div><div class="cap">Company Representative</div></div>
            <div class="rc" style="margin-left:0.35in;">
                <div>Res. Cert No.: <span class="u">&nbsp;</span></div>
                <div>Issued on: <span class="u">&nbsp;</span></div>
                <div>Issued at: <span class="u">&nbsp;</span></div>
            </div>
        </td>
    </tr>
</table>

<div class="sworn">
    <span class="nw">SUBSCRIBED AND SWORN BEFORE ME this <span class="u" style="min-width:0.5in;">{{ $signedDay }}</span> day of
    <span class="u" style="min-width:1in;">{{ $signedMonth }}</span>,
    <span class="u" style="min-width:0.5in;">{{ $signedYear }}</span> at</span><br>
    <span class="u" style="min-width:2.7in;">{{ $signedCity }}</span>, Philippines.
</div>

</td></tr></tbody></table>

</body>
</html>
