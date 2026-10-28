{{--
    Academic year, typed instead of picked from a fixed list, so any year
    works (2036-2037 just as well as 2026-2027). Typing the first year is
    enough: "2026" becomes "2026-2027" when leaving the box or pressing
    Enter. The server reads the same formats.

    @include('partials.academic-year-input', [
        'name' => 'academic_year', 'id' => 'f_ay', 'value' => '2026-2027',
        'required' => true, 'small' => false, 'autoSubmit' => false,
    ])
--}}
@php
    $ayId = $id ?? 'academic_year';
    $ayCurrent = (int) substr(\App\Support\Term::current()[0], 0, 4);
@endphp
<input type="text" name="{{ $name ?? 'academic_year' }}" id="{{ $ayId }}"
       class="form-control {{ ($small ?? false) ? 'form-control-sm' : '' }} il-ay-input @error($name ?? 'academic_year') is-invalid @enderror"
       value="{{ $value ?? '' }}" placeholder="e.g. {{ \App\Support\Term::academicYear($ayCurrent) }}"
       inputmode="numeric" autocomplete="off" maxlength="11" list="{{ $ayId }}_list"
       pattern="\d{4}(\s*[-/]\s*\d{2,4})?" title="Type the academic year, e.g. 2026-2027 (or just 2026)"
       {{ ($required ?? false) ? 'required' : '' }} {{ ($autoSubmit ?? false) ? 'data-auto-submit' : '' }} {{ ($readonly ?? false) ? 'readonly' : '' }}>
<datalist id="{{ $ayId }}_list">
    @foreach (range($ayCurrent + 1, $ayCurrent - 2) as $y)
        <option value="{{ \App\Support\Term::academicYear($y) }}"></option>
    @endforeach
</datalist>
@error($name ?? 'academic_year')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
@once
<script>
(function () {
    // "2026" -> "2026-2027"; "2026-27", "2026 / 2027" -> "2026-2027".
    function normalize(value) {
        var v = value.trim(), m;
        if ((m = v.match(/^(\d{4})$/))) return m[1] + '-' + (+m[1] + 1);
        if ((m = v.match(/^(\d{4})\s*[-\/–]\s*(\d{2}|\d{4})$/))) {
            var end = m[2].length === 2 ? +(m[1].slice(0, 2) + m[2]) : +m[2];
            if (end === +m[1] + 1) return m[1] + '-' + end;
        }
        return v;
    }
    function valid(value) {
        var m = value.match(/^(\d{4})-(\d{4})$/);
        return !!m && +m[2] === +m[1] + 1;
    }
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.il-ay-input').forEach(function (input) {
            var last = input.value;
            function apply(submit) {
                input.value = normalize(input.value);
                var ok = input.value === '' || valid(input.value);
                input.setCustomValidity(ok ? '' : 'Type the academic year, e.g. 2026-2027 (or just 2026).');
                input.classList.toggle('is-invalid', !ok);
                if (submit && ok && input.value !== '' && input.value !== last && input.hasAttribute('data-auto-submit') && input.form) {
                    last = input.value;
                    input.form.submit();
                }
            }
            input.addEventListener('blur', function () { apply(true); });
            input.addEventListener('change', function () { apply(true); });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    apply(false);
                    if (input.hasAttribute('data-auto-submit') && input.form && input.checkValidity()) {
                        e.preventDefault();
                        input.form.submit();
                    }
                }
            });
            input.addEventListener('input', function () {
                input.setCustomValidity('');
                input.classList.remove('is-invalid');
            });
        });
    });
})();
</script>
@endonce
