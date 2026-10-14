<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Consent Form — {{ $document->internship?->student?->user?->name ?? 'Student' }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0 auto;
            padding: 18mm 17mm 12mm;
            max-width: 210mm;
            line-height: 1.28;
        }
        .no-print { margin-bottom: 10px; }
        .page { width: 100%; }
        .top-meta {
            text-align: right;
            font-size: 8px;
            line-height: 1.2;
            margin-bottom: 3px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 13px;
        }
        .header .title { text-align: center; }
        .header h1 {
            margin: 0 0 4px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: .2px;
        }
        .header .school { font-size: 10px; font-weight: 700; }
        .header .campus { font-size: 10px; }
        .header img { width: 58px; height: 58px; object-fit: contain; }
        p { margin: 0 0 10px; text-align: justify; }
        .intro { margin-top: 2px; }
        .line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 105px;
            padding: 0 2px;
            line-height: 1.05;
            vertical-align: baseline;
        }
        .line.short { min-width: 58px; }
        .line.medium { min-width: 95px; }
        .line.long { min-width: 155px; }
        ol {
            margin: 0 0 10px;
            padding-left: 21px;
        }
        ol li {
            padding-left: 3px;
            margin-bottom: 8px;
            text-align: justify;
        }
        .signed {
            margin-top: 8px;
            margin-bottom: 0;
            text-align: left;
        }
        .signature-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 45px;
            margin-top: 57px;
        }
        .sig-col { min-width: 0; }
        .section-label { font-weight: 700; margin-bottom: 28px; }
        .sig-line {
            border-top: 1px solid #000;
            width: 88%;
            margin-bottom: 3px;
        }
        .sig-caption {
            font-size: 9px;
            font-style: italic;
            text-align: center;
            width: 88%;
        }
        .noted { margin-top: 37px; font-weight: 700; }
        .noted-line { margin-top: 27px; }
        .noted-caption { font-size: 9px; font-style: italic; width: 88%; text-align: center; }
        .psu-campus { font-size: 9px; font-style: italic; width: 88%; text-align: center; }
        .conforme { margin-bottom: 28px; }
        .cert-row { margin-top: 8px; font-size: 10px; }
        .cert-line { display: inline-block; border-bottom: 1px solid #000; min-width: 112px; height: 12px; vertical-align: bottom; }
        .parent-line { margin-top: 29px; }
        .parent-caption { font-size: 9px; font-style: italic; width: 88%; text-align: center; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 15mm 16mm 10mm; }
        }
    </style>
</head>
<body onload="window.print()">
@php
    $v = fn (string $key, $default = '') => $document->fieldValue($key) ?: $default;

    $student = $document->internship?->student;
    $campus = $document->internship?->campus;

    $campusName = trim($campus?->name ?? '');
    $campusName = preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', $campusName);
    $campusName = trim($campusName) ?: 'Alaminos City Campus';
    $campusShortName = preg_replace('/\s+Campus$/i', '', $campusName);
    $campusShortName = trim($campusShortName) ?: 'Alaminos City';

    $studentName = $student?->user?->name ?: $v('full_name', '');
    $studentAddress = $v('address', $student?->current_address ?? $student?->home_address ?? '');
    $course = $student?->program?->name ?: $v('course', '');
    $major = $student?->major?->name ?: $v('major', '');

    $requiredHours = $student?->program?->required_hours
        ?? $document->internship?->required_hours
        ?? $v('required_hours', '');

    $companyName = $v('company_name', '');

    $campusAddress = trim($campus?->address ?? '');
    if ($campusAddress === '') {
        $campusAddress = 'Alaminos City, Pangasinan';
    }

    // The official 2024 form says: "Signed at _____________, Pangasinan."
    // Use the campus city/location while preserving the official wording.
    $signedAt = $campusAddress;

    $signedDate = $v('signed_date', '');
    $signedDay = '';
    $signedMonth = '';
    $signedYear = '';
    if ($signedDate) {
        try {
            $date = \Carbon\Carbon::parse($signedDate);
            $signedDay = $date->format('j');
            $signedMonth = $date->format('F');
            $signedYear = $date->format('Y');
        } catch (\Throwable $e) {
            // Keep the official blanks if the stored value cannot be parsed.
        }
    }

    $coordinatorName = $v('coordinator_name', '');
    $executiveDirectorName = $v('executive_director_name', '');
@endphp

<button class="no-print" onclick="window.print()">Print</button>

<div class="page">
    <div class="top-meta">
        FM-AA-INT-03<br>
        Rev. 02<br>
        14-Oct-2024
    </div>

    <div class="header">
        <div class="title">
            <h1>CONSENT FORM</h1>
            <div class="school">PANGASINAN STATE UNIVERSITY</div>
            <div class="campus">{{ $campusName }}</div>
        </div>
        <img src="{{ asset('images/psu-logo.png') }}" alt="PSU Logo">
    </div>

    <p class="intro">
        I, <span class="line long">{{ $studentName }}</span>, Filipino, of legal age, and residing at
        <span class="line long">{{ $studentAddress }}</span> after being duly sworn by law, do hereby depose and say that:
    </p>

    <ol>
        <li>
            I am a student of Pangasinan State University, <span class="line medium">{{ $campusShortName }}</span> Campus and that as a course requirement
            for <span class="line long">{{ $course }}</span>, I am required to undergo Internship / Practicum in any company in line
            with my major field of specialization for <span class="line short">{{ $requiredHours }}</span> hours;
        </li>

        <li>
            That the Pangasinan State University, <span class="line medium">{{ $campusShortName }}</span> Campus, thru the Campus Internship
            Coordinator and the Internship/Practicum Subject Instructor has requested
            <span class="line long">{{ $companyName }}</span> to allow me to undergo thereat and said company had accepted said
            request;
        </li>

        <li>
            I promise to render the necessary work to the best of my ability in order that my practicum shall be
            brought to a successful conclusion and that <span class="line long">{{ $companyName }}</span> and the Pangasinan State
            University, <span class="line medium">{{ $campusShortName }}</span> Campus shall not adversely affect;
        </li>

        <li>
            That while performing the practicum and the duration thereof, I shall abide by the rules and policies
            of said Company and Pangasinan State University, <span class="line medium">{{ $campusShortName }}</span> Campus relative to the work
            covered by said practicum and such order and instruction issued or to be issued thereby;
        </li>

        <li>
            I shall be punctual in my attendance and shall abide by the evaluation given me by said company
            based on such internship;
        </li>

        <li>
            I declare unconditional that there exists no employer-employee relationship between
            <span class="line long">{{ $companyName }}</span> and me, and hereby hold its officers, directors and personnel free and harmless
            from any case or causes of action, claims or otherwise arising from or about my internship which I
            have, including money claims, it understood that the internship is just a part of the academic
            preparation and thus non-compensable;
        </li>
    </ol>

    <p class="signed">
        Signed at <span class="line medium">{{ $signedAt }}</span>, Pangasinan.
        This <span class="line short">{{ $signedDay }}</span> day of
        <span class="line medium">{{ $signedMonth }}</span>,
        <span class="line short">{{ $signedYear }}</span>.
    </p>

    <div class="signature-area">
        <div class="sig-col">
            <div class="section-label">WITNESSES:</div>
            <div class="sig-line"></div>
            <div class="sig-caption">Campus Internship Coordinator</div>

            <div class="noted">NOTED:</div>
            <div class="sig-line noted-line"></div>
            <div class="sig-caption">Campus Executive Director</div>
            <div class="psu-campus">PSU {{ $campusName }}</div>
        </div>

        <div class="sig-col">
            <div class="section-label">CONFORME:</div>
            <div class="sig-line"></div>
            <div class="sig-caption">Signature over Printed Name of Student</div>

            <div class="cert-row">Residence Cert. No.: <span class="cert-line"></span></div>
            <div class="cert-row">Issued at: <span class="cert-line"></span></div>
            <div class="cert-row">Issued on: <span class="cert-line"></span></div>

            <div class="sig-line parent-line"></div>
            <div class="parent-caption">Signature over Printed Name of Parent/Guardian</div>
        </div>
    </div>
</div>
</body>
</html>
