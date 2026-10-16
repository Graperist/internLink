<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application for Internship — {{ $document->internship->student->user->name }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 9mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; max-width: 800px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        .outer { border: 1.4px solid #000; }

        .header-row { display: flex; align-items: center; border-bottom: 1.4px solid #000; padding: 7px 9px; position: relative; }
        .header-row img { width: 55px; height: 55px; object-fit: contain; margin-right: 12px; }
        .header-title { flex: 1; text-align: center; padding-right: 50px; }
        .header-title h1 { font-size: 16px; margin: 0 0 1px; letter-spacing: .4px; }
        .header-title div { font-size: 9.5px; font-weight: 700; line-height: 1.3; }
        .form-control-no { position: absolute; top: 7px; right: 8px; text-align: right; font-size: 7.5px; line-height: 1.25; }

        .section-header { border-top: 1.2px solid #000; border-bottom: 1px solid #000; padding: 3px 7px; font-weight: 700; font-size: 10.5px; }
        .section-header .sec-num { display: inline-block; width: 22px; }

        .profile-table td { border-bottom: 1px solid #555; border-right: 1px solid #555; padding: 3px 6px; vertical-align: middle; }
        .profile-table tr:last-child td { border-bottom: 0; }
        .profile-table td:last-child { border-right: 0; }
        .profile-table td.label { width: 16%; font-weight: 700; background: #fafafa; }
        .profile-table td.sublabel { font-size: 7.5px; color: #444; text-align: center; padding-top: 0; }
        .profile-table td.value { font-weight: 600; white-space: pre-line; }

        .credentials-note { padding: 6px 7px; font-size: 9px; border-bottom: 1px solid #555; }
        .credentials-table td, .credentials-table th { border: 1px solid #555; padding: 3px; text-align: center; font-size: 8.5px; height: 19px; }
        .credentials-table th { font-weight: 700; background: #fafafa; }
        .credentials-table td.value { font-weight: 600; }

        .registrar-block { padding: 8px 9px 7px; border-bottom: 1.3px solid #000; }
        .registrar-block .cert-line { text-align: right; font-size: 9px; margin-bottom: 7px; }
        .registrar-block .fit-line { font-size: 9px; margin-bottom: 8px; }
        .registrar-sig { text-align: center; font-size: 8.8px; }
        .registrar-sig .name { display: inline-block; min-width: 180px; border-bottom: 1px solid #000; padding: 0 4px 2px; font-weight: 700; }

        .company-table td { border-bottom: 1px solid #555; border-right: 1px solid #555; padding: 3px 6px; vertical-align: top; }
        .company-table tr:last-child td { border-bottom: 0; }
        .company-table td:last-child { border-right: 0; }
        .company-table td.label { width: 25%; font-weight: 700; background: #fafafa; }
        .company-table td.value { font-weight: 600; white-space: pre-line; }

        .note-block { padding: 6px 9px; font-size: 8px; font-style: italic; text-align: justify; border-bottom: 1.3px solid #000; }
        .approval-header { padding: 4px 8px; font-weight: 700; font-size: 10.5px; border-bottom: 1px solid #000; }
        .approval-grid { display: flex; gap: 25px; padding: 14px 9px 3px; }
        .approval-col { flex: 1; text-align: center; min-height: 33px; }
        .sig-line { border-top: 1px solid #000; width: 80%; margin: 0 auto 3px; padding-top: 2px; }
        .sig-name { font-weight: 700; font-size: 9px; }
        .sig-title { font-size: 8.5px; }
        .sig-name { font-weight: 700; font-size: 9px; min-height: 12px; margin-top: 8px; }
        .approved-header { padding: 4px 8px; font-weight: 700; font-size: 10.5px; border-top: 1px solid #000; border-bottom: 1px solid #000; }
        .approved-block { text-align: center; padding: 14px 9px 11px; }
        .approved-name { display: inline-block; border-bottom: 1px solid #000; min-width: 280px; padding: 14px 6px 1px; margin-bottom: 3px; font-weight: 700; font-size: 9px; }
        .no-print { margin-bottom: 8px; }
        @media print { .no-print { display: none; } body { max-width: none; } }
    </style>
</head>
<body onload="window.print()">
@php
    $v = fn (string $key, string $fallback = '____________________________') => $document->fieldValue($key) ?: $fallback;
    $internship = $document->internship;
    $student = $internship?->student;
    $campus = $internship?->campus;
    $campusName = preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', trim((string) ($campus?->name ?: ''))) ?: 'Alaminos City Campus';
    // Signatory names come from the records (Signatories helper).
    $sig = \App\Support\Signatories::for($internship);
    $cedName = $sig['ced'];
    $academicRows = [];
    for ($i = 1; $i <= 5; $i++) {
        $academicRows[] = [
            'sem' => $v("academic_{$i}_sem", ''),
            'sy' => $v("academic_{$i}_sy", ''),
            'subject' => $v("academic_{$i}_subject", ''),
            'passing' => $v("academic_{$i}_passing", ''),
            'failing' => $v("academic_{$i}_failing", ''),
            'grade' => $v("academic_{$i}_grade", ''),
            'signature' => $v("academic_{$i}_signature", ''),
            'date' => $v("academic_{$i}_date", ''),
        ];
    }
@endphp

<button class="no-print" onclick="window.print()">Print</button>

<div class="outer">
    <div class="header-row">
        <img src="{{ asset('images/psu-logo.png') }}" alt="PSU">
        <div class="header-title">
            <h1>APPLICATION FOR INTERNSHIP</h1>
            <div>PANGASINAN STATE UNIVERSITY</div>
            <div>{{ $campusName }}</div>
        </div>
        <div class="form-control-no">FM-AA-INT-01<br>Rev. 1<br>14-Oct-2024</div>
    </div>

    <div class="section-header"><span class="sec-num">A.</span>PERSONAL PROFILE</div>
    <table class="profile-table">
        <tr>
            <td class="label" rowspan="2">NAME</td>
            <td class="value" style="width:28%;">{{ $v('surname','') }}</td>
            <td class="value" style="width:28%;">{{ $v('given_name','') }}</td>
            <td class="value" style="width:28%;">{{ $v('middle_name','') }}</td>
        </tr>
        <tr>
            <td class="sublabel">Surname</td>
            <td class="sublabel">Given Name</td>
            <td class="sublabel">Middle Name</td>
        </tr>
        <tr>
            <td class="label">AGE</td>
            <td class="value">{{ $v('age','') }}</td>
            <td class="label">CIVIL STATUS</td>
            <td class="value">{{ $v('civil_status','') }}</td>
        </tr>
        <tr>
            <td class="label">SEX - GENDER</td>
            <td class="value">{{ $v('sex','') }}</td>
            <td class="label">CONTACT NUMBER</td>
            <td class="value">{{ $v('contact_number','') }}</td>
        </tr>
        <tr>
            <td class="label">COURSE</td>
            <td class="value">{{ $v('course','') }}</td>
            <td class="label">YEAR &amp; SECTION</td>
            <td class="value">{{ $v('year_section','') }}</td>
        </tr>
        <tr>
            <td class="label">MAJOR</td>
            <td class="value">{{ $v('major','N/A') }}</td>
            <td class="label">PARENT/GUARDIAN</td>
            <td class="value">{{ $v('parent_guardian_name','') }}</td>
        </tr>
        <tr>
            <td class="label">CURRENT ADDRESS</td>
            <td class="value" colspan="3">{{ $v('current_address','') }}</td>
        </tr>
        <tr>
            <td class="label">HOME ADDRESS</td>
            <td class="value" colspan="3">{{ $v('home_address','') }}</td>
        </tr>
    </table>

    <div class="section-header"><span class="sec-num">B.</span>ACADEMIC CREDENTIALS/QUALIFICATIONS</div>
    <div class="credentials-note">
        This is to certify that as per records kept in this office, the above name had already taken and pass
        <strong>{{ $v('passed_units','________') }}</strong> units except the following subject/s to wit:
    </div>
    <table class="credentials-table">
        <tr>
            <th>SEM</th><th>SY</th><th>SUBJECT</th><th>PASSING</th><th>FAILING</th><th>GRADE</th><th>SIGNATURE</th><th>DATE</th>
        </tr>
        @foreach ($academicRows as $row)
            <tr>
                <td class="value">{{ $row['sem'] }}</td>
                <td class="value">{{ $row['sy'] }}</td>
                <td class="value">{{ $row['subject'] }}</td>
                <td class="value">{{ $row['passing'] }}</td>
                <td class="value">{{ $row['failing'] }}</td>
                <td class="value">{{ $row['grade'] }}</td>
                <td class="value">{{ $row['signature'] }}</td>
                <td class="value">{{ $row['date'] }}</td>
            </tr>
        @endforeach
    </table>

    <div class="registrar-block">
        <div class="fit-line">This is to certify that he/she is <u style="color:#c00000;">qualified</u> to undergo internship.</div>
        <div class="cert-line">Certified and verified true and correct:</div>
        <div class="registrar-sig">
            <div class="name">{!! $sig['registrar'] !== '' ? e($sig['registrar']) : '&nbsp;' !!}</div>
            <div>Registrar</div>
        </div>
    </div>

    <div class="section-header"><span class="sec-num">C.</span>FIRM/AGENCY/COMPANY FOR INTERNSHIP</div>
    <table class="company-table">
        <tr><td class="label">NAME OF THE FIRM/AGENCY/COMPANY</td><td class="value">{{ $v('hte_name','') }}</td></tr>
        <tr><td class="label">ADDRESS</td><td class="value">{{ $v('hte_address','') }}</td></tr>
        <tr><td class="label">HEAD</td><td class="value">{{ $v('hte_head','') }}</td></tr>
        <tr><td class="label">CONTACT NUMBER</td><td class="value">{{ $v('hte_contact_number','') }}</td></tr>
        <tr><td class="label">NUMBER OF HOURS</td><td class="value">{{ $v('hte_number_of_hours','') }}</td></tr>
    </table>

    <div class="note-block">
        Note: Please attach Medical Certificate and Psychological Test from any government physician, certifying
        that the student applicant is physically, emotionally, and mentally fit for the internship training activity.
        <span style="color:#c00000;">Please attach curriculum checklist duly signed by the adviser and registrar.
        Also attach the updated grades from the portal.</span>
    </div>

    <div class="approval-header">RECOMMENDING APPROVAL</div>
    <div class="approval-grid">
        <div class="approval-col"><div class="sig-name">{{ $sig['instructor'] }}</div><div class="sig-line"></div><div class="sig-title">Internship/Practicum Subject Instructor</div></div>
        <div class="approval-col"><div class="sig-name">{{ $sig['coordinator'] }}</div><div class="sig-line"></div><div class="sig-title">Campus Internship Coordinator</div></div>
    </div>
    <div class="approval-grid">
        <div class="approval-col"><div class="sig-name">{{ $sig['department_chair'] }}</div><div class="sig-line"></div><div class="sig-title">Department Chair</div></div>
        <div class="approval-col"><div class="sig-name">{{ $sig['college_dean'] }}</div><div class="sig-line"></div><div class="sig-title">College Dean</div></div>
    </div>

    <div class="approved-header">APPROVED BY:</div>
    <div class="approved-block">
        @if ($cedName)
            <div class="approved-name">{{ $cedName }}</div>
        @else
            <div class="approved-name">&nbsp;</div>
        @endif
        <div class="sig-title">Campus Executive Director</div>
    </div>
</div>
</body>
</html>
