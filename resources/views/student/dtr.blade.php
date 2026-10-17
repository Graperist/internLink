@extends('layouts.student')

@section('title', 'Daily Time Record - PSU Intern-Link')

@section('content')
<div class="il-page-header"><h1 class="il-page-title">Daily time record</h1></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if($flaggedEntries->isNotEmpty())
    @foreach($flaggedEntries as $flagged)
        @if($flagged->log_date?->isToday() || $flagged->log_date?->isFuture())
        <div class="alert alert-danger border-danger shadow-sm">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="fw-bold">DTR entry flagged — {{ $flagged->log_date?->format('F d, Y') }}</div>
                    <div class="small mt-1">HTE: <strong>{{ $flagged->deployment?->hte?->name ?? '—' }}</strong></div>
                    @if($flagged->flagTargetLabel())
                        <div class="small mt-1">Flagged: <strong>{{ $flagged->flagTargetLabel() }}</strong></div>
                    @endif
                    <div class="small mt-1">Reason: <strong>{{ $flagged->flag_reason ?? 'HTE requested correction or clarification.' }}</strong></div>
                    @if($flagged->flag_note)<div class="small mt-1">HTE note: {{ $flagged->flag_note }}</div>@endif
                    @if($flagged->time_out)
                        <div class="small mt-2">Original time-in/time-out and original GPS evidence remain preserved for audit.</div>
                    @else
                        <div class="small mt-2">
                            Your HTE flagged this before you clocked out. Keep working as usual — clocking out will
                            automatically resend this entry for HTE re-verification, no separate action needed here.
                        </div>
                    @endif
                </div>
                @if($flagged->time_out)
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#resolveDtr{{ $flagged->id }}"><i class="ti ti-edit"></i> Resolve Flag / Edit DTR</button>
                @endif
            </div>
        </div>
        @if($flagged->time_out)
        <div class="modal fade" id="resolveDtr{{ $flagged->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Resolve Flag — {{ $flagged->log_date?->format('M d, Y') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST" action="{{ route('student.dtr.resolve', $flagged) }}">@csrf @method('PUT')
                    <div class="modal-body">
                        <div class="alert alert-light border small"><div class="fw-semibold">Original attendance (audit)</div><div>Time In: {{ ($flagged->original_time_in ?: $flagged->time_in) ? \Carbon\Carbon::parse($flagged->original_time_in ?: $flagged->time_in)->format('g:i A') : '—' }}</div><div>Time Out: {{ ($flagged->original_time_out ?: $flagged->time_out) ? \Carbon\Carbon::parse($flagged->original_time_out ?: $flagged->time_out)->format('g:i A') : '—' }}</div></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small fw-semibold">Correct Time In</label><input type="time" name="time_in" class="form-control" value="{{ $flagged->time_in ? \Carbon\Carbon::parse($flagged->time_in)->format('H:i') : '' }}" required></div>
                            <div class="col-6"><label class="form-label small fw-semibold">Correct Time Out</label><input type="time" name="time_out" class="form-control" value="{{ $flagged->time_out ? \Carbon\Carbon::parse($flagged->time_out)->format('H:i') : '' }}" required></div>
                        </div>
                        <label class="form-label small fw-semibold mt-3">Your explanation / clarification</label>
                        <textarea name="notes" class="form-control" rows="4" maxlength="1000" placeholder="Explain the correction or provide clarification..."></textarea>
                        <div class="small text-secondary mt-3">This edits the same DTR. It does not create a new clock-in/out. The HTE will re-verify it after submission.</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Submit Correction</button></div>
                </form>
            </div></div>
        </div>
        @endif
        @endif
    @endforeach
@endif

@if(!$deployment || $deployment->status !== 'active')
    @if ($deployment?->status === 'pending')
        @include('student._deployment-blockers', ['internship' => $deployment->internship])
    @endif
    <div class="il-card"><p class="text-secondary mb-0">You don't have an active deployment yet, so there's nothing to log. Check <a href="{{ route('student.hte') }}">My HTE Info</a> once your subject instructor starts your deployment.</p></div>
@else
<div class="il-card">
    <h2 class="h6 fw-bold mb-3">Log Today's Attendance</h2>
    <div class="text-center py-3 mb-3" style="background:#f4f6f9;border-radius:10px;"><div class="fw-bold js-live-clock" id="liveClockTime" style="font-size:1.75rem;color:var(--il-navy);" data-server-time="{{ now()->toIso8601String() }}">{{ now()->format('g:i:s A') }}</div><div class="text-secondary small" id="liveClockDate">{{ now()->format('F d, Y l') }}</div></div>
    <div class="small text-secondary mb-1 fw-semibold">HOST TRAINING ESTABLISHMENT</div>
    <div class="mb-3 p-2" style="background:#f4f6f9;border-radius:8px;">{{ $deployment->hte->name }}</div>

    @if($deployment->hte->latitude === null || $deployment->hte->longitude === null)
        <div class="alert alert-warning small">Your HTE's official location is not configured yet, so DTR clock-in is temporarily disabled.</div>
    @else
        <div class="alert alert-light border small"><strong>Location security:</strong> the server checks a fresh GPS reading against the HTE's {{ $deployment->hte->geofence_radius_m ?? 100 }} m geofence. GPS readings above the normal accuracy threshold of {{ number_format($deployment->hte->max_gps_accuracy_m ?? 75, 0) }} m can still be recorded when they are inside the geofence, but they require HTE verification. If the captured point is outside the geofence, the attendance is still recorded but clearly marked for HTE review and you are informed. VPN/IP does not replace GPS verification.</div>
    @endif

    @if(!$todaysDtr || !$todaysDtr->time_in)
        <form method="POST" action="{{ route('student.dtr.clock-in') }}" class="js-gps-form" data-action="Clock In" data-hte-latitude="{{ (float) $deployment->hte->latitude }}" data-hte-longitude="{{ (float) $deployment->hte->longitude }}" data-geofence-radius="{{ (float) ($deployment->hte->geofence_radius_m ?? 100) }}" data-hte-name="{{ e($deployment->hte->name) }}">@csrf
            <input type="hidden" name="latitude"><input type="hidden" name="longitude"><input type="hidden" name="accuracy"><input type="hidden" name="captured_at"><input type="hidden" name="confirm_outside_geofence" value="0">
            <div class="d-flex align-items-start gap-2 p-2 mb-3" style="background:#eef6fd;border-radius:8px;"><i class="ti ti-map-pin-check mt-1" style="color:var(--il-navy);"></i><div class="small text-secondary">A fresh high-accuracy location is captured and the server independently calculates your distance from the HTE.</div></div>
            <div class="small text-danger js-gps-error mb-2 d-none"></div>
            <button type="submit" class="btn btn-primary w-100 js-gps-submit" @disabled($deployment->hte->latitude === null || $deployment->hte->longitude === null)>Clock In</button>
        </form>
    @elseif(!$todaysDtr->time_out)
        @php
            $clockInFlags = $todaysDtr->clock_in_location_flags;
            if (is_string($clockInFlags)) {
                $clockInFlags = json_decode($clockInFlags, true) ?: [];
            }
            $clockInOutside = is_array($clockInFlags) && in_array('outside_geofence', $clockInFlags, true);
        @endphp
        @if($clockInOutside)
            <div class="alert alert-warning border-warning small">
                <div class="fw-bold"><i class="ti ti-map-pin-off"></i> You are outside your designated HTE location.</div>
                <div class="mt-1">Your clock-in was recorded, but the captured GPS position is outside the registered HTE geofence. The DTR will require HTE review.</div>
            </div>
        @endif
        <div class="row g-2 mb-3"><div class="col-6"><div class="small text-secondary fw-semibold">TIME IN</div><div class="fw-bold">{{ \Carbon\Carbon::parse($todaysDtr->time_in)->format('g:i A') }}</div></div><div class="col-6"><div class="small text-secondary fw-semibold">TIME OUT</div><div class="fw-bold text-secondary">--:-- --</div></div></div>
        <form method="POST" action="{{ route('student.dtr.clock-out') }}" class="js-gps-form" data-action="Clock Out" data-hte-latitude="{{ (float) $deployment->hte->latitude }}" data-hte-longitude="{{ (float) $deployment->hte->longitude }}" data-geofence-radius="{{ (float) ($deployment->hte->geofence_radius_m ?? 100) }}" data-hte-name="{{ e($deployment->hte->name) }}">@csrf
            <input type="hidden" name="latitude"><input type="hidden" name="longitude"><input type="hidden" name="accuracy"><input type="hidden" name="captured_at"><input type="hidden" name="confirm_outside_geofence" value="0">
            <div class="d-flex align-items-start gap-2 p-2 mb-3" style="background:#eef6fd;border-radius:8px;"><i class="ti ti-map-pin-check mt-1" style="color:var(--il-navy);"></i><div class="small text-secondary">A fresh GPS capture is required again at clock-out.</div></div>
            <div class="small text-danger js-gps-error mb-2 d-none"></div><p class="text-secondary small text-center mb-2">Your DTR will be sent to your HTE Supervisor for verification before it is finalized.</p><button type="submit" class="btn btn-primary w-100 js-gps-submit">Clock Out</button>
        </form>
    @else
        @php
            $locationFlags=$todaysDtr->locationFlagLabels();
            $allLocationFlags = [];
            foreach (['clock_in_location_flags', 'clock_out_location_flags'] as $flagField) {
                $flags = $todaysDtr->{$flagField};
                if (is_string($flags)) {
                    $flags = json_decode($flags, true) ?: [];
                }
                if (is_array($flags)) {
                    $allLocationFlags = array_merge($allLocationFlags, $flags);
                }
            }
            $outsideHte = in_array('outside_geofence', $allLocationFlags, true);
        @endphp
        @if($outsideHte)
            <div class="alert alert-warning border-warning small">
                <div class="fw-bold"><i class="ti ti-map-pin-off"></i> Location notice: outside designated HTE</div>
                <div class="mt-1">At least one GPS capture for today was outside your registered HTE geofence. Your attendance remains recorded, but the DTR requires HTE verification.</div>
            </div>
        @endif
        <div class="row g-2 mb-3"><div class="col-6"><div class="small text-secondary fw-semibold">TIME IN</div><div class="fw-bold">{{ \Carbon\Carbon::parse($todaysDtr->time_in)->format('g:i A') }}</div></div><div class="col-6"><div class="small text-secondary fw-semibold">TIME OUT</div><div class="fw-bold">{{ \Carbon\Carbon::parse($todaysDtr->time_out)->format('g:i A') }}</div></div></div>
        <div class="alert {{ $todaysDtr->status==='flagged'?'alert-danger':'alert-light border' }} small mb-3">{{ number_format($todaysDtr->hours_logged,2) }} hours logged today — @if($todaysDtr->status==='flagged')<span class="badge rounded-pill text-bg-danger">Flagged</span>@elseif($todaysDtr->status==='approved')<span class="badge rounded-pill text-bg-success">Verified</span>@else<span class="badge rounded-pill text-bg-warning">Pending HTE verification</span>@endif</div>
        <div class="row g-2 small">
            <div class="col-md-6"><div class="fw-semibold">Clock-in location</div>@if($todaysDtr->clock_in_distance_m!==null)<div>{{ number_format($todaysDtr->clock_in_distance_m,1) }} m from HTE · ±{{ number_format($todaysDtr->clock_in_accuracy??0,0) }} m accuracy</div><div>Status: <strong>{{ $todaysDtr->clock_in_location_status==='verified'?'Verified':'Needs HTE review' }}</strong></div>@else<div class="text-secondary">No server-side location evaluation.</div>@endif</div>
            <div class="col-md-6"><div class="fw-semibold">Clock-out location</div>@if($todaysDtr->clock_out_distance_m!==null)<div>{{ number_format($todaysDtr->clock_out_distance_m,1) }} m from HTE · ±{{ number_format($todaysDtr->clock_out_accuracy??0,0) }} m accuracy</div><div>Status: <strong>{{ $todaysDtr->clock_out_location_status==='verified'?'Verified':'Needs HTE review' }}</strong></div>@else<div class="text-secondary">No server-side location evaluation.</div>@endif</div>
        </div>
        @if(count($locationFlags))<div class="alert alert-warning small mt-3 mb-0"><div class="fw-semibold mb-1">Location review flags</div>@foreach($locationFlags as $flag)<div>• {{ $flag }}</div>@endforeach</div>@endif
    @endif
</div>
@endif

@if($dtrHistory->isNotEmpty())
<div class="il-card mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 class="h6 fw-bold mb-0">Time In / Out History</h2>
        <span class="text-secondary small">Original values remain visible after any HTE-approved correction</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dtrHistory as $entry)
                    <tr>
                        <td>{{ $entry->log_date?->format('M d, Y') }}</td>
                        <td>
                            {{ $entry->time_in ? \Carbon\Carbon::parse($entry->time_in)->format('g:i A') : '—' }}
                            @if($entry->original_time_in)
                                <div class="small text-danger">Original: {{ \Carbon\Carbon::parse($entry->original_time_in)->format('g:i A') }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $entry->time_out ? \Carbon\Carbon::parse($entry->time_out)->format('g:i A') : '—' }}
                            @if($entry->original_time_out)
                                <div class="small text-danger">Original: {{ \Carbon\Carbon::parse($entry->original_time_out)->format('g:i A') }}</div>
                            @endif
                        </td>
                        <td>{{ $entry->hours_logged !== null ? number_format($entry->hours_logged, 1).'h' : '—' }}</td>
                        <td>
                            @if($entry->status === 'approved')
                                <span class="badge text-bg-success">Verified</span>
                            @elseif($entry->status === 'flagged')
                                <span class="badge text-bg-danger">Flagged</span>
                            @elseif(!$entry->time_in || !$entry->time_out)
                                <span class="badge text-bg-secondary">Incomplete</span>
                            @else
                                <span class="badge text-bg-warning">Pending</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

<style>
    .il-location-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.58);
        backdrop-filter: blur(4px);
    }
    .il-location-modal-backdrop.is-open { display: flex; }
    .il-location-modal {
        width: min(100%, 520px);
        border-radius: 18px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
        transform: translateY(10px) scale(.98);
        opacity: 0;
        transition: transform .18s ease, opacity .18s ease;
    }
    .il-location-modal-backdrop.is-open .il-location-modal {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    .il-location-modal-header {
        display: flex;
        align-items: center;
        gap: .8rem;
        padding: 1.1rem 1.2rem;
        border-bottom: 1px solid #e9edf3;
    }
    .il-location-modal-icon {
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #fff4db;
        color: #b66a00;
        font-size: 1.3rem;
    }
    .il-location-modal-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: #132238;
    }
    .il-location-modal-subtitle {
        margin: .15rem 0 0;
        font-size: .83rem;
        color: #6b7785;
    }
    .il-location-modal-body { padding: 1.15rem 1.2rem .9rem; }
    .il-location-modal-alert {
        border: 1px solid #f5d58b;
        background: #fffaf0;
        border-radius: 12px;
        padding: .9rem 1rem;
        margin-bottom: 1rem;
        color: #6c4a12;
    }
    .il-location-modal-alert strong {
        display: block;
        color: #7a4a00;
        margin-bottom: .25rem;
    }
    .il-location-stat-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .il-location-stat {
        border: 1px solid #e7ebf0;
        border-radius: 12px;
        padding: .8rem .9rem;
        background: #f8fafc;
    }
    .il-location-stat-label {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #778394;
        font-weight: 700;
    }
    .il-location-stat-value {
        margin-top: .15rem;
        font-size: 1rem;
        font-weight: 700;
        color: #132238;
    }
    .il-location-modal-note {
        font-size: .85rem;
        line-height: 1.5;
        color: #5e6b79;
        margin: 0;
    }
    .il-location-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: .65rem;
        padding: .95rem 1.2rem 1.2rem;
    }
    .il-location-modal-footer button {
        min-width: 110px;
        border-radius: 10px;
        padding: .65rem 1rem;
        font-weight: 600;
    }
    @media (max-width: 520px) {
        .il-location-stat-grid { grid-template-columns: 1fr; }
        .il-location-modal-footer { flex-direction: column-reverse; }
        .il-location-modal-footer button { width: 100%; }
    }
</style>

<div class="il-location-modal-backdrop" id="outsideHteModal" aria-hidden="true">
    <div class="il-location-modal" role="dialog" aria-modal="true" aria-labelledby="outsideHteModalTitle">
        <div class="il-location-modal-header">
            <div class="il-location-modal-icon"><i class="ti ti-map-pin-off"></i></div>
            <div>
                <h3 class="il-location-modal-title" id="outsideHteModalTitle">Outside HTE Location</h3>
                <p class="il-location-modal-subtitle">Please confirm before continuing.</p>
            </div>
        </div>
        <div class="il-location-modal-body">
            <div class="il-location-modal-alert">
                <strong>You are outside your designated HTE.</strong>
                <span>Your current GPS position is outside the registered geofence for <span id="outsideHteName">your HTE</span>.</span>
            </div>
            <div class="il-location-stat-grid">
                <div class="il-location-stat">
                    <div class="il-location-stat-label">Current distance</div>
                    <div class="il-location-stat-value" id="outsideHteDistance">—</div>
                </div>
                <div class="il-location-stat">
                    <div class="il-location-stat-label">Allowed radius</div>
                    <div class="il-location-stat-value" id="outsideHteRadius">—</div>
                </div>
            </div>
            <p class="il-location-modal-note">
                Are you sure you want to continue? Your attendance can still be recorded, but this location will be
                clearly marked and sent to your HTE Supervisor for verification. The HTE may verify or flag the DTR.
            </p>
        </div>
        <div class="il-location-modal-footer">
            <button type="button" class="btn btn-light border" id="outsideHteCancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="outsideHteContinue">Continue</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    // Live-updating clock, anchored to the server's time (captured on page load)
    // rather than the visitor's device clock, so it stays in sync with what the
    // server will actually record on clock-in/clock-out.
    const clockTimeEl = document.getElementById('liveClockTime');
    const clockDateEl = document.getElementById('liveClockDate');
    if (clockTimeEl) {
        const serverTime = new Date(clockTimeEl.dataset.serverTime);
        const clientLoadTime = Date.now();
        const isValidServerTime = !Number.isNaN(serverTime.getTime());

        const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

        const tick = () => {
            const now = isValidServerTime
                ? new Date(serverTime.getTime() + (Date.now() - clientLoadTime))
                : new Date();

            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const meridiem = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            if (hours === 0) hours = 12;

            clockTimeEl.textContent = `${hours}:${minutes}:${seconds} ${meridiem}`;
            if (clockDateEl) {
                clockDateEl.textContent = `${monthNames[now.getMonth()]} ${String(now.getDate()).padStart(2, '0')}, ${now.getFullYear()} ${dayNames[now.getDay()]}`;
            }
        };

        tick();
        setInterval(tick, 1000);
    }

    const calculateDistanceMeters = (lat1, lon1, lat2, lon2) => {
        const toRadians = (degrees) => degrees * Math.PI / 180;
        const earthRadius = 6371000;
        const dLat = toRadians(lat2 - lat1);
        const dLon = toRadians(lon2 - lon1);
        const a =
            Math.sin(dLat / 2) ** 2 +
            Math.cos(toRadians(lat1)) *
            Math.cos(toRadians(lat2)) *
            Math.sin(dLon / 2) ** 2;

        return earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    const showOutsideHteModal = ({form, button, errorBox, confirmField, distanceMeters, geofenceRadius, hteName}) => {
        const backdrop = document.getElementById('outsideHteModal');
        const nameEl = document.getElementById('outsideHteName');
        const distanceEl = document.getElementById('outsideHteDistance');
        const radiusEl = document.getElementById('outsideHteRadius');
        const cancelButton = document.getElementById('outsideHteCancel');
        const continueButton = document.getElementById('outsideHteContinue');

        if (!backdrop || !cancelButton || !continueButton) {
            const confirmed = window.confirm(
                `You are outside your designated HTE (${hteName}).\n\n` +
                `Current distance: ${distanceMeters.toFixed(1)} m\n` +
                `Allowed radius: ${geofenceRadius.toFixed(0)} m\n\n` +
                `Are you sure you want to continue?`
            );
            if (confirmed) {
                if (confirmField) confirmField.value = '1';
                button.textContent = 'Submitting…';
                HTMLFormElement.prototype.submit.call(form);
                return;
            }
            button.disabled = false;
            button.textContent = form.dataset.action;
            errorBox.textContent = 'Clock-in/out cancelled. Please move to your designated HTE and try again.';
            errorBox.classList.remove('d-none');
            return;
        }

        nameEl.textContent = hteName;
        distanceEl.textContent = `${distanceMeters.toFixed(1)} m`;
        radiusEl.textContent = `${geofenceRadius.toFixed(0)} m`;
        backdrop.classList.add('is-open');
        backdrop.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        const closeModal = (submitted = false) => {
            backdrop.classList.remove('is-open');
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            cancelButton.onclick = null;
            continueButton.onclick = null;
            backdrop.onclick = null;
            if (!submitted) {
                button.disabled = false;
                button.textContent = form.dataset.action;
                errorBox.textContent = 'Clock-in/out cancelled. Please move to your designated HTE and try again.';
                errorBox.classList.remove('d-none');
            }
        };

        cancelButton.onclick = () => closeModal(false);
        continueButton.onclick = () => {
            if (confirmField) confirmField.value = '1';
            closeModal(true);
            button.textContent = 'Submitting…';
            HTMLFormElement.prototype.submit.call(form);
        };
        backdrop.onclick = (event) => {
            if (event.target === backdrop) closeModal(false);
        };
    };

    const startGpsCapture = (form) => {
        const button = form.querySelector('.js-gps-submit');
        const errorBox = form.querySelector('.js-gps-error');
        const latitude = form.querySelector('input[name="latitude"]');
        const longitude = form.querySelector('input[name="longitude"]');
        const accuracy = form.querySelector('input[name="accuracy"]');
        const capturedAt = form.querySelector('input[name="captured_at"]');
        const confirmField = form.querySelector('input[name="confirm_outside_geofence"]');

        if (!button || !errorBox || !latitude || !longitude || !accuracy || !capturedAt) {
            console.error('DTR GPS fields are missing from the form.');
            return;
        }

        if (!navigator.geolocation) {
            errorBox.textContent = 'This browser does not support device location.';
            errorBox.classList.remove('d-none');
            return;
        }

        errorBox.textContent = '';
        errorBox.classList.add('d-none');
        button.disabled = true;
        button.textContent = 'Getting high-accuracy location…';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = Number(position.coords.latitude);
                const lon = Number(position.coords.longitude);
                const acc = Number(position.coords.accuracy);
                const timestamp = Number(position.timestamp);

                if (!Number.isFinite(lat) || !Number.isFinite(lon) || !Number.isFinite(acc) || !Number.isFinite(timestamp)) {
                    button.disabled = false;
                    button.textContent = form.dataset.action;
                    errorBox.textContent = 'The device returned an invalid GPS reading. Please try again.';
                    errorBox.classList.remove('d-none');
                    return;
                }

                latitude.value = lat.toFixed(7);
                longitude.value = lon.toFixed(7);
                accuracy.value = acc.toFixed(2);
                capturedAt.value = String(timestamp);

                // The server remains the final source of truth, but the
                // student should be warned before submitting an attendance
                // record whose captured point is outside the designated HTE.
                const hteLat = Number(form.dataset.hteLatitude);
                const hteLon = Number(form.dataset.hteLongitude);
                const geofenceRadius = Number(form.dataset.geofenceRadius) || 100;
                const hteName = form.dataset.hteName || 'your designated HTE';

                if (Number.isFinite(hteLat) && Number.isFinite(hteLon)) {
                    const distanceMeters = calculateDistanceMeters(lat, lon, hteLat, hteLon);
                    if (distanceMeters > geofenceRadius) {
                        showOutsideHteModal({
                            form,
                            button,
                            errorBox,
                            confirmField,
                            distanceMeters,
                            geofenceRadius,
                            hteName
                        });
                        return;
                    }

                    // Submit only after all four GPS fields have been populated
                    // and, when necessary, the student confirms the outside-HTE warning.
                    button.textContent = 'Submitting…';
                    HTMLFormElement.prototype.submit.call(form);
                }
            },
            (error) => {
                button.disabled = false;
                button.textContent = form.dataset.action;
                errorBox.classList.remove('d-none');

                const messages = {
                    1: 'Location permission was denied. Please allow location access in your browser and try again.',
                    2: 'Your current location could not be determined. Make sure Location Services are enabled.',
                    3: 'Location lookup timed out. Move to an open area and try again.'
                };

                errorBox.textContent = messages[error.code] || 'Unable to capture your location. Please try again.';
                console.error('DTR GPS error:', error);
            },
            {
                enableHighAccuracy: true,
                timeout: 30000,
                maximumAge: 0
            }
        );
    };

    document.querySelectorAll('.js-gps-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            startGpsCapture(form);
        });
    });
})();
</script>
@endpush
