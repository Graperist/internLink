{{--
    Mail icon: email an HTE account its sign-in details. Enabled only once
    every intern at the agency has finished (App\Support\HteAccounts).
    $representative (HteRepresentative with user + hte); optional $btnClass.
--}}
@php
    $btnClass ??= 'il-icon-btn';
    $ready = $representative->hte ? \App\Support\HteAccounts::readiness($representative->hte) : ['ready' => false, 'reason' => 'No agency linked.'];
    $sentBefore = $representative->credentials_sent_at;
@endphp
@if ($ready['ready'])
    <form method="POST" action="{{ route('instructor.hte-accounts.send', $representative) }}" class="d-inline">
        @csrf
        <button type="submit" class="{{ $btnClass }}" title="{{ $sentBefore ? 'Send the sign-in details again' : 'Email the sign-in details' }} to {{ $representative->user->email }}"
                onclick="return confirm('Email the sign-in details of {{ addslashes($representative->hte->name) }} to {{ addslashes($representative->user->email) }}? A new password is made and sent{{ $sentBefore ? ' (the one sent before stops working)' : '' }}.')">
            <i class="ti {{ $sentBefore ? 'ti-mail-forward' : 'ti-mail' }}"></i><span class="visually-hidden">Email the sign-in details</span>
        </button>
    </form>
@else
    <span class="d-inline-block" tabindex="0" title="{{ $ready['reason'] }} The sign-in details can be sent once every intern there has finished.">
        <button type="button" class="{{ $btnClass }}" disabled style="opacity: .4; pointer-events: none;" aria-label="Email the sign-in details (not yet: {{ $ready['reason'] }})">
            <i class="ti ti-mail"></i>
        </button>
    </span>
@endif
