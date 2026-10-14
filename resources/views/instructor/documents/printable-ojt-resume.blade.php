<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Internship Resume — {{ $document->internship->student->user->name }}</title>
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
        }

        /* One outer box; every section is a table inside it so the
           grid lines match the official single-table layout. */
        .sheet { border: 1.5px solid #000; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: middle;
            height: 24px;
            overflow-wrap: anywhere;
            white-space: pre-line;
        }
        .sheet table:first-child tr:first-child > * { border-top: 0; }
        .sheet td:first-child, .sheet th:first-child { border-left: 0; }
        .sheet td:last-child, .sheet th:last-child { border-right: 0; }
        .sheet table + table { margin-top: -1px; }

        th { font-weight: 700; text-align: center; }
        .label { font-weight: 700; }
        .section-title { font-weight: 700; font-size: 9pt; height: 15px; padding: 1px 4px; text-align: left; }

        /* Header */
        .header-table td { height: 78px; }
        .logo-cell { width: 13%; text-align: center; padding: 2px; }
        .logo-cell img { width: 58px; height: 58px; object-fit: contain; display: block; margin: 0 auto; }
        .title-cell { text-align: center; }
        .title-cell h1 { font-size: 16pt; margin: 0 0 4px; font-weight: 700; line-height: 1.1; }
        .title-cell div { font-size: 7pt; line-height: 1.3; }

        /* Personal info + photo */
        .info-table .c-label { width: 17%; }
        .info-table .c-value { width: 58%; }
        .info-table .c-photo { width: 25%; text-align: center; font-size: 8pt; }
        .info-table td.career-label { vertical-align: middle; }
        .info-table td.career-value { height: 104px; vertical-align: top; padding: 4px 5px; }

        .w-17 { width: 17%; } .w-58 { width: 58%; } .w-25 { width: 25%; }
        .w-75 { width: 75%; }

        .extra-cell { height: 135px; vertical-align: top; padding: 5px 6px; text-align: left; }
        .extra-cell .label { display: block; margin-bottom: 3px; }

        .p-label { width: 17%; }
        .p-value { width: 36%; }
        .p-label2 { width: 17%; }
        .p-value2 { width: 30%; }

        .r-name { width: 35%; } .r-pos { width: 18%; } .r-company { width: 30%; } .r-contact { width: 17%; }

        @media print {
            .no-print { display: none; }
            body { max-width: none; }
        }
    </style>
</head>
<body onload="window.print()">

@php
    $v = fn (string $key) => $document->fieldValue($key) ?: '';

    $student = $document->internship->student;
    $studentName = $student->user->name ?? '';

    // Campus identity always comes from the student's campus record.
    $campus = $student->campus ?? $document->internship->campus;
    $campusLabel = preg_replace(
        '/^Pangasinan State University\s*[-,:]\s*/i',
        '',
        trim((string) ($campus?->name ?? 'Alaminos City Campus'))
    ) ?: 'Alaminos City Campus';

    // OJT Resume now uses separate structured fields for repeating sections.
    // Keep the older single-field values as fallbacks for previously submitted
    // records created before the form was changed.
    $workRows = [];
    for ($i = 1; $i <= 5; $i++) {
        $date = $v("work_experience_{$i}_date");
        $employer = $v("work_experience_{$i}_employer");
        $position = $v("work_experience_{$i}_position");

        if ($date || $employer || $position) {
            $workRows[] = compact('date', 'employer', 'position');
        }
    }

    if (! $workRows && $v('work_experience')) {
        $legacyLines = preg_split('/\r\n|\r|\n/', trim($v('work_experience')));
        foreach ($legacyLines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = preg_split('/\s*\|\s*|\s*;\s*/', trim($line), 3);
            $workRows[] = [
                'date' => $parts[0] ?? '',
                'employer' => $parts[1] ?? '',
                'position' => $parts[2] ?? '',
            ];
        }
    }

    $trainingRows = [];
    for ($i = 1; $i <= 6; $i++) {
        $title = $v("training_{$i}_title");
        $date = $v("training_{$i}_date");

        if ($title || $date) {
            $trainingRows[] = compact('title', 'date');
        }
    }

    if (! $trainingRows && $v('trainings_seminars')) {
        $legacyLines = preg_split('/\r\n|\r|\n/', trim($v('trainings_seminars')));
        foreach ($legacyLines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = preg_split('/\s*\|\s*|\s*;\s*/', trim($line), 2);
            $trainingRows[] = [
                'title' => $parts[0] ?? '',
                'date' => $parts[1] ?? '',
            ];
        }
    }

    $education = [
        'ELEMENTARY' => [
            'school' => $v('education_elementary_school'),
            'date' => $v('education_elementary_date'),
        ],
        'HIGH SCHOOL' => [
            'school' => $v('education_high_school_school'),
            'date' => $v('education_high_school_date'),
        ],
        'COLLEGE' => [
            'school' => $v('education_college_school'),
            'date' => $v('education_college_date'),
        ],
    ];

    if (
        ! array_filter($education, fn ($row) => $row['school'] || $row['date'])
        && $v('educational_background')
    ) {
        $legacyEducation = preg_split('/\r\n|\r|\n/', trim($v('educational_background')));
        $levels = array_keys($education);

        foreach ($levels as $index => $level) {
            if (! isset($legacyEducation[$index]) || trim($legacyEducation[$index]) === '') {
                continue;
            }

            $parts = preg_split('/\s*\|\s*|\s*;\s*/', trim($legacyEducation[$index]), 2);
            $education[$level] = [
                'school' => $parts[0] ?? '',
                'date' => $parts[1] ?? '',
            ];
        }
    }

    $referenceRows = [];
    for ($i = 1; $i <= 3; $i++) {
        $name = $v("reference_{$i}_name");
        $position = $v("reference_{$i}_position");
        $company = $v("reference_{$i}_company");
        $contact = $v("reference_{$i}_contact");

        if ($name || $position || $company || $contact) {
            $referenceRows[] = compact('name', 'position', 'company', 'contact');
        }
    }

    if (! $referenceRows && $v('references')) {
        $legacyLines = preg_split('/\r\n|\r|\n/', trim($v('references')));
        foreach ($legacyLines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = preg_split('/\s*\|\s*|\s*;\s*/', trim($line), 4);
            $referenceRows[] = [
                'name' => $parts[0] ?? '',
                'position' => $parts[1] ?? '',
                'company' => $parts[2] ?? '',
                'contact' => $parts[3] ?? '',
            ];
        }
    }

    $extracurricular = $v('extracurricular_activities');
    $nickname = $v('nickname');
    $height = $v('height');
    $age = $v('age');
    $weight = $v('weight');
    $gender = $v('gender');
    $civilStatus = $v('civil_status');
    $religion = $v('religion');
    $skills = $v('skills');
@endphp

<button class="no-print" onclick="window.print()">Print</button>

<div class="form-control-no">
    FM-AA-INT-11<br>
    Rev. 0<br>
    14-Oct-2024
</div>

<div class="sheet">

<table class="header-table">
<colgroup><col style="width:13%"><col style="width:87%"></colgroup>
    <tr>
        <td class="logo-cell"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU"></td>
        <td class="title-cell">
            <h1>INTERNSHIP RESUME</h1>
            <div>PANGASINAN STATE UNIVERSITY</div>
            <div>{{ $campusLabel }}</div>
        </td>
    </tr>
</table>

<table class="info-table">
<colgroup><col style="width:17%"><col style="width:58%"><col style="width:25%"></colgroup>
    <tr>
        <td class="c-label label">NAME</td>
        <td class="c-value">{{ $studentName }}</td>
        <td class="c-photo" rowspan="5">2x2 PHOTO</td>
    </tr>
    <tr>
        <td class="label">ADDRESS</td>
        <td>{{ $v('address') }}</td>
    </tr>
    <tr>
        <td class="label">TELEPHONE /<br>MOBILE NUMBER</td>
        <td>{{ $v('contact_number') }}</td>
    </tr>
    <tr>
        <td class="label">EMAIL ADDRESS</td>
        <td>{{ $v('email') }}</td>
    </tr>
    <tr>
        <td class="label career-label">CAREER OBJECTIVE</td>
        <td class="career-value">{{ $v('career_objective') }}</td>
    </tr>
</table>

<table>
<colgroup><col style="width:17%"><col style="width:58%"><col style="width:25%"></colgroup>
    <tr><td colspan="3" class="section-title">WORK EXPERIENCE</td></tr>
    <tr>
        <th class="w-17">INCLUSIVE DATE</th>
        <th class="w-58">EMPLOYER</th>
        <th class="w-25">POSITION</th>
    </tr>
    @for ($i = 0; $i < 5; $i++)
        @php $row = $workRows[$i] ?? ['date' => '', 'employer' => '', 'position' => '']; @endphp
        <tr>
            <td>{{ $row['date'] }}</td>
            <td>{{ $row['employer'] }}</td>
            <td>{{ $row['position'] }}</td>
        </tr>
    @endfor
</table>

<table>
<colgroup><col style="width:75%"><col style="width:25%"></colgroup>
    <tr><td colspan="2" class="section-title">TRAININGS AND SEMINARS ATTENDED</td></tr>
    <tr>
        <th class="w-75">TITLE OF SEMINAR</th>
        <th class="w-25">INCLUSIVE DATE</th>
    </tr>
    @for ($i = 0; $i < 6; $i++)
        @php $row = $trainingRows[$i] ?? ['title' => '', 'date' => '']; @endphp
        <tr>
            <td>{{ $row['title'] }}</td>
            <td>{{ $row['date'] }}</td>
        </tr>
    @endfor
</table>

<table>
<colgroup><col style="width:17%"><col style="width:58%"><col style="width:25%"></colgroup>
    <tr><td colspan="3" class="section-title">EDUCATIONAL BACKGROUND</td></tr>
    <tr>
        <th class="w-17">LEVEL</th>
        <th class="w-58">SCHOOL</th>
        <th class="w-25">INCLUSIVE DATE</th>
    </tr>
    @foreach ($education as $level => $row)
        <tr>
            <td>{{ $level }}</td>
            <td>{{ $row['school'] }}</td>
            <td>{{ $row['date'] }}</td>
        </tr>
    @endforeach
</table>

<table>
    <tr>
        <td class="extra-cell">
            <span class="label">EXTRACURRICULAR ACTIVITIES AND AWARDS RECEIVED</span>
            {{ $extracurricular }}
        </td>
    </tr>
</table>

<table>
<colgroup><col style="width:17%"><col style="width:36%"><col style="width:17.5%"><col style="width:29.5%"></colgroup>
    <tr><td colspan="4" class="section-title">PERSONAL BACKGROUND</td></tr>
    <tr>
        <td class="p-label">NICKNAME</td><td class="p-value">{{ $nickname }}</td>
        <td class="p-label2">HEIGHT</td><td class="p-value2">{{ $height }}</td>
    </tr>
    <tr>
        <td>AGE</td><td>{{ $age }}</td>
        <td>WEIGHT</td><td>{{ $weight }}</td>
    </tr>
    <tr>
        <td>GENDER</td><td>{{ $gender }}</td>
        <td>CIVIL STATUS</td><td>{{ $civilStatus }}</td>
    </tr>
    <tr>
        <td>RELIGION</td><td>{{ $religion }}</td>
        <td>SKILLS</td><td>{{ $skills }}</td>
    </tr>
</table>

<table>
<colgroup><col style="width:35%"><col style="width:18%"><col style="width:30%"><col style="width:17%"></colgroup>
    <tr><td colspan="4" class="section-title">REFERENCES</td></tr>
    <tr>
        <th class="r-name">NAME</th>
        <th class="r-pos">POSITION</th>
        <th class="r-company">COMPANY</th>
        <th class="r-contact">CONTACT NUMBER</th>
    </tr>
    @for ($i = 0; $i < 3; $i++)
        @php
            $row = $referenceRows[$i] ?? ['name' => '', 'position' => '', 'company' => '', 'contact' => ''];
        @endphp
        <tr>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['position'] }}</td>
            <td>{{ $row['company'] }}</td>
            <td>{{ $row['contact'] }}</td>
        </tr>
    @endfor
</table>

</div>

</body>
</html>
