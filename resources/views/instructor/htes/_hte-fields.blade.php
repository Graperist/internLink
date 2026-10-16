@php $hte = $hte ?? null; @endphp

<label class="form-label small fw-semibold">Name of agency / company <span class="text-danger">*</span></label>
<input
    type="text"
    name="name"
    id="hteName"
    class="form-control mb-3"
    value="{{ old('name', $hte->name ?? '') }}"
    required
>

<label class="form-label small fw-semibold">Address <span class="text-danger">*</span></label>
<input
    type="text"
    name="address"
    id="hteAddress"
    class="form-control mb-3"
    value="{{ old('address', $hte->address ?? '') }}"
    required
>

<div class="row g-2 mb-3">
    <div class="col-md-6">
        <label class="form-label small fw-semibold">Contact person <span class="text-danger">*</span></label>
        <input
            type="text"
            name="contact_person"
            id="hte_contact_person"
            class="form-control"
            value="{{ old('contact_person', $hte->contact_person ?? '') }}"
            placeholder="e.g. Mr. Ramon Cruz"
            required
        >
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">Representative position <span class="text-danger">*</span></label>
        <input
            type="text"
            name="representative_position"
            id="hte_representative_position"
            class="form-control"
            value="{{ old('representative_position', $hte->representative_position ?? '') }}"
            placeholder="e.g. Mayor, HR Officer, IT Manager"
            required
        >
    </div>
</div>

<div class="mb-3">
    <label class="form-label small fw-semibold">Contact email <span class="text-secondary fw-normal">(optional)</span></label>
    <input
        type="email"
        name="contact_email"
            id="hte_contact_email"
        class="form-control"
        value="{{ old('contact_email', $hte->contact_email ?? '') }}"
    >
</div>

<label class="form-label small fw-semibold">Contact number <span class="text-danger">*</span></label>
<input
    type="text"
    name="contact_phone"
            id="hte_contact_phone"
    class="form-control mb-3"
    value="{{ old('contact_phone', $hte->contact_phone ?? '') }}"
    placeholder="e.g. 0917 123 4567"
    required
>

<label class="form-label small fw-semibold">MOA status</label>
<select name="moa_status" class="form-select mb-4" required>
    @foreach (['pending' => 'Pending', 'active' => 'Active', 'expired' => 'Expired'] as $value => $label)
        <option value="{{ $value }}" @selected(old('moa_status', $hte->moa_status ?? 'pending') === $value)>
            {{ $label }}
        </option>
    @endforeach
</select>

<div class="border rounded-3 p-3 mb-4" style="background:#f8fafc;">
    <div class="fw-semibold mb-1">DTR Location Security <span class="text-secondary fw-normal small">(can be set later, before interns clock in)</span></div>
    <div class="small text-secondary mb-3">
        Set the official HTE location used for attendance verification.
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
        Open Google Maps, find the HTE company, then on the exact building/location
        <strong>right-click</strong> (desktop) or <strong>press and hold</strong> (mobile) to display the coordinates.
        Copy the latitude and longitude and paste them into the fields below.
        No Google Maps API key is required for this method.
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
                value="{{ old('latitude', $hte->latitude ?? '') }}"
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
                value="{{ old('longitude', $hte->longitude ?? '') }}"
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
                value="{{ old('geofence_radius_m', $hte->geofence_radius_m ?? 100) }}"
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
                value="{{ old('max_gps_accuracy_m', $hte->max_gps_accuracy_m ?? 75) }}"
                required
            >
        </div>
    </div>

    <div id="hteLocationStatus" class="small text-secondary mt-3"></div>
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
        const lon = parseFloat(lonInput?.value);

        // If exact coordinates are already saved, open that precise pin
        // instead of a name/address text search - a search can land on the
        // wrong branch or a nearby listing, while the saved coordinates are
        // exactly where the pin was placed.
        if (Number.isFinite(lat) && Number.isFinite(lon)) {
            const url = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(lat + ',' + lon);
            window.open(url, '_blank', 'noopener,noreferrer');
            setStatus('Opened the exact saved location in a new tab.', 'success');
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
        setStatus('Google Maps opened in a new tab. Copy the exact location coordinates and paste them below.', 'success');
    });

    currentButton?.addEventListener('click', function () {
        if (!navigator.geolocation) {
            setStatus('This browser does not support device location.', 'error');
            return;
        }

        currentButton.disabled = true;
        currentButton.innerHTML = '<i class="ti ti-loader-2"></i> Capturing…';
        setStatus('Capturing the coordinator device location…');

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
                    ' (±' + (Number.isFinite(accuracy) ? Math.round(accuracy) : 'unknown') + ' m). Click Save changes to store it.',
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
