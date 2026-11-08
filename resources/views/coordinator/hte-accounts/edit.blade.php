@extends('layouts.coordinator')

@section('title', 'Edit HTE Account - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Edit HTE account</h1>
    <a href="{{ route('instructor.hte-accounts.index') }}" class="il-btn-secondary text-decoration-none">
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

    <p class="small text-secondary mb-3">
        <i class="ti ti-building"></i> Account of <strong>{{ $account->hte->name }}</strong>
    </p>

    <form method="POST" action="{{ route('instructor.hte-accounts.update', $account) }}">
        @csrf
        @method('PUT')

        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="il-form-label" for="academic_year">Academic year</label>
                @include('partials.academic-year-input', ['name' => 'academic_year', 'id' => 'academic_year', 'value' => old('academic_year', $account->academic_year ?: \App\Support\Term::current()[0]), 'required' => true])
            </div>
            <div class="col-md-6">
                <label class="il-form-label" for="semester">Semester</label>
                @include('partials.semester-select', ['name' => 'semester', 'id' => 'semester', 'value' => old('semester', $account->semester ?: \App\Support\Term::current()[1])])
            </div>
            <p class="il-form-hint mb-0">The term the account is for. It shows under this academic year and semester on HTE Accounts.</p>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="id_number">Representative ID number</label>
            <input type="text" class="form-control" id="id_number" name="id_number" maxlength="100"
                   value="{{ old('id_number', $account->user->id_number) }}" required>
            <p class="il-form-hint">They sign in with this ID number.</p>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="name">Full name</label>
            <input type="text" class="form-control" id="name" name="name" maxlength="255"
                   value="{{ old('name', $account->user->name) }}" required>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email" maxlength="255"
                   value="{{ old('email', $account->user->email) }}" required>
            <p class="il-form-hint">This also becomes the partner agency's contact email. It can't be an email another account already uses. The mail icon sends the sign-in details here.</p>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">Save changes</button>
            <a href="{{ route('instructor.hte-accounts.index') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@endsection
