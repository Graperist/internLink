{{--
    A rating question whose levels each have their own description (Form 13
    competencies): a numbered card with one selectable row per level.
    $field, $value, $number, $label, $meta (e.g. "20%"), $levels [rate => text], $required.
    Optional $remarksField / $remarksValue: a remarks box shown inside the card.
--}}
<fieldset class="rate-q {{ filled($value) ? 'is-answered' : '' }}" data-rate-q>
    <legend class="rate-q-text"><span class="rate-q-no">{{ $number }}</span>
        <span>{{ $label }} @if (! empty($meta))<span class="rate-q-meta">({{ $meta }})</span>@endif</span>
    </legend>
    <div class="rate-rows">
        @foreach ($levels as $rate => $text)
            <input type="radio" class="btn-check {{ $extraClass ?? '' }}" id="{{ $field->field_key }}_{{ $rate }}" name="{{ $field->field_key }}" value="{{ $rate }}"
                   autocomplete="off" @checked((string) $value === (string) $rate) {{ $required ? 'required' : '' }}>
            <label class="rate-row" for="{{ $field->field_key }}_{{ $rate }}">
                <span class="rate-num">{{ $rate }}</span>
                <span>{{ $text }}</span>
            </label>
        @endforeach
    </div>
    @if (! empty($remarksField))
        <div class="rate-remarks">
            <label class="rate-remarks-label" for="{{ $remarksField->field_key }}"><i class="ti ti-message-2"></i> Remarks on {{ $remarksName ?? 'this competency' }} <span>(optional)</span></label>
            <textarea class="form-control" id="{{ $remarksField->field_key }}" name="{{ $remarksField->field_key }}" rows="2"
                      placeholder="Add a short note if needed" {{ $remarksField->is_required ? 'required' : '' }}>{{ $remarksValue }}</textarea>
        </div>
    @endif
</fieldset>
