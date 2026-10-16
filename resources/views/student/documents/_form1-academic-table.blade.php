{{--
    Form 1 - B. Academic Credentials/Qualifications, entered as a table
    (like Form 2): SEM is a dropdown, SY is picked by its first year and
    becomes "2026-2027", and the subject, passing, failing and grade are
    typed in. Signature and Date are signed by hand on the printed copy.
--}}
@php
    $val = fn (string $key) => old($key, $document?->fieldValue($key) ?? '');
    $semesters = \App\Support\Form1Academics::SEMESTERS;
    $schoolYears = \App\Support\Form1Academics::schoolYearOptions();
    $rows = \App\Support\Form1Academics::ROWS;
@endphp

<h2 class="h6 fw-bold mt-4 mb-2">Academic Credentials/Qualifications</h2>
<p class="text-secondary small mb-3">
    List the subjects you have taken that the Registrar should check. Choose the semester and the school year
    (pick the first year &mdash; e.g. 2026 becomes 2026-2027), then type the subject and its result.
    The Signature and Date columns are signed on the printed copy.
</p>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label class="il-form-label" for="passed_units">Total Passed Units</label>
        <input type="number" min="0" max="999" class="form-control" id="passed_units" name="passed_units" value="{{ $val('passed_units') }}" placeholder="e.g. 150">
    </div>
</div>

<div class="table-responsive border rounded mb-2">
    <table class="table table-sm table-bordered align-middle mb-0 il-academic-table">
        <thead class="table-light">
            <tr class="small">
                <th style="width: 112px;">SEM</th>
                <th style="width: 118px;">SY</th>
                <th>SUBJECT</th>
                <th style="width: 66px;">PASSING</th>
                <th style="width: 66px;">FAILING</th>
                <th style="width: 60px;">GRADE</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 1; $i <= $rows; $i++)
                @php
                    $sem = $val("academic_{$i}_sem");
                    $sy = $val("academic_{$i}_sy");
                    $years = $schoolYears;
                    if ($sy !== '' && ! array_key_exists($sy, $years)) {
                        $years = [$sy => $sy] + $years;   // keep an older saved value selectable
                    }
                @endphp
                <tr>
                    <td>
                        <select class="form-select form-select-sm @error("academic_{$i}_sem") is-invalid @enderror" name="academic_{{ $i }}_sem" aria-label="Row {{ $i }} semester">
                            <option value="">&mdash;</option>
                            @foreach ($semesters as $option)
                                <option value="{{ $option }}" @selected($sem === $option)>{{ str_replace('emester', 'em', $option) }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm il-sy-select @error("academic_{$i}_sy") is-invalid @enderror" name="academic_{{ $i }}_sy" aria-label="Row {{ $i }} school year">
                            <option value="">&mdash;</option>
                            @foreach ($years as $value => $startYear)
                                <option value="{{ $value }}" data-year="{{ $startYear }}" @selected($sy === $value)>{{ $sy === $value ? $value : $startYear }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="text" class="form-control form-control-sm" name="academic_{{ $i }}_subject" value="{{ $val("academic_{$i}_subject") }}" maxlength="255" aria-label="Row {{ $i }} subject"></td>
                    <td><input type="text" class="form-control form-control-sm" name="academic_{{ $i }}_passing" value="{{ $val("academic_{$i}_passing") }}" maxlength="20" aria-label="Row {{ $i }} passing"></td>
                    <td><input type="text" class="form-control form-control-sm" name="academic_{{ $i }}_failing" value="{{ $val("academic_{$i}_failing") }}" maxlength="20" aria-label="Row {{ $i }} failing"></td>
                    <td><input type="text" class="form-control form-control-sm" name="academic_{{ $i }}_grade" value="{{ $val("academic_{$i}_grade") }}" maxlength="20" aria-label="Row {{ $i }} grade"></td>
                </tr>
            @endfor
        </tbody>
    </table>
</div>
<p class="text-secondary small mb-0">Leave a row blank if you don't need it. A row with a subject needs its SEM and SY.</p>

<style>
    .il-academic-table { table-layout: fixed; min-width: 560px; }
    .il-academic-table th { font-size: 0.72rem; letter-spacing: .02em; }
    .il-academic-table td { padding: 4px; }
    .il-academic-table .form-select-sm { padding-right: 1.6rem; background-position: right .4rem center; }
    .il-academic-table input, .il-academic-table select { width: 100%; }
</style>
<script>
    // The SY list shows the first year only (2026, 2025, ...). Once a year is
    // picked, the chosen option reads as the full school year (2026-2027).
    document.querySelectorAll('.il-sy-select').forEach(function (select) {
        select.addEventListener('change', function () {
            Array.from(select.options).forEach(function (option) {
                if (option.dataset.year) {
                    option.textContent = option.selected ? option.value : option.dataset.year;
                }
            });
        });
    });
</script>
