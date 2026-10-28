{{--
    Thumbnails of the photos taken at the HTE for one half of a day:
    $dtr, $part ('am' or 'pm'). Each opens the full photo; the time under it
    is when the student saved it.
--}}
@php $shots = collect(['_in' => 'In', '_out' => 'Out'])->filter(fn ($l, $s) => $dtr->photo($part.$s)); @endphp
@if ($shots->isNotEmpty())
    <div class="d-flex gap-2 mt-1">
        @foreach ($shots as $suffix => $shotLabel)
            @php $shot = $dtr->photo($part.$suffix); @endphp
            <a href="{{ $dtr->photoUrl($part.$suffix) }}" target="_blank" class="text-decoration-none text-center" title="Photo at time {{ strtolower($shotLabel) }} · saved {{ \Carbon\Carbon::parse($shot['at'])->format('M d, g:i A') }}">
                <img src="{{ $dtr->photoUrl($part.$suffix) }}" alt="Photo at time {{ strtolower($shotLabel) }}" class="rounded border d-block" style="width:52px;height:52px;object-fit:cover;">
                <span class="text-secondary d-block" style="font-size:.66rem;">{{ $shotLabel }} · {{ \Carbon\Carbon::parse($shot['at'])->format('g:i A') }}</span>
            </a>
        @endforeach
    </div>
@elseif ($dtr->period($part) && $dtr->status === 'pending')
    <div class="text-secondary mt-1" style="font-size:.7rem;"><i class="ti ti-camera-off"></i> No photo</div>
@endif
