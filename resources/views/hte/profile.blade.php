@extends('layouts.hte')

@section('title', 'My Profile - PSU Intern-Link')
@section('page_title', 'My Profile')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger small">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="il-card">
                <h2 class="h6 fw-bold mb-3">Company Profile</h2>

                <form method="POST" action="{{ route('hte.profile.company') }}">
                    @csrf
                    @method('PUT')

                    <label class="form-label small fw-semibold">HTE name</label>
                    <input
                        type="text"
                        name="name"
                        id="hteName"
                        class="form-control mb-3"
                        value="{{ old('name', $representative->hte->name) }}"
                        required
                    >

                    <label class="form-label small fw-semibold">Address</label>
                    <input
                        type="text"
                        name="address"
                        id="hteAddress"
                        class="form-control mb-3"
                        value="{{ old('address', $representative->hte->address) }}"
                    >

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact person</label>
                            <input
                                type="text"
                                name="contact_person"
                                class="form-control"
                                value="{{ old('contact_person', $representative->hte->contact_person) }}"
                            >
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact email</label>
                            <input
                                type="email"
                                name="contact_email"
                                class="form-control"
                                value="{{ old('contact_email', $representative->hte->contact_email) }}"
                            >
                        </div>
                    </div>

                    <label class="form-label small fw-semibold">Contact phone</label>
                    <input
                        type="text"
                        name="contact_phone"
                        class="form-control mb-4"
                        value="{{ old('contact_phone', $representative->hte->contact_phone) }}"
                    >

                    <div class="border rounded-3 p-3 mb-4" style="background:#f8fafc;">
                        <div class="fw-semibold mb-1">DTR Location Security</div>
                        <div class="small text-secondary mb-3">
                            The official site location used to verify students' clock-in/clock-out distance.
                            Since you're on site, capturing it here is usually the most accurate way to set it.
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="useHteCurrentLocation">
                                <i class="ti ti-current-location"></i> Use my current location
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="openHteGoogleMaps">
                                <i class="ti ti-map-pin"></i> Open in Google Maps
                            </button>
                        </div>

                        <div class="alert alert-info small mb-3">
                            <strong>Free Google Maps method:</strong>
                            If latitude/longitude are already saved below, "Open in Google Maps" jumps straight to
                            that exact pin. If they're empty, it searches by HTE name/address instead — find the
                            exact building there, then <strong>right-click</strong> (desktop) or <strong>press and
                            hold</strong> (mobile) to display its coordinates, copy them into the fields below, and
                            save. No Google Maps API key is required either way.
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">HTE Latitude</label>
                                <input
                                    type="number"
                                    step="0.0000001"
                                    min="-90"
                                    max="90"
                                    name="latitude"
                                    id="hteLatitude"
                                    class="form-control"
                                    value="{{ old('latitude', $representative->hte->latitude) }}"
                                    placeholder="e.g. 16.1234567"
                                >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">HTE Longitude</label>
                                <input
                                    type="number"
                                    step="0.0000001"
                                    min="-180"
                                    max="180"
                                    name="longitude"
                                    id="hteLongitude"
                                    class="form-control"
                                    value="{{ old('longitude', $representative->hte->longitude) }}"
                                    placeholder="e.g. 119.1234567"
                                >
                            </div>
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Allowed geofence radius (meters)</label>
                                <input
                                    type="number"
                                    step="1"
                                    min="10"
                                    max="1000"
                                    name="geofence_radius_m"
                                    class="form-control"
                                    value="{{ old('geofence_radius_m', $representative->hte->geofence_radius_m ?? 100) }}"
                                    required
                                >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Maximum GPS accuracy (meters)</label>
                                <input
                                    type="number"
                                    step="1"
                                    min="5"
                                    max="500"
                                    name="max_gps_accuracy_m"
                                    class="form-control"
                                    value="{{ old('max_gps_accuracy_m', $representative->hte->max_gps_accuracy_m ?? 75) }}"
                                    required
                                >
                            </div>
                        </div>

                        <div id="hteLocationStatus" class="small text-secondary mt-3"></div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save company profile</button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="il-card">
                <h2 class="h6 fw-bold mb-3">My Account</h2>

                <form method="POST" action="{{ route('hte.profile.account') }}">
                    @csrf
                    @method('PUT')

                    <label class="form-label small fw-semibold">Full name</label>
                    <input
                        type="text"
                        name="name"
                        class="form-control mb-3"
                        value="{{ old('name', $representative->user->name) }}"
                        required
                    >

                    <label class="form-label small fw-semibold">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control mb-3"
                        value="{{ old('email', $representative->user->email) }}"
                        required
                    >

                    <label class="form-label small fw-semibold">ID number</label>
                    <input
                        type="text"
                        class="form-control mb-4"
                        value="{{ $representative->user->id_number }}"
                        disabled
                    >
                    <p class="small text-secondary mt-n3 mb-4">
                        Your ID number is set by your OJT Coordinator and can't be changed here.
                    </p>

                    <button type="submit" class="btn btn-primary">Save account details</button>
                </form>
            </div>

            <div class="mt-3">
                @include('partials.profile-photo-card', ['prefix' => 'hte'])
            </div>

            <div class="il-card mt-3">
                <h2 class="h6 fw-bold mb-2">Password</h2>
                <p class="small text-secondary mb-3">
                    Change the password you use to sign in.
                </p>
                <a href="{{ route('password.change') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-lock"></i> Change password
                </a>
            </div>
        </div>
    </div>

<script>
(function () {
    const latInput = document.getElementById('hteLatitude');
    const lonInput = document.getElementById('hteLongitude');
    const addressInput = document.getElementById('hteAddress');
    const nameInput = document.getElementById('hteName');
    const statusBox = document.getElementById('hteLocationStatus');
    const currentButton = document.getElementById('useHteCurrentLocation');
    const mapsButton = document.getElementById('openHteGoogleMaps');

    function setStatus(message, type) {
        statusBox.textContent = message || '';
        statusBox.className = 'small mt-3 ' + (
            type === 'error' ? 'text-danger' :
            type === 'success' ? 'text-success' :
            'text-secondary'
        );
    }

    mapsButton?.addEventListener('click', function () {
        const lat = parseFloat(latInput?.value);
        const lng = parseFloat(lonInput?.value);
        const hasCoords = Number.isFinite(lat) && Number.isFinite(lng);

        if (hasCoords) {
            const url = 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lng;
            window.open(url, '_blank', 'noopener,noreferrer');
            setStatus('Opened the exact saved coordinates (' + lat + ', ' + lng + ') in Google Maps.', 'success');
            return;
        }

        const query = [
            nameInput?.value?.trim(),
            addressInput?.value?.trim(),
            'Philippines'
        ].filter(Boolean).join(', ');

        if (!query) {
            setStatus('Enter the HTE name or address first, then open Google Maps.', 'error');
            return;
        }

        const url = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query);
        window.open(url, '_blank', 'noopener,noreferrer');
        setStatus('No coordinates saved yet, so this searched by name/address instead. Find the exact building, right-click (or press-and-hold) to get its coordinates, then paste them below and save.', 'success');
    });

    currentButton?.addEventListener('click', function () {
        if (!navigator.geolocation) {
            setStatus('This browser does not support device location.', 'error');
            return;
        }

        currentButton.disabled = true;
        currentButton.innerHTML = '<i class="ti ti-loader-2"></i> Capturing…';
        setStatus('Capturing your current location…');

        navigator.geolocation.getCurrentPosition(
            function (position) {
                const lat = Number(position.coords.latitude);
                const lng = Number(position.coords.longitude);
                const accuracy = Number(position.coords.accuracy);

                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    setStatus('The device returned an invalid location.', 'error');
                    return;
                }

                latInput.value = lat.toFixed(7);
                lonInput.value = lng.toFixed(7);
                setStatus(
                    'Current location captured: ' + latInput.value + ', ' + lonInput.value +
                    ' (±' + (Number.isFinite(accuracy) ? Math.round(accuracy) : 'unknown') + ' m). Click Save company profile to store it.',
                    'success'
                );
            },
            function (error) {
                const messages = {
                    1: 'Location permission was denied. Allow location access and try again.',
                    2: 'Your location could not be determined. Make sure Location Services are enabled.',
                    3: 'Location lookup timed out. Move to an open area and try again.'
                };
                setStatus(messages[error.code] || 'Unable to capture your current location.', 'error');
            },
            { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 }
        );

        setTimeout(function () {
            currentButton.disabled = false;
            currentButton.innerHTML = '<i class="ti ti-current-location"></i> Use my current location';
        }, 1000);
    });
})();
</script>
@endsection
