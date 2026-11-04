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
            @if ($instructor->isCoordinator())
                Added to the <strong>{{ $listing->semester }}, AY <span data-term-year>{{ old('academic_year', $listing->academic_year) }}</span> list</strong> of the program you choose below.
            @else
                Added to <strong>your program's {{ $listing->semester }}, AY <span data-term-year>{{ old('academic_year', $listing->academic_year) }}</span> list</strong>
                ({{ $instructor->program->code }}).
            @endif
            Use this when the agencies already on the list don't have enough slots:
            enter the new partner's details and the number of slots it gave. Only the agency name and number of student-trainees
            are printed on the Masterlist of Partner Industries (FM-AA-INT-06).
        @endif
    </div>

    @unless ($editing)
        <script>
        // The note above follows the academic year typed below.
        document.addEventListener('DOMContentLoaded', function () {
            var input = document.getElementById('academic_year');
            if (!input) return;
            var show = function () {
                var v = (input.value || '').trim();
                if (/^\d{4}$/.test(v)) v = v + '-' + (parseInt(v, 10) + 1);
                document.querySelectorAll('[data-term-year]').forEach(function (el) { el.textContent = v || '____'; });
            };
            input.addEventListener('input', show);
            input.addEventListener('change', show);
            show();
        });
        </script>
    @endunless

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
                    {{-- The internship is only in the 2nd Semester. --}}
                    <input type="text" id="semester" class="form-control" value="{{ \App\Support\Term::SEMESTER }}" readonly>
                    <input type="hidden" name="semester" value="{{ \App\Support\Term::SEMESTER }}">
                </div>
                @if ($instructor->isCoordinator())
                    <div class="col-12">
                        <label class="form-label small fw-semibold" for="program_id">Program</label>
                        <select name="program_id" id="program_id" class="form-select @error('program_id') is-invalid @enderror" required>
                            <option value="">Choose a program</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" @selected((int) old('program_id', $selectedProgram) === $program->id)>{{ $program->code }} &ndash; {{ $program->name }}</option>
                            @endforeach
                        </select>
                        @error('program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endif
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

        @unless ($editing)
            <div id="accountSection">
                <p class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">HTE portal account</p>
                <p class="small fw-semibold mb-1">Do you also want to create an account for this partner agency?</p>
                <div class="d-flex gap-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="create_account" id="create_account_no" value="0" @checked(old('create_account', '0') !== '1')>
                        <label class="form-check-label" for="create_account_no">No, not now</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="create_account" id="create_account_yes" value="1" @checked(old('create_account') === '1')>
                        <label class="form-check-label" for="create_account_yes">Yes, create the account</label>
                    </div>
                </div>
                <div id="accountFields" class="p-3 rounded mb-2" style="background:#f4f6f9;" @if (old('create_account') !== '1') hidden @endif>
                    <label class="form-label small fw-semibold" for="account_id_number">Account ID number <span class="text-danger">*</span></label>
                    <input type="text" name="account_id_number" id="account_id_number" class="form-control @error('account_id_number') is-invalid @enderror" maxlength="100"
                           value="{{ old('account_id_number', $suggestedAccountId ?? '') }}" placeholder="e.g. ACC-HTE-001">
                    @error('account_id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <p class="il-form-hint mb-0 mt-1">
                        The account uses the <strong>contact person</strong> as its name and the <strong>contact email</strong> as its email (needed for the account).
                        <i class="ti ti-mail"></i> Nothing is emailed now: once this agency's interns finish their internship, send the sign-in details with the mail icon on HTE Accounts.
                    </p>
                </div>
            </div>
            <p id="accountExists" class="il-form-hint mb-4" hidden><i class="ti ti-user-check"></i> This agency already has an HTE portal account.</p>
            <div class="mb-4"></div>
        @endunless

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
        $withAccount = $existing->filter(fn ($h) => $h->representatives_count ?? $h->representatives()->exists())->pluck('id')->values();
    @endphp
    <script>
    (function () {
        const directory = @json($directory);
        const withAccount = @json($withAccount);
        const picker = document.getElementById('existing_hte_id');
        const form = document.getElementById('partnerForm');

        // "Create an account?" - Yes shows the account box; an agency that
        // already has an account doesn't ask.
        const fields = document.getElementById('accountFields');
        const section = document.getElementById('accountSection');
        const exists = document.getElementById('accountExists');
        const email = form.querySelector('[name="contact_email"]');
        function syncAccount() {
            const yes = document.getElementById('create_account_yes').checked;
            const has = picker && withAccount.includes(Number(picker.value));
            section.hidden = has;
            exists.hidden = !has;
            fields.hidden = !yes || has;
            document.getElementById('account_id_number').required = yes && !has;
            if (email) email.required = yes && !has;
        }
        form.querySelectorAll('[name="create_account"]').forEach(r => r.addEventListener('change', syncAccount));
        if (picker) picker.addEventListener('change', syncAccount);
        syncAccount();

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
