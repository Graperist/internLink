{{-- Logo + title header shared by every instructor report sheet. --}}
<table class="head">
    <colgroup><col style="width:13%"><col style="width:87%"></colgroup>
    <tr>
        <td class="logo"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU"></td>
        <td class="title">
            <h1>{{ $title }}</h1>
            <div class="sub">PANGASINAN STATE UNIVERSITY</div>
            <div class="sub">{{ $campusLabel }}</div>
        </td>
    </tr>
</table>
