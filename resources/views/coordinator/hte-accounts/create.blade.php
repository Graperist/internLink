@extends('layouts.coordinator')

@section('title', 'Add HTE Account - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Add HTE account</h1>
    <a href="{{ route('coordinator.hte-accounts.index') }}" class="il-btn-secondary text-decoration-none">
        <i class="ti ti-arrow-left"></i> Back to HTE accounts
    </a>
</div>

<div class="il-card" style="max-width: 640px;">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('coordinator.hte-accounts.store') }}">
        @csrf

        <div class="mb-3">
            <label class="il-form-label" for="hte_id">Host Training Establishment</label>
            <select class="form-select" id="hte_id" name="hte_id" required>
                <option value="" disabled {{ old('hte_id') ? '' : 'selected' }}>Select HTE</option>
                @foreach ($htes as $hte)
                    @php $hasAccount = $hte->representatives->isNotEmpty(); @endphp
                    <option value="{{ $hte->id }}" @selected(old('hte_id') == $hte->id) {{ $hasAccount ? 'disabled' : '' }}>
                        {{ $hte->name }} @if ($hasAccount) (already has an account) @endif
                    </option>
                @endforeach
            </select>
            <p class="il-form-hint">
                Each HTE can have exactly one portal account - to change who logs in for an HTE that
                already has one, use Reset Password on the HTE Accounts page instead of creating a new one.
            </p>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="id_number">Representative ID number</label>
            <input type="text" class="form-control" id="id_number" name="id_number"
                   value="{{ old('id_number') }}" placeholder="e.g. ACC-HTE-001" required>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="name">Full name</label>
            <input type="text" class="form-control" id="name" name="name"
                   value="{{ old('name') }}" placeholder="e.g. Engr. Ricardo Bautista" required>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="{{ old('email') }}" placeholder="e.g. ricardo.bautista@company.com" required>
            <p class="il-form-hint">Used for account recovery, not for logging in - login uses the ID number above.</p>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">Create HTE account</button>
            <a href="{{ route('coordinator.hte-accounts.index') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@endsection
