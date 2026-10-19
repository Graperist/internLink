<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Consent Form — {{ $document->internship?->student?->user?->name ?? 'Student' }}</title>
    <style>
        /* Sized to the official FM-AA-INT-03 (Rev. 02, 14-Oct-2024): A4, Arial 11pt. */
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 14mm 20mm 14mm; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #000;
            margin: 0 auto;
            padding: 14mm 20mm;
            max-width: 210mm;
            line-height: 1.2;
        }
        .no-print { margin-bottom: 10px; }
        .top-meta { text-align: right; font-size: 7.5pt; font-style: italic; line-height: 1.2; }
        .header { position: relative; text-align: center; margin: 6pt 0 16pt; min-height: 62pt; }
        .header h1 { margin: 10pt 0 3pt; font-size: 17pt; font-weight: 700; }
        .header .school, .header .campus { font-size: 7.5pt; }
        .header img { position: absolute; right: 9%; top: 0; width: 62pt; height: 62pt; object-fit: contain; }
        p { margin: 0 0 9pt; text-align: justify; }
        .line { display: inline-block; border-bottom: 1px solid #000; min-width: 90pt; padding: 0 3pt; line-height: 1.05; text-align: center; }
        .line.short { min-width: 34pt; }
        .line.medium { min-width: 62pt; }
        .line.long { min-width: 125pt; }
        ol { margin: 0 0 12pt; padding-left: 15pt; }
        ol li { padding-left: 4pt; margin-bottom: 12pt; text-align: justify; }
        .signed { margin-top: 4pt; text-align: left; }

        /* Signatures: names are printed on top of the lines automatically. */
        .signature-area { display: grid; grid-template-columns: 1fr 1fr; column-gap: 30pt; margin-top: 70pt; font-size: 9pt; }
        .label { font-weight: 700; font-size: 9pt; }
        .sign { width: 180pt; text-align: center; }
        .sign .name { font-weight: 700; font-size: 9pt; min-height: 11pt; padding: 0 2pt 1pt; text-transform: uppercase; }
        .sign .rule { border-top: 1px solid #000; }
        .sign .caption { font-size: 7.5pt; font-style: italic; }
        .left .witness { margin: 20pt 0 0 6pt; }
        .left .noted-label { margin-top: 20pt; }
        .left .ced { margin: 20pt 0 0 12pt; }
        .right .student { margin-top: 18pt; }
        .cert { margin-top: 14pt; font-size: 9pt; line-height: 1.5; }
        .cert .blank { display: inline-block; border-bottom: 1px solid #000; min-width: 85pt; height: 10pt; vertical-align: bottom; }
        .right .parent { margin-top: 12pt; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
    @include('partials.a4-page')
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
    $studentAddress = \App\Support\TextCase::fixCaps($v('address', $student?->current_address ?? $student?->home_address ?? ''));
    $course = $v('course', '') ?: ($student?->program?->courseTitle() ?? $student?->program?->name ?? '');
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
            $signedYear = $date->format('y'); // printed after the form's "20" 
        } catch (\Throwable $e) {
            // Keep the official blanks if the stored value cannot be parsed.
        }
    }

    // "Signed at ____, Pangasinan." - the form already ends with
    // "Pangasinan", so only the city/town is printed in the blank.
    $signedAt = trim(preg_replace('/,?\s*Pangasinan\s*$/i', '', $signedAt)) ?: $signedAt;

    // Signatories, filled in automatically from the records.
    $sig = \App\Support\Signatories::for($document->internship);
    $coordinatorName = $sig['coordinator'];
    $executiveDirectorName = $sig['ced'];
    // Parent/Guardian: as the student entered it on their Form 1.
    $parentName = $document->internship?->documents()
        ->whereHas('requirement', fn ($q) => $q->where('code', 'APPLICATION_FOR_OJT'))
        ->first()?->fieldValue('parent_guardian_name') ?? '';
@endphp

<button class="no-print" onclick="window.print()">Print</button>

<div class="page">
    <div class="top-meta">
        FM-AA-INT-03<br>
        Rev. 02<br>
        14-Oct-2024
    </div>

    <div class="header">
        <h1>CONSENT FORM</h1>
        <div class="school">PANGASINAN STATE UNIVERSITY</div>
        <div class="campus">{{ $campusName }}</div>
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
20<span class="line short">{{ $signedYear }}</span>.
    </p>

    <div class="signature-area">
        <div class="left">
            <div class="label">WITNESSES:</div>
            <div class="sign witness">
                <div class="name">{!! $coordinatorName !== '' ? e($coordinatorName) : '&nbsp;' !!}</div>
                <div class="rule"></div>
                <div class="caption">Campus Internship Coordinator</div>
            </div>

            <div class="label noted-label">NOTED:</div>
            <div class="sign ced">
                <div class="name">{!! $executiveDirectorName !== '' ? e($executiveDirectorName) : '&nbsp;' !!}</div>
                <div class="rule"></div>
                <div class="caption">Campus Executive Director</div>
                <div class="caption">PSU {{ $campusName }}</div>
            </div>
        </div>

        <div class="right">
            <div class="label">CONFORME:</div>
            <div class="sign student">
                <div class="name">{!! $studentName !== '' ? e($studentName) : '&nbsp;' !!}</div>
                <div class="rule"></div>
                <div class="caption">Signature over Printed Name of Student</div>
            </div>

            <div class="cert">
                Residence Cert. No.: <span class="blank"></span><br>
                Issued at: <span class="blank" style="min-width: 115pt;"></span><br>
                Issued on: <span class="blank" style="min-width: 112pt;"></span>
            </div>

            <div class="sign parent">
                <div class="name">{!! $parentName !== '' ? e($parentName) : '&nbsp;' !!}</div>
                <div class="rule"></div>
                <div class="caption">Signature over Printed Name of Parent/Guardian</div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
