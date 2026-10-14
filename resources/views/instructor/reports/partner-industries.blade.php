@extends('instructor.reports.print-layout')

@section('form_title', 'Masterlist of Partner Industries')
@section('form_no', 'FM-AA-INT-06')
@section('form_rev', 'Rev. 1')
@section('form_date', '14-Oct-2024')

@section('styles')
<style>
    .list td { height: 19px; }
    .list td.num { text-align: center; }
    .list th { font-size: 8pt; }
</style>
@endsection

@section('content')
<table>
    <colgroup><col style="width:17%"><col style="width:83%"></colgroup>
    <tr>
        <td class="lbl">College</td>
        <td>{{ $collegeName }}</td>
    </tr>
</table>

<table>
    <colgroup><col style="width:17%"><col style="width:41%"><col style="width:22%"><col style="width:20%"></colgroup>
    <tr>
        <td class="lbl">Semester</td>
        <td>{{ $semester }}</td>
        <td class="lbl">Academic Year</td>
        <td>{{ $academicYear }}</td>
    </tr>
</table>

<table class="list">
    <colgroup><col style="width:75%"><col style="width:25%"></colgroup>
    <tr>
        <th>PARTNER INDUSTRIES</th>
        <th>TOTAL NUMBER OF<br>STUDENT-TRAINEES</th>
    </tr>
    @for ($i = 0; $i < max(32, $rows->count()); $i++)
        <tr>
            <td>{{ $rows[$i]['name'] ?? '' }}</td>
            <td class="num">{{ $rows[$i]['total'] ?? '' }}</td>
        </tr>
    @endfor
</table>

@include('instructor.reports._signatures', ['rightLabel' => null])
@endsection
