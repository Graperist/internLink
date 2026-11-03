@extends('layouts.admin')

@php $editing = $campus->exists; $locked = $editing && $campus->users()->exists(); @endphp
@section('title', ($editing ? 'Edit Campus' : 'Add Campus').' - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">{{ $editing ? 'Edit campus' : 'Add campus' }}</h1>
    <a href="{{ route('admin.campuses.index') }}" class="il-btn-secondary text-decoration-none"><i class="ti ti-arrow-left"></i> Back to campuses</a>
</div>

<div class="il-card" style="max-width: 640px;">
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" id="campusForm" action="{{ $editing ? route('admin.campuses.update', $campus) : route('admin.campuses.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="mb-3">
            <label class="il-form-label" for="code">Campus code</label>
            <input type="text" class="form-control text-uppercase @error('code') is-invalid @enderror" id="code" name="code" maxlength="3" pattern="[A-Z]{2,3}"
                   autocapitalize="characters" autocomplete="off" spellcheck="false"
                   title="2 to 3 capital letters (A-Z)"
                   value="{{ old('code', $campus->code) }}" placeholder="e.g. BMC" required @readonly($locked)>
            <div class="invalid-feedback" id="codeMsg">{{ $errors->first('code') ?: 'This campus code is already used by another campus.' }}</div>
            <p class="il-form-hint">
                @if ($locked)
                    The code can't be changed: this campus's accounts sign in with it (their IDs contain {{ $campus->code }}).
                @else
                    2 to 3 letters. It goes inside this campus's employee IDs, e.g. <code>{{ old('code', $campus->code) ?: 'BMC' }}-COORD-001</code>, so sign-in knows the campus.
                @endif
            </p>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="name">Campus name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $campus->name) }}"
                   placeholder="e.g. Pangasinan State University - Binmaley Campus" required>
            <div class="invalid-feedback" id="nameTaken">{{ $errors->first('name') ?: 'This campus already exists.' }}</div>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="address">Address <span class="text-secondary fw-normal">(optional)</span></label>
            <input type="text" class="form-control" id="address" name="address" value="{{ old('address', $campus->address) }}" placeholder="e.g. Binmaley, Pangasinan">
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">{{ $editing ? 'Save' : 'Add' }}</button>
            <a href="{{ route('admin.campuses.index') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>

<script>
    // Warns right away if the code or name is already used, and stops the form with a pop-up.
    (function () {
        const taken = @json($taken);
        const form = document.getElementById('campusForm');
        const code = document.getElementById('code');
        const name = document.getElementById('name');
        const codeMsg = document.getElementById('codeMsg');
        // Same rule as the server: "PSU Binmaley" = "Pangasinan State University - Binmaley Campus".
        const placeKey = (n) => {
            let k = n.toLowerCase().replace(/pangasinan state university|psu/g, ' ').replace(/\b(campus|city|of|the)\b/g, ' ').replace(/[^a-z0-9]+/g, '');
            return k !== '' ? k : n.trim().toLowerCase();
        };
        const check = (submitting) => {
            const value = code.value.trim();
            const codeUsed = taken.codes.includes(value);
            const codeBad = (submitting || value.length >= 3 || document.activeElement !== code) && value !== '' && !/^[A-Z]{2,3}$/.test(value);
            const nameUsed = name.value.trim() !== '' && taken.names.includes(placeKey(name.value));
            if (!code.readOnly) {
                code.classList.toggle('is-invalid', codeUsed || codeBad);
                codeMsg.textContent = codeUsed ? 'This campus code is already used by another campus.' : '2 to 3 capital letters only (A-Z), e.g. ACC or BC.';
            }
            name.classList.toggle('is-invalid', nameUsed);
            return { codeUsed: codeUsed && !code.readOnly, codeBad: codeBad && !code.readOnly, nameUsed };
        };
        // Letters only, always in capitals (CAPS LOCK or not).
        code.addEventListener('input', () => { code.value = code.value.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 3); check(); });
        code.addEventListener('blur', () => check());
        name.addEventListener('input', check);
        form.addEventListener('submit', (e) => {
            const { codeUsed, codeBad, nameUsed } = check(true);
            if (codeBad) {
                e.preventDefault();
                alert('The campus code must be 2 to 3 capital letters (A-Z), e.g. ACC or BC.');
                code.focus();
                return;
            }
            if (codeUsed || nameUsed) {
                e.preventDefault();
                const what = [codeUsed ? 'the campus code ' + code.value.trim().toUpperCase() : null, nameUsed ? 'the campus name' : null].filter(Boolean).join(' and ');
                alert('This campus already exists: ' + what + ' is already used by another campus (it may be under Archived campuses).');
            }
        });
        check();
    })();
</script>
@endsection
