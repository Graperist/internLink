@extends('instructor.reports.print-layout')

@section('form_title', 'Directory of Student-Interns')
@section('form_no', 'FM-AA-INT-05')
@section('form_rev', 'Rev. 1')
@section('form_date', '14-Oct-2024')

@section('styles')
<style>
    .list td { height: 31px; font-size: 8pt; }
    .list td.c { text-align: center; }
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
    <colgroup><col style="width:24%"><col style="width:30%"><col style="width:26%"><col style="width:20%"></colgroup>
    <tr>
        <th>NAME OF STUDENT-INTERN</th>
        <th>HOME ADDRESS</th>
        <th>EMAIL ADDRESS</th>
        <th>CONTACT NUMBER</th>
    </tr>
    @for ($i = 0; $i < max(22, $rows->count()); $i++)
        <tr>
            <td>{{ $rows[$i]['name'] ?? '' }}</td>
            <td>{{ $rows[$i]['address'] ?? '' }}</td>
            <td>{{ $rows[$i]['email'] ?? '' }}</td>
            <td class="c">{{ $rows[$i]['contact'] ?? '' }}</td>
        </tr>
    @endfor
</table>
@endsection
