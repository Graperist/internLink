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

{{-- MOA status: the Subject Instructor's list only, not the OJT Coordinator's. --}}
@unless (auth()->user()->isCoordinator())
<label class="form-label small fw-semibold">MOA status</label>
<select name="moa_status" class="form-select mb-4" required>
    @foreach (['pending' => 'Pending', 'active' => 'Active', 'expired' => 'Expired'] as $value => $label)
        <option value="{{ $value }}" @selected(old('moa_status', $hte->moa_status ?? 'pending') === $value)>
            {{ $label }}
        </option>
    @endforeach
</select>
@endunless

