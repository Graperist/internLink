{{--
    Form 1 - B. Academic Credentials/Qualifications, entered as a table
    (like Form 2): SEM is a dropdown, SY is picked by its first year and
    becomes "2026-2027", the subject and its result are typed in and DATE
    is picked from a calendar. SIGNATURE is signed on the printed copy.
    "Add row" adds rows beyond the five on the official form.
--}}
@php
    $val = fn (string $key) => old($key, $document?->fieldValue($key) ?? '');
    $semesters = \App\Support\Form1Academics::SEMESTERS;
    $schoolYears = \App\Support\Form1Academics::schoolYearOptions();
    $baseRows = \App\Support\Form1Academics::ROWS;
    $maxRows = \App\Support\Form1Academics::MAX_ROWS;
    $rowCount = \App\Support\Form1Academics::rowCount($val);
    $semLabel = fn (string $option) => str_replace('emester', 'em', $option);
@endphp

<h2 class="h6 fw-bold mt-4 mb-2">Academic Credentials/Qualifications</h2>
<p class="text-secondary small mb-3">
    List the subjects you have taken that the Registrar should check. Choose the semester and the school year
    (pick the first year &mdash; e.g. 2026 becomes 2026-2027), type the subject and its result, and pick the date.
    Need more rows? Click <strong>Add row</strong>. The Signature column is signed on the printed copy.
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
                <th style="width: 108px;">SEM</th>
                <th style="width: 118px;">SY</th>
                <th>SUBJECT</th>
                <th style="width: 70px;">PASSING</th>
                <th style="width: 70px;">FAILING</th>
                <th style="width: 64px;">GRADE</th>
                <th style="width: 138px;">DATE</th>
                <th style="width: 34px;"><span class="visually-hidden">Remove</span></th>
            </tr>
        </thead>
        <tbody id="academicRows">
            @for ($i = 1; $i <= $rowCount; $i++)
                @php
                    $sem = $val("academic_{$i}_sem");
                    $sy = $val("academic_{$i}_sy");
                    $years = $schoolYears;
                    if ($sy !== '' && ! array_key_exists($sy, $years)) {
                        $years = [$sy => (int) $sy] + $years;   // keep an older saved value selectable
                    }
                @endphp
                <tr data-row="{{ $i }}">
                    <td>
                        <select class="form-select form-select-sm @error("academic_{$i}_sem") is-invalid @enderror" name="academic_{{ $i }}_sem" aria-label="Row {{ $i }} semester">
                            <option value="">&mdash;</option>
                            @foreach ($semesters as $option)
                                <option value="{{ $option }}" @selected($sem === $option)>{{ $semLabel($option) }}</option>
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
                    <td><input type="date" class="form-control form-control-sm @error("academic_{$i}_date") is-invalid @enderror" name="academic_{{ $i }}_date" value="{{ $val("academic_{$i}_date") }}" aria-label="Row {{ $i }} date"></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 il-row-remove" title="{{ $i > $baseRows ? 'Remove this row' : 'Clear this row' }}" aria-label="{{ $i > $baseRows ? 'Remove' : 'Clear' }} row {{ $i }}">
                            <i class="ti ti-{{ $i > $baseRows ? 'trash' : 'eraser' }}"></i>
                        </button>
                    </td>
                </tr>
            @endfor
        </tbody>
    </table>
</div>
<div class="d-flex flex-wrap align-items-center gap-2 mb-0">
    <button type="button" class="btn btn-sm btn-outline-primary" id="addAcademicRow"><i class="ti ti-plus"></i> Add row</button>
    <span class="text-secondary small">A row with a subject needs its SEM and SY. Blank rows are left out. Up to {{ $maxRows }} rows.</span>
</div>

<style>
    .il-academic-table { table-layout: fixed; min-width: 640px; }
    .il-academic-table th { font-size: 0.72rem; letter-spacing: .02em; }
    .il-academic-table td { padding: 4px; }
    .il-academic-table .form-select-sm { padding-right: 1.5rem; background-position: right .35rem center; }
    .il-academic-table input, .il-academic-table select { width: 100%; }
    .il-academic-table input[type="date"] { padding-left: .35rem; padding-right: .25rem; }
</style>
<script>
(function () {
    const body = document.getElementById('academicRows');
    const addButton = document.getElementById('addAcademicRow');
    const baseRows = {{ $baseRows }};
    const maxRows = {{ $maxRows }};

    // The SY list shows the first year only (2026, 2025, ...). Once a year is
    // picked, the chosen option reads as the full school year (2026-2027).
    function syncSchoolYear(select) {
        Array.from(select.options).forEach(function (option) {
            if (option.dataset.year) {
                option.textContent = option.selected ? option.value : option.dataset.year;
            }
        });
    }

    // Keep the names in order (academic_1_..., academic_2_...) after rows
    // are added or removed.
    function renumber() {
        Array.from(body.rows).forEach(function (tr, index) {
            const n = index + 1;
            tr.dataset.row = n;
            tr.querySelectorAll('[name^="academic_"]').forEach(function (el) {
                el.name = el.name.replace(/^academic_\d+_/, 'academic_' + n + '_');
                el.setAttribute('aria-label', el.getAttribute('aria-label').replace(/^Row \d+/, 'Row ' + n));
            });
        });
        addButton.disabled = body.rows.length >= maxRows;
    }

    function clearRow(tr) {
        tr.querySelectorAll('input').forEach(function (el) { el.value = ''; });
        tr.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; if (el.classList.contains('il-sy-select')) syncSchoolYear(el); });
        tr.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    }

    body.addEventListener('change', function (e) {
        if (e.target.classList.contains('il-sy-select')) syncSchoolYear(e.target);
    });

    body.addEventListener('click', function (e) {
        const button = e.target.closest('.il-row-remove');
        if (!button) return;
        const tr = button.closest('tr');
        if (body.rows.length > baseRows) {
            tr.remove();
            renumber();
        } else {
            clearRow(tr);
        }
        updateRemoveButtons();
    });

    function updateRemoveButtons() {
        const canRemove = body.rows.length > baseRows;
        body.querySelectorAll('.il-row-remove').forEach(function (button) {
            button.title = canRemove ? 'Remove this row' : 'Clear this row';
            button.querySelector('i').className = 'ti ti-' + (canRemove ? 'trash' : 'eraser');
        });
    }

    addButton.addEventListener('click', function () {
        if (body.rows.length >= maxRows) return;
        const copy = body.rows[body.rows.length - 1].cloneNode(true);
        clearRow(copy);
        body.appendChild(copy);
        renumber();
        updateRemoveButtons();
        copy.querySelector('select').focus();
    });

    renumber();
    updateRemoveButtons();
})();
</script>
