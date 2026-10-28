<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Internship Agreement — {{ $document->internship->student->user->name }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 14mm 25.4mm 14mm; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            margin: 0 auto;
            max-width: 6.27in;
            line-height: 1.3;
        }

        .no-print { margin-bottom: 10px; }

        /* Page wrapper: the <thead> repeats at the top of every printed page,
           which is how the form control number sits in the header like Word. */
        table.page { width: 100%; border-collapse: collapse; }
        table.page > thead > tr > td,
        table.page > tbody > tr > td { padding: 0; border: 0; }
        table.page > thead { display: table-header-group; }
        .form-control-no {
            text-align: right;
            font-size: 7pt;
            font-style: italic;
            line-height: 1.15;
            margin-bottom: 4px;
        }

        .header { position: relative; text-align: center; margin: 4px 0 14px; min-height: 66px; }
        .header h1 { font-size: 16pt; margin: 14px 0 3px; font-weight: 700; line-height: 1.1; }
        .header .sub { font-size: 7pt; }
        .header img { position: absolute; right: 0.15in; top: 0; width: 62px; height: 62px; object-fit: contain; }

        p { margin: 0 0 8px; text-align: justify; }
        .loose { line-height: 2.1; text-align: left; }

        /* Fill-in blanks */
        .red { color: #e00000; }
        .red .u { color: #000; border-bottom-color: #000; }
        .u { display: inline-block; border-bottom: 1px solid #000; padding: 0 4px; min-width: 1.1in; text-align: center; line-height: 1.2; }
        .fld { display: inline-block; vertical-align: bottom; text-align: center; margin: 0 2px; line-height: 1.1; }
        .fld .v { display: block; border-bottom: 1px solid #000; min-height: 14px; padding: 0 4px; }
        .fld .c { display: block; font-size: 6pt; line-height: 1.2; }

        h3.witnesseth { text-align: center; font-size: 10pt; margin: 14px 0 8px; }

        ol { margin: 4px 0 0; padding-left: 0.42in; }
        ol > li { margin-bottom: 10px; text-align: justify; padding-left: 0.1in; }
        ul.grounds { margin: 3px 0 0; padding-left: 0.3in; list-style: disc; }
        ul.grounds li { margin: 0; text-align: justify; }

        .closing-block { break-inside: avoid; page-break-inside: avoid; }

        .sigs { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 14px; }
        .sigs td { vertical-align: top; padding: 0 0.12in; text-align: center; }
        .sig { padding-top: 14px; }
        .sig .name { border-bottom: 1px solid #000; height: 22px; padding: 6px 4px 1px; font-size: 9pt; font-weight: 700; line-height: 1.15; }
        .sig .cap { font-size: 7pt; font-style: italic; margin-top: 2px; line-height: 1.2; }
        .org { font-weight: 700; font-size: 9pt; text-align: left; line-height: 1.5; }
        .org .u { min-width: 0.9in; font-weight: 400; }

        .presence { font-weight: 700; margin: 26px 0 0; }

        .cut { border: 0; border-top: 1.5px dashed #000; margin: 14px 0 10px; }

        .ack-title { text-align: center; font-weight: 700; margin: 8px 0 10px; }
        .ack-head { margin: 0 0 8px; line-height: 1.3; }
        .indent { text-indent: 0.4in; text-align: left; }

        .ack-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 4px 0 8px; }
        .ack-table th { font-weight: 400; font-size: 10pt; text-align: center; padding: 2px 6px 6px; }
        .ack-table td { vertical-align: top; padding: 0 6px; }
        .ack-table .line { border-bottom: 1px solid #000; height: 26px; padding: 9px 2px 1px; font-size: 8.5pt; font-weight: 700; text-align: center; }
        .ack-table .cap { font-size: 6.5pt; font-style: italic; text-align: center; line-height: 1.2; min-height: 22px; padding-bottom: 4px; }

        .docbox { margin-top: 8px; font-size: 9.5pt; }
        .docbox div { margin-bottom: 1px; }
        .docbox .lbl { display: inline-block; width: 0.9in; }
        .docbox .u { min-width: 0.6in; }

        @media print {
            .no-print { display: none; }
            body { max-width: none; }
        }
            /* Signatory names are printed in capital letters on every form. */
        .sig .name, .ack-table .line { text-transform: uppercase; }
    </style>
    @include('partials.a4-page')
</head>
<body onload="window.print()">
@php
        $v = fn (string $key) => (string) ($document->fieldValue($key) ?: '');
        $student = $document->internship->student;
        $campus = $student?->campus ?? $document->internship->campus;
        $campusName = $campus?->name ?: 'Pangasinan State University';

        // "Alaminos City Campus" - the campus label without the university prefix.
        $campusLabel = preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', trim((string) $campusName)) ?: 'Alaminos City Campus';
        $campusShort = trim(preg_replace('/\s*Campus\s*$/i', '', $campusLabel));
        $campusAddress = trim((string) ($campus?->address ?: ''));
        $cedName = trim((string) ($campus?->ced_name ?: ''));
        $deanName = trim((string) ($campus?->college_dean_name ?: ''));
        // City/municipality only - the form already prints ", Pangasinan" after it.
        $campusCity = trim(preg_replace('/\s*,?\s*Pangasinan\s*$/i', '', $campusAddress));
        $requiredHours = $document->fieldValue('required_hours') ?: ($document->internship->required_hours ?? '');

        // Resolve the Subject Instructor from the student's actual program
        // and campus. The Instructor Portal uses the same campus + program
        // scoping, so the name shown here stays aligned with the student.
        $programId = $document->internship->program_id ?: $student?->program_id;
        $subjectInstructor = null;

        if ($campus?->id && $programId) {
            $subjectInstructor = \App\Models\Instructor::with('user')
                ->where('campus_id', $campus->id)
                ->where('program_id', $programId)
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->first();
        }

        $subjectInstructorName = $subjectInstructor?->user?->name ?: '';

        // The parent/guardian name is entered on the student's
        // APPLICATION_FOR_OJT form. The Internship Agreement itself does
        // not store that field, so fall back to that submitted application.
        $parentGuardianName = $document->fieldValue('parent_guardian_name');
        if (blank($parentGuardianName)) {
            $applicationDocument = $document->internship->documents()
                ->whereHas('requirement', fn ($query) => $query->where('code', 'APPLICATION_FOR_OJT'))
                ->with('fieldValues')
                ->first();

            $parentGuardianName = $applicationDocument?->fieldValue('parent_guardian_name');
        }
        $parentGuardianName = $parentGuardianName ?: '';

        // Resolve only the Campus Internship and Placement Coordinator from
        // the student's enrolled campus.
        $campusCoordinator = null;
        if ($campus?->id) {
            $campusCoordinator = \App\Models\User::with('role')
                ->where('campus_id', $campus->id)
                ->whereHas('role', fn ($query) => $query->where('code', 'coordinator'))
                ->where('status', 'active')
                ->orderBy('id')
                ->first();
        }

        // Derive the municipality/city used in the acknowledgment from the
        // campus address, instead of hard-coding "CITY OF ALAMINOS".
        $addressParts = array_values(array_filter(
            preg_split('/\s*,\s*/', $campusAddress),
            fn ($part) => trim((string) $part) !== ''
        ));
        $locality = trim((string) ($addressParts[0] ?? ''));

        if ($locality !== '') {
            $isCity = preg_match('/\bcity\b/i', $locality) === 1;
            $localityName = trim(preg_replace('/\s+city\s*$/i', '', $locality));
            $ackLocality = ($isCity ? 'CITY OF ' : 'MUNICIPALITY OF ') . strtoupper($localityName);
        } else {
            $ackLocality = 'MUNICIPALITY OF ____________';
        }

        // "this __ day of ____" - use the form's agreement_date (or signed_date) if entered,
        // otherwise the date the document was printed (today on first print).
        try {
            $enteredDate = $document->fieldValue('agreement_date') ?: $document->fieldValue('signed_date');
            $signedAt = filled($enteredDate)
                ? \Carbon\Carbon::parse($enteredDate)
                : ($document->printed_at ?: now());
        } catch (\Throwable $e) {
            $signedAt = now();
        }
        $signDay = $signedAt->format('jS');
        $signMonth = $signedAt->format('F');
        $signYear = $signedAt->format('Y');

        // Company/Agency Internship In-Charge: the on-site HTE Supervisor
        // account. Use the student's deployed HTE; before deployment (the
        // student only typed/picked the company on this form) match the
        // campus HTE by the company name instead.
        $hte = $document->internship->deployment?->hte;
        if (! $hte && filled($v('company_name'))) {
            $hte = \App\Models\Hte::where('campus_id', $campus?->id)
                ->where('name', $v('company_name'))
                ->first();
        }
        $internshipInCharge = $hte
            ? ($hte->representatives()->with('user')->orderBy('id')->get()
                ->map(fn ($rep) => $rep->user?->name)->filter()->first() ?: '')
            : '';

        $studentName = $student?->user?->name ?? '';
        $major = $v('major');
        $courseLine = $v('course').(($major !== '' && strtoupper($major) !== 'N/A') ? ', '.$major : '');
    @endphp

<button class="no-print" onclick="window.print()">Print</button>

<table class="page">
<thead><tr><td><div class="form-control-no">FM-AA-INT-10<br>Rev. 03<br>14-Oct-2024</div></td></tr></thead>
<tbody><tr><td>

<div class="header">
    <h1>INTERNSHIP AGREEMENT</h1>
    <div class="sub">PANGASINAN STATE UNIVERSITY</div>
    <img src="{{ asset('images/psu-logo.png') }}" alt="PSU">
</div>

<p><strong>KNOW ALL MEN THIS PRESENTS:</strong></p>

<p class="loose">
    This <strong>Internship Agreement</strong> is entered between <strong>Pangasinan State University,</strong>
    <span class="u">{{ $campusLabel }}</span>, <span class="u">{{ $campusCity }}</span>, Pangasinan
    represented by <span class="u">{{ $cedName }}</span> in his capacity as Campus Executive Director,
    herein referred to as <strong>Campus</strong>;
</p>

<p class="loose">
    <span class="fld" style="min-width:2.6in;"><span class="v">{{ $studentName }}</span><span class="c">Name of student-intern</span></span>,
    a 4<sup>th</sup> year student major in
    <span class="fld" style="min-width:2.4in;"><span class="v">{{ $courseLine }}</span><span class="c">Course</span></span>
    of the <strong>Pangasinan State University,</strong> <span class="u" style="min-width:2.2in;">{{ $campusLabel }}</span>
    and a resident of <span class="u" style="min-width:2.4in;">{{ $v('address') }}</span>
    herein referred to as the <strong>Student-Intern</strong>; and a duly recognized existing business institution with
    <span class="fld" style="min-width:3in;"><span class="v">{{ $v('company_name') }}</span><span class="c">Name of agency/ company</span></span>,
    <span class="fld" style="min-width:2.4in;"><span class="v">{{ $v('company_address') }}</span><span class="c">Office Address</span></span>
    represented herein by <span class="u" style="min-width:2in;">{{ $v('company_representative') }}</span>
    in his/her capacity as <span class="u" style="min-width:2in;">{{ $v('representative_position') }}</span>
    herein referred to as the Host Company.
</p>

<h3 class="witnesseth">WITNESSETH</h3>

<p style="margin-bottom:2px;">That the <strong>PARTIES</strong> concerned hereby entered this Internship Agreement under the following conditions:</p>

<ol>
    <li>That the <strong>Campus</strong> hereby certifies that the <strong>Student-Intern</strong> is a bonafide student and at the same time has completed all the requirements/prerequisites to his/her Internship /Practicum activity;</li>
    <li>That the <strong>Student-Intern</strong> will undergo Internship/Practicum in any company/corporation for not less than <span class="u" style="min-width:0.55in;"><strong>{{ $requiredHours }}</strong></span> <strong>hours</strong> in line with his/her major field of specialization;</li>
    <li>That the student intern is not required to report to his/her internship site during calamities, health problems or in case of emergencies.</li>
    <li>That the <strong>Student-Intern</strong> understands and must strictly follow the <strong>Campus / College</strong> Internship program and <strong>Host Training Establishment (HTE)</strong> policies, rules and regulations during the specified period of training;</li>
    <li>That the <strong>Student-Intern</strong> understands that he/she will not be entitled to any compensation from the <strong>Host Training Establishment (HTE)</strong> unless the Company will voluntarily give the Student-Internship travelling allowances and other expenses;</li>
    <li>That the campus shall look for a <strong>Host Training Establishment (HTE)</strong> that provides relevant industry-based exposure, supervise the conduct of internship training accordingly with the Student-Intern major field of specialization and shall give necessary performance assessment/evaluation upon completion of his/her training</li>
    <li>
        That the Internship /Practicum of the Student-Intern shall be discontinued on reasonable grounds upon written notice to the Internship Coordinator concerned. The reasonable grounds that may terminate the Student-Trainee’s training shall include not limited to the following:
        <ul class="grounds">
            <li>Habitual absenteeism from the training/practicum and related theoretical instruction;</li>
            <li>Willful disobedience of the company rules or insubordination to lawful order of the superior;</li>
            <li>Poor physical condition, permanent disability or prolonged illness which incapacitates the trainee from working</li>
            <li>Theft or malicious destruction of company property and/or equipment.</li>
            <li>Poor efficiency of performance on Internship or in the center training for a prolonged period despite warnings duly given to the trainee; and</li>
            <li>Engaging in violence or other forms of gross misconduct inside the company premise.</li>
            <li>Other analogous cases.</li>
        </ul>
    </li>
</ol>

<div class="closing-block">

<p style="margin-top:12px; text-align:left;"><strong>IN WITNESS, WHEREOF</strong>, the parties hereunto affixed their signatures this <span class="u" style="min-width:0.4in;">{{ $signDay }}</span> day of <span class="u" style="min-width:0.9in;">{{ $signMonth }}</span>, <span class="u" style="min-width:0.5in;">{{ $signYear }}</span>.</p>

<table class="sigs">
    <tr>
        <td>
            <div class="org">PANGASINAN STATE UNIVERSITY-<br><span class="u">{{ $campusShort }}</span> Campus</div>
        </td>
        <td>
            <div class="sig" style="padding-top:2px;"><div class="name">{{ $v('company_name') }}</div><div class="cap">Name of Company/Agency</div></div>
        </td>
    </tr>
    <tr>
        <td><div class="sig"><div class="name">{{ $cedName }}</div><div class="cap">Campus Executive Director</div></div></td>
        <td><div class="sig"><div class="name">{{ $v('company_representative') }}</div><div class="cap">Host Training Establishment Head or Representative</div></div></td>
    </tr>
    <tr>
        <td><div class="sig"><div class="name">{{ $studentName }}</div><div class="cap">Student-Intern</div></div></td>
        <td><div class="sig"><div class="name">{{ $parentGuardianName }}</div><div class="cap">With Parent/Guardian/Spouse Consent, Endorsement and Approval</div></div></td>
    </tr>
</table>

<div class="presence">SIGNED IN THE PRESENCE OF:</div>

<table class="sigs" style="margin-top:0;">
    <tr>
        <td><div class="sig"><div class="name">{{ $subjectInstructorName }}</div><div class="cap">Internship /Practicum Subject Instructor</div></div></td>
        <td><div class="sig"><div class="name">{{ $internshipInCharge }}</div><div class="cap">Company/Agency Internship In-Charge</div></div></td>
    </tr>
    <tr>
        <td colspan="2" style="padding: 0 1.6in;"><div class="sig"><div class="name">{{ $campusCoordinator?->name ?: '' }}</div><div class="cap">Campus Internship and Placement Coordinator</div></div></td>
    </tr>
</table>

<hr class="cut">

<div class="ack-title">ACKNOWLEDGMENT</div>

<div class="ack-head">
    REPUBLIC OF THE PHILIPPINES<br>
    PROVINCE OF PANGASINAN<br>
    {{ $ackLocality }}
</div>

<p class="indent">SUBSCRIBED AND SWORN to before me this <span class="u" style="min-width:0.45in;">&nbsp;</span> day of <span class="u" style="min-width:0.9in;">&nbsp;</span>, <span class="u" style="min-width:0.5in;">&nbsp;</span>, exhibiting to me their Residence Certificate Number</p>

<table class="ack-table">
    <colgroup><col style="width:38%"><col style="width:24%"><col style="width:17%"><col style="width:21%"></colgroup>
    <tr>
        <th>Name of Issue</th>
        <th>Residence Certificate No.</th>
        <th>Date Issued</th>
        <th>Place</th>
    </tr>
    <tr>
        <td><div class="line">{{ $cedName }}</div><div class="cap">Campus Executive Director</div></td>
        <td><div class="line">&nbsp;</div><div class="cap">&nbsp;</div></td>
        <td><div class="line">&nbsp;</div><div class="cap">&nbsp;</div></td>
        <td><div class="line">&nbsp;</div><div class="cap">&nbsp;</div></td>
    </tr>
    <tr>
        <td><div class="line">{{ $v('company_representative') }}</div><div class="cap">Head or Representative of the Host Training Establishment</div></td>
        <td><div class="line">&nbsp;</div><div class="cap">&nbsp;</div></td>
        <td><div class="line">&nbsp;</div><div class="cap">&nbsp;</div></td>
        <td><div class="line">&nbsp;</div><div class="cap">&nbsp;</div></td>
    </tr>
    <tr>
        <td><div class="line">{{ $studentName }}</div><div class="cap">Student-Intern</div></td>
        <td><div class="line">&nbsp;</div><div class="cap">&nbsp;</div></td>
        <td><div class="line">&nbsp;</div><div class="cap">&nbsp;</div></td>
        <td><div class="line">&nbsp;</div><div class="cap">&nbsp;</div></td>
    </tr>
</table>

<p>Known to me and to be the same person who executed the foregoing instrument and acknowledge the same to be their free and voluntary act and that of the institution they represent.</p>

<p>WITNESS MY HAND SEAL ON THE DATE AT THE PLACE FIRST ABOVE WRITTEN</p>

<div class="docbox">
    <div><span class="lbl">Doc</span><span class="u">&nbsp;</span></div>
    <div><span class="lbl">Page No.</span><span class="u">&nbsp;</span></div>
    <div><span class="lbl">Book No.</span><span class="u">&nbsp;</span></div>
    <div><span class="lbl">Series of</span><span class="u">&nbsp;</span></div>
</div>

</div>

</td></tr></tbody></table>

</body>
</html>
