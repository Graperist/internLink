<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Units Earned for Practicum — {{ $document->internship->student->user->name }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 8mm; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.7px;
            color: #000;
            margin: 0 auto;
            max-width: 802px;
            line-height: 1.08;
        }

        .no-print { margin-bottom: 10px; }
        .form-control-no { text-align:right; font-size:8px; line-height:1.15; margin-bottom:2px; }
        .outer { border:1.5px solid #000; }

        .header {
            display:grid;
            grid-template-columns: 13% 87%;
            min-height:70px;
            border-bottom:1.5px solid #000;
        }

        .logo {
            border-right:1.5px solid #000;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .logo img { width:53px; height:53px; object-fit:contain; }

        .title {
            text-align:center;
            padding-top:10px;
            font-weight:700;
        }
        .title h1 { margin:0; font-size:16px; line-height:1.05; }
        .title h2 { margin:4px 0 0; font-size:10px; line-height:1.05; }
        .title div { margin-top:2px; font-size:9px; font-weight:400; }

        table { width:100%; border-collapse:collapse; table-layout:fixed; }
        th, td { border:1px solid #000; padding:2px 3px; height:17px; }
        th { font-weight:700; text-align:center; }

        .info td:first-child {
            width:20%;
            font-weight:700;
        }

        .info .sub-row td { height: 11px; padding: 0; font-size: 6.5px; font-weight: 400; text-align: center; border-top: 0; }
        .info td:first-child { width: 20%; }
        .year-title {
            border-top:0;
            border-bottom:1px solid #000;
            padding:3px 5px;
            font-weight:700;
            text-transform:uppercase;
        }

        .year-table th, .year-table td {
            height:16px;
            padding:2px;
            font-size:8px;
        }
        .year-col { width:16%; }
        .course-col { width:16%; }
        .unit-col { width:7%; }
        .total-col { width:9%; }

        .year-label {
            text-transform:uppercase;
            font-weight:700;
            vertical-align:middle;
            text-align:center;
        }

        .failed-title {
            border-top:1.5px solid #000;
            border-bottom:1px solid #000;
            padding:3px 5px;
            font-weight:700;
        }

        .failed-table th, .failed-table td { height:17px; font-size:8.3px; }
        .failed-code { width:25%; }
        .failed-title-col { width:55%; }
        .failed-units { width:20%; }

        .total-row td {
            height:19px;
            font-weight:700;
        }

        .certification {
            border-top:1.5px solid #000;
            text-align:center;
            padding:20px 0 14px;
        }
        .cert-label { font-weight:700; margin-bottom:15px; }
        .registrar-sign { display:inline-block; min-width:190px; }
        .registrar-name { font-weight:700; padding:0 10px 1px; }
        .registrar-line { border-top:1px solid #000; margin:0 0 3px; }
        .registrar-title { font-size:8.5px; }

        .wrap { white-space:normal; overflow-wrap:anywhere; }
        @media print { .no-print{display:none;} }
            /* Signatory names are printed in capital letters on every form. */
        .registrar-name { text-transform: uppercase; }
    </style>
    @include('partials.a4-page')
</head>
<body onload="window.print()">

@php
    $value = fn (string $key) => $document->fieldValue($key) ?: '';

    $internship = $document->internship;
    $student = $internship?->student;

    $campusName = trim($internship?->campus?->name ?? 'Alaminos City Campus');
    $campusName = preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', $campusName) ?: 'Alaminos City Campus';

    $firstName = trim($value('given_name'));
    $middleName = trim($value('middle_name'));
    $surname = trim($value('surname'));

    $studentFullName = trim(implode(' ', array_filter([$firstName, $middleName, $surname])))
        ?: ($student?->user?->name ?? '____________________________');

    $course = $value('course') ?: ($student?->program?->name ?? '');
    $major = $value('major');

    $decodeRows = function ($raw, int $count) {
        $decoded = json_decode($raw ?: '', true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $defaults = [
            'semester1' => '', 'units1' => '',
            'semester2' => '', 'units2' => '',
            'summer' => '', 'summer_units' => '',
            'total' => '',
        ];

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = array_merge($defaults, is_array($decoded[$i] ?? null) ? $decoded[$i] : []);
        }

        return $rows;
    };

    $years = [
        'FIRST YEAR' => $decodeRows($value('first_year_courses'), 7),
        'SECOND YEAR' => $decodeRows($value('second_year_courses'), 7),
        'THIRD YEAR' => $decodeRows($value('third_year_courses'), 7),
        'FOURTH YEAR' => $decodeRows($value('fourth_year_courses'), 7),
    ];

    $failed = json_decode($value('failed_courses') ?: '', true);
    $failed = is_array($failed) ? $failed : [];
    for ($i = count($failed); $i < 6; $i++) {
        $failed[] = ['code' => '', 'title' => '', 'units' => ''];
    }
@endphp

<button class="no-print" onclick="window.print()">Print</button>

<div class="form-control-no">
    FM-AA-INT-02<br>
    Rev. 1<br>
    14-Oct-2024
</div>

<div class="outer">
    <div class="header">
        <div class="logo">
            <img src="{{ asset('images/psu-logo.png') }}" alt="PSU">
        </div>
        <div class="title">
            <h1>CERTIFICATION OF UNITS EARNED FOR<br>PRACTICUM/INTERNSHIP</h1>
            <h2>PANGASINAN STATE UNIVERSITY</h2>
            <div>{{ $campusName }}</div>
        </div>
    </div>

    <table class="info">
        <tr>
            <td rowspan="2">NAME</td>
            <td class="wrap">{{ $surname }}</td>
            <td class="wrap">{{ $firstName }}</td>
            <td class="wrap">{{ $middleName }}</td>
        </tr>
        <tr class="sub-row">
            <td>Last Name</td>
            <td>First Name</td>
            <td>Middle Name</td>
        </tr>
        <tr>
            <td>COURSE</td>
            <td colspan="3" class="wrap">{{ $course }}</td>
        </tr>
        <tr>
            <td>MAJOR</td>
            <td colspan="3">{{ $major }}</td>
        </tr>
    </table>

    <div class="year-title">COURSES EARNED</div>
    <table class="year-table">
        <tr>
            <th class="year-col">YEAR LEVEL</th>
            <th class="course-col">1<sup>st</sup> SEMESTER</th><th class="unit-col">Units</th>
            <th class="course-col">2<sup>nd</sup> SEMESTER</th><th class="unit-col">Units</th>
            <th class="course-col">SUMMER</th><th class="unit-col">Units</th>
            <th class="total-col">TOTAL</th>
        </tr>
        @foreach ($years as $label => $rows)
            @foreach ($rows as $index => $row)
                <tr>
                    @if ($index === 0)
                        <td rowspan="{{ count($rows) }}" class="year-label">{{ $label }}</td>
                    @endif
                    <td class="wrap">{{ $row['semester1'] }}</td>
                    <td>{{ $row['units1'] }}</td>
                    <td class="wrap">{{ $row['semester2'] }}</td>
                    <td>{{ $row['units2'] }}</td>
                    <td class="wrap">{{ $row['summer'] }}</td>
                    <td>{{ $row['summer_units'] }}</td>
                    <td>{{ $row['total'] }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr class="total-row">
            <td colspan="7">TOTAL</td>
            <td>{{ $value('record_total') }}</td>
        </tr>
    </table>

    <div class="failed-title">COURSES FAILED TO TAKE AS OF THE PRESENT</div>
    <table class="failed-table">
        <tr>
            <th class="failed-code">COURSE CODE</th>
            <th class="failed-title-col">COURSE TITLE</th>
            <th class="failed-units">UNITS</th>
        </tr>
        @foreach ($failed as $row)
            <tr>
                <td>{{ $row['code'] ?? '' }}</td>
                <td class="wrap">{{ $row['title'] ?? '' }}</td>
                <td>{{ $row['units'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>

    <div class="certification">
        <div class="cert-label">CERTIFIED TRUE AND CORRECT</div>
        {{-- Name printed on top of the signature line, title below it. --}}
        <div class="registrar-sign">
            <div class="registrar-name">{!! ($registrarName = \App\Support\Signatories::for($document->internship)['registrar']) !== '' ? e($registrarName) : '&nbsp;' !!}</div>
            <div class="registrar-line"></div>
            <div class="registrar-title">Registrar</div>
        </div>
    </div>
</div>

</body>
</html>
