@extends('instructor.reports.print-layout')

@section('form_title', 'Internship Site Monitoring Report')

@section('styles')
<style>
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    .info td { height: 17px; }
    .info .lbl { font-size: 8pt; line-height: 1.05; }
    .list td { height: 17px; }
    .list td.c { text-align: center; }
    .list th { font-size: 8pt; }
    .fb td { vertical-align: middle; }
    .fb .q { width: 20%; }
    .fb .tall td { height: 42px; }
    .fb .taller td { height: 46px; }
    .page .sign td { height: 78px; }
    .bar-row td { border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; padding: 1px 5px; }
</style>
@endsection

@section('pages')
@forelse ($companies as $company)
    @php $hte = $company['hte']; $rows = max(8, $company['trainees']->count()); @endphp
    <div class="page">
        <div class="form-control-no">FM-AA-INT-16<br>Rev. 0<br>03-Oct-2017</div>

        <div class="sheet">
            @include('instructor.reports._head', ['title' => 'Internship Site Monitoring Report'])

            <table class="info">
                <colgroup><col style="width:20%"><col style="width:80%"></colgroup>
                <tr><td class="lbl">Name of Company</td><td>{{ $hte->name }}</td></tr>
                <tr><td class="lbl">Company Address</td><td>{{ $hte->address }}</td></tr>
                <tr><td class="lbl">Date of Monitoring</td><td>{{ $monitoringDate }}</td></tr>
            </table>

            <table class="list">
                <colgroup><col style="width:63%"><col style="width:17%"><col style="width:20%"></colgroup>
                <tr><th>NAME OF STUDENT-TRAINEES</th><th>COURSE</th><th>YEAR LEVEL</th></tr>
                @for ($i = 0; $i < $rows; $i++)
                    <tr>
                        <td>{{ $company['trainees'][$i]['name'] ?? '' }}</td>
                        <td class="c">{{ $company['trainees'][$i]['course'] ?? '' }}</td>
                        <td class="c">{{ $company['trainees'][$i]['year'] ?? '' }}</td>
                    </tr>
                @endfor
            </table>

            <table class="fb">
                <colgroup><col style="width:20%"><col style="width:80%"></colgroup>
                <tr class="bar-row"><td colspan="2" class="bar">Training Supervisor's Feedback</td></tr>
                <tr class="tall"><td class="q">Attendance and Punctuality</td><td></td></tr>
                <tr class="tall"><td class="q">Performance</td><td></td></tr>
                <tr class="tall"><td class="q">General Attitude</td><td></td></tr>
                <tr class="bar-row"><td colspan="2" class="bar">Student-Trainee's Feedback</td></tr>
                <tr class="taller"><td class="q">Training</td><td></td></tr>
                <tr class="taller"><td class="q">Working Condition</td><td></td></tr>
                <tr class="taller"><td class="q">Supervisor/Employer's Treatment</td><td></td></tr>
                <tr class="bar-row"><td colspan="2" class="bar">Problems Met and Actions Taken</td></tr>
                <tr class="tall"><td class="q">Problems Met</td><td></td></tr>
                <tr class="taller"><td class="q">Actions Taken</td><td></td></tr>
            </table>

            @include('instructor.reports._signatures', ['rightLabel' => null])
        </div>
    </div>
@empty
    <p>No student-trainees have been deployed to a partner company yet.</p>
@endforelse
@endsection
