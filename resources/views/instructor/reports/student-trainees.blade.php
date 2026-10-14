@extends('instructor.reports.print-layout')

@section('form_title', 'Masterlist of Student-Trainees')

@section('styles')
<style>
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    .info td { height: 20px; }
    .list td { height: 19px; }
    .list td.c { text-align: center; }
    .list th { font-size: 8pt; }
</style>
@endsection

@section('pages')
@forelse ($companies as $company)
    @php $hte = $company['hte']; @endphp
    <div class="page">
        <div class="form-control-no">FM-AA-INT-09<br>Rev. 1<br>14-Oct-2024</div>

        <div class="sheet">
            @include('instructor.reports._head', ['title' => 'Masterlist of Student-Trainees'])

            <table class="info">
                <colgroup><col style="width:28%"><col style="width:72%"></colgroup>
                <tr><td class="lbl">Name of Company</td><td>{{ $hte->name }}</td></tr>
                <tr><td class="lbl">Company Address</td><td>{{ $hte->address }}</td></tr>
                <tr><td class="lbl">Contact Person</td><td>{{ $hte->contact_person }}</td></tr>
                <tr><td class="lbl">Designation</td><td>{{ $hte->representative_position }}</td></tr>
                <tr><td class="lbl">Contact Number</td><td>{{ $hte->contact_phone }}</td></tr>
            </table>

            <table class="list">
                <colgroup><col style="width:62%"><col style="width:16%"><col style="width:22%"></colgroup>
                <tr>
                    <th>NAME OF STUDENT</th>
                    <th>YEAR</th>
                    <th>COURSE</th>
                </tr>
                @for ($i = 0; $i < max(26, $company['trainees']->count()); $i++)
                    <tr>
                        <td>{{ $company['trainees'][$i]['name'] ?? '' }}</td>
                        <td class="c">{{ $company['trainees'][$i]['year'] ?? '' }}</td>
                        <td class="c">{{ $company['trainees'][$i]['course'] ?? '' }}</td>
                    </tr>
                @endfor
            </table>

            @include('instructor.reports._signatures', ['rightLabel' => null])
        </div>
    </div>
@empty
    <p>No student-trainees have been deployed to a partner company yet.</p>
@endforelse
@endsection
