{{--
    One rating question as a numbered card with 5-1 choice buttons.
    $field, $value (current answer), $number, $legend, $required (bool).
--}}
<fieldset class="rate-q {{ filled($value) ? 'is-answered' : '' }}" data-rate-q>
    <legend class="rate-q-text"><span class="rate-q-no">{{ $number }}</span><span>{{ $label ?? $field->label }}</span></legend>
    <div class="rate-opts">
        @foreach (['5', '4', '3', '2', '1'] as $rate)
            <input type="radio" class="btn-check" id="{{ $field->field_key }}_{{ $rate }}" name="{{ $field->field_key }}" value="{{ $rate }}"
                   autocomplete="off" @checked((string) $value === $rate) {{ $required ? 'required' : '' }}>
            <label class="rate-opt" for="{{ $field->field_key }}_{{ $rate }}">
                <span class="rate-num">{{ $rate }}</span>
                @if ((string) ($legend[$rate] ?? $rate) !== $rate)
                    <span class="rate-txt">{{ $legend[$rate] }}</span>
                @endif
            </label>
        @endforeach
    </div>
</fieldset>
