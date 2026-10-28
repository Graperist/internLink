@extends('instructor.reports.print-layout')

@section('form_title', 'Accreditation Instrument of PSU Partner Agencies as Host Training Establishment')
@section('form_no', 'FM-AA-INT-24')
@section('form_rev', 'Rev. 02')
@section('form_date', '14-Oct-2024')

@section('styles')
<style>
    .head h1 { font-size: 13.5pt; }
    .info td { height: 20px; }
    .direction td { text-align: center; font-size: 8pt; padding: 6px 30px; }
    .criteria th { font-size: 7.5pt; height: 26px; padding: 2px 3px; }
    .criteria td.crit { height: 72px; padding: 4px 6px 4px 22px; text-indent: -14px; font-size: 8.5pt; }
    .criteria tr.long td.crit { height: 88px; }
    .acc-sign td { height: 70px; vertical-align: top; padding: 4px 6px; border-top: 0; border-bottom: 0; }
    .acc-sign tr:first-child td { border-top: 1px solid #000; }
    .acc-sign td { border-left: 0; border-right: 0; }
    .acc-sign .role { font-weight: 700; text-transform: uppercase; display: block; }
    .acc-sign .sig { width: 62%; margin: 22px auto 0; }
    .acc-sign .sig .cap { font-style: normal; }
</style>
@endsection

@section('content')
    @if ($hte)
        <table class="info">
            <colgroup><col style="width:28%"><col style="width:72%"></colgroup>
            <tr><td class="lbl">Name of Partner Agency</td><td>{{ $hte->name }}</td></tr>
            <tr><td class="lbl">Address</td><td>{{ $hte->address }}</td></tr>
        </table>
    @endif

    <table class="direction">
        <tr><td>
            <strong>DIRECTION</strong>: <em>Please put a check mark (&#10003;) on the appropriate column under the level of compliance and opposite the criteria for selecting and accrediting cooperating agency which corresponds to your answer.</em>
        </td></tr>
    </table>

    <table class="criteria">
        <colgroup><col style="width:52%"><col style="width:11.5%"><col style="width:11.5%"><col style="width:25%"></colgroup>
        <tr>
            <th>CRITERIA FOR SELECTING AND ACCREDITING COOPERATING AGENCY</th>
            <th>COMPLIANT</th>
            <th>NON-<br>COMPLIANT</th>
            <th>REMARKS</th>
        </tr>
        @foreach ($criteria as $i => $criterion)
            <tr class="{{ mb_strlen($criterion) > 200 ? 'long' : '' }}">
                <td class="crit">{{ $i + 1 }}. {{ $criterion }}</td>
                <td></td><td></td><td></td>
            </tr>
        @endforeach
    </table>

    <table class="acc-sign">
        <colgroup><col style="width:50%"><col style="width:50%"></colgroup>
        <tr>
            <td>
                <span class="role">Prepared by:</span>
                <div class="sig"><div class="name">{{ $instructorName }}</div><div class="cap">Internship Instructor</div></div>
            </td>
            <td>
                <span class="role">Reviewed by:</span>
                <div class="sig"><div class="name">{{ $coordinatorName }}</div><div class="cap">Campus Internship Coordinator</div></div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="role">Recommended:</span>
                <div class="sig"><div class="name">{{ $deanName }}</div><div class="cap">College Dean</div></div>
            </td>
            <td>
                <span class="role">Approved:</span>
                <div class="sig"><div class="name">{{ $cedName }}</div><div class="cap">Campus Executive Director</div></div>
            </td>
        </tr>
    </table>
@endsection
