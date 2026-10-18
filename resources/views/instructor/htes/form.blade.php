@extends('layouts.instructor')

@php $editing = $listing->exists; @endphp

@section('title', ($editing ? 'Edit Partner Agency' : 'Add Partner Agency').' - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">{{ $editing ? 'Edit partner agency' : 'Add partner agency' }}</h1>
    <a href="{{ route('instructor.htes', ['academic_year' => $listing->academic_year, 'semester' => $listing->semester]) }}" class="il-btn-secondary text-decoration-none">
        <i class="ti ti-arrow-left"></i> Back to partner agencies
    </a>
</div>

<div class="il-card" style="max-width: 720px;">
    @if ($errors->any())
        <div class="alert alert-danger small">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-light border small mb-4">
        @if ($editing)
            Partner agency of <strong>{{ $listing->program->code }}</strong> for
            <strong>{{ $listing->semester }}, AY {{ $listing->academic_year }}</strong>.
            Changes to the agency's details are shared with every program that lists it.
        @else
            Added to <strong>your program's {{ $listing->semester }}, AY {{ $listing->academic_year }} list</strong>
            ({{ $instructor->program->code }}). Use this when the agencies already on the list don't have enough slots:
            enter the new partner's details and the number of slots it gave. Only the agency name and number of student-trainees
            are printed on the Masterlist of Partner Industries (FM-AA-INT-06).
        @endif
    </div>

    <form method="POST" action="{{ $editing ? route('instructor.htes.update', $listing) : route('instructor.htes.store') }}" id="partnerForm">
        @csrf
        @if ($editing) @method('PUT') @endif

        @unless ($editing)
            <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">Term</p>
            <div class="row g-2 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="academic_year">Academic year</label>
                    @include('partials.academic-year-input', ['name' => 'academic_year', 'id' => 'academic_year', 'value' => old('academic_year', $listing->academic_year), 'required' => true, 'readonly' => $listing->exists])
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="semester">Semester</label>
                    <select name="semester" id="semester" class="form-select" required>
                        @foreach ($semesters as $option)
                            <option value="{{ $option }}" @selected(old('semester', $listing->semester) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($existing->isNotEmpty())
                <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">Agency</p>
                <label class="form-label small fw-semibold" for="existing_hte_id">Already on the campus list?</label>
                <select name="existing_hte_id" id="existing_hte_id" class="form-select mb-1">
                    <option value="">— New agency (type the details below) —</option>
                    @foreach ($existing as $option)
                        <option value="{{ $option->id }}" @selected((string) old('existing_hte_id') === (string) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
                <p class="il-form-hint mb-4">Picking one fills in its saved details; check and update them before saving.</p>
            @endif
        @endunless

        <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">Agency details</p>
        @include('instructor.htes._hte-fields', ['hte' => $hte])

        <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">For this term</p>
        <div class="row g-2 mb-4">
            <div class="col-md-4">
                <label class="form-label small fw-semibold" for="slots">Slots given by the HTE <span class="text-danger">*</span></label>
                <input type="number" min="1" max="500" name="slots" id="slots" class="form-control"
                       value="{{ old('slots', $listing->slots) }}" placeholder="e.g. 5" required>
                <p class="il-form-hint mb-0">How many of your interns it will take this term.</p>
            </div>
            <div class="col-md-8">
                <label class="form-label small fw-semibold" for="remarks">Remarks <span class="text-secondary fw-normal">(optional)</span></label>
                <input type="text" name="remarks" id="remarks" class="form-control" maxlength="500"
                       value="{{ old('remarks', $listing->remarks) }}" placeholder="e.g. Contacted 12 Dec - accepting BSIT interns">
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="il-btn-primary">{{ $editing ? 'Save changes' : 'Add partner agency' }}</button>
            <a href="{{ route('instructor.htes', ['academic_year' => $listing->academic_year, 'semester' => $listing->semester]) }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>

@unless ($editing)
    @php
        $directory = $existing->mapWithKeys(fn ($h) => [$h->id => [
            'name' => $h->name,
            'address' => $h->address,
            'contact_person' => $h->contact_person,
            'representative_position' => $h->representative_position,
            'contact_phone' => $h->contact_phone,
            'contact_email' => $h->contact_email,
            'moa_status' => $h->moa_status,
        ]]);
    @endphp
    <script>
    (function () {
        const directory = @json($directory);
        const picker = document.getElementById('existing_hte_id');
        const form = document.getElementById('partnerForm');
        if (!picker) return;
        picker.addEventListener('change', function () {
            const info = directory[picker.value];
            if (!info) return;
            Object.keys(info).forEach(function (key) {
                const field = form.querySelector('[name="' + key + '"]');
                if (field && info[key] !== null) field.value = info[key];
            });
        });
    })();
    </script>
@endunless
@endsection
