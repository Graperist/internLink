{{--
    Prepared by (Instructor) / Noted by (Coordinator) + CED block.
    $rightLabel: label printed above the CED signature ('NOTED BY' for the
    wide variant, or 'APPROVED BY' as a separate column).
--}}
@php $rightLabel = $rightLabel ?? null; @endphp

<table class="sign">
    <tr>
        <td>
            <span class="role">Prepared by:</span>
            <div class="sig" style="margin-top:34px; width:44%; margin-left:8%;">
                <div class="name">{{ $instructorName }}</div>
                <div class="cap">Internship / Practicum Subject Instructor</div>
                <div class="date">Date: _______________________________</div>
            </div>
        </td>
    </tr>
</table>

<table class="sign">
    <colgroup><col style="width:50%"><col style="width:50%"></colgroup>
    <tr>
        <td>
            <span class="role">Noted by:</span>
            <div class="sig" style="margin-top:24px; width:86%;">
                <div class="name">{{ $coordinatorName }}</div>
                <div class="cap">Campus Internship Coordinator</div>
                <div class="date">Date: _______________________________</div>
            </div>
        </td>
        <td>
            @if ($rightLabel)
                <span class="role">{{ $rightLabel }}</span>
            @endif
            <div class="sig" style="margin-top: {{ $rightLabel ? '24px' : '38px' }}; width:86%;">
                <div class="name">{{ $cedName }}</div>
                <div class="cap">Campus Executive Director</div>
                <div class="date">Date: _______________________________</div>
            </div>
        </td>
    </tr>
</table>
