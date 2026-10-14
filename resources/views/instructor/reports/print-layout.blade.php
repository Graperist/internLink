<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('form_title') - {{ $campusLabel }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 0.5in; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
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
        }

        .sheet { border: 1.5px solid #000; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th {
            border: 1px solid #000;
            padding: 2px 5px;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }
        .sheet > table:first-child tr:first-child > * { border-top: 0; }
        .sheet td:first-child, .sheet th:first-child { border-left: 0; }
        .sheet td:last-child, .sheet th:last-child { border-right: 0; }
        .sheet table + table { margin-top: -1px; }

        th { font-weight: 700; text-align: center; }
        .lbl { font-weight: 700; text-transform: uppercase; }
        .bar { font-weight: 700; text-transform: uppercase; background: #fff; padding: 1px 5px; }

        /* Header: logo cell + title cell */
        .head td { height: 74px; }
        .head .logo { width: 13%; text-align: center; padding: 2px; }
        .head .logo img { width: 58px; height: 58px; object-fit: contain; display: block; margin: 0 auto; }
        .head .title { text-align: center; }
        .head h1 { font-size: 15pt; margin: 0 0 3px; font-weight: 700; line-height: 1.1; text-transform: uppercase; }
        .head .sub { font-size: 7pt; line-height: 1.3; }

        /* Signature blocks */
        .sign td { height: 92px; vertical-align: top; padding: 4px 6px; }
        .sign .role { font-weight: 700; text-transform: uppercase; display: block; }
        .sig { width: 78%; margin: 30px auto 0; text-align: center; }
        .sig .name { border-bottom: 1px solid #000; min-height: 14px; font-weight: 700; font-size: 8.5pt; padding: 0 4px 1px; }
        .sig .cap { font-style: italic; font-size: 7.5pt; margin-top: 2px; }
        .sig .date { font-style: italic; font-size: 7.5pt; margin-top: 7px; text-align: center; }

        @media print {
            .no-print { display: none; }
            body { max-width: none; }
        }
    </style>
    @yield('styles')
</head>
<body>

<button class="no-print" onclick="window.print()">Print</button>

@hasSection('pages')
    @yield('pages')
@else
    <div class="form-control-no">
        @yield('form_no')<br>
        @yield('form_rev')<br>
        @yield('form_date')
    </div>

    <div class="sheet">
        @include('instructor.reports._head', ['title' => trim($__env->yieldContent('form_title'))])

        @yield('content')
    </div>
@endif

</body>
</html>
