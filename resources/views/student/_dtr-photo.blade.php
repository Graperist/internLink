{{--
    The photo taken at the HTE for one time in / time out ($stamp: am_in,
    am_out, pm_in, pm_out). Opens the camera on a phone. A saved photo is
    shown; taking a new one replaces it.
--}}
@php
    $savedPhoto = $form?->photo($stamp);
    $failedPhoto = $failedHere ? $errors->first($stamp.'_photo') : null;
@endphp
<div class="mt-2 dtr-photo">
    <label class="form-label small mb-1 d-flex align-items-center gap-1" for="{{ $stamp }}_photo">
        <i class="ti ti-camera"></i> {{ $label }}
        @unless ($savedPhoto)<span class="text-danger">*</span>@endunless
    </label>
    @if ($savedPhoto)
        <a href="{{ $form->photoUrl($stamp) }}" target="_blank" class="d-flex align-items-center gap-2 small text-decoration-none mb-1">
            <img src="{{ $form->photoUrl($stamp) }}" alt="{{ $label }}" class="rounded border" style="width:44px;height:44px;object-fit:cover;">
            <span class="text-secondary">Saved {{ \Carbon\Carbon::parse($savedPhoto['at'])->format('g:i A') }}</span>
        </a>
    @endif
    @unless ($locked)
        <input type="file" class="form-control form-control-sm {{ $failedPhoto ? 'is-invalid' : '' }} js-photo" id="{{ $stamp }}_photo" name="{{ $stamp }}_photo"
               accept="image/*" capture="environment" data-has-photo="{{ $savedPhoto ? '1' : '0' }}">
        @if ($failedPhoto)<div class="invalid-feedback">{{ $failedPhoto }}</div>@endif
        <img class="js-photo-preview rounded border mt-1 d-none" alt="" style="width:44px;height:44px;object-fit:cover;">
    @endunless
</div>
