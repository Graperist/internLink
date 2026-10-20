@extends('layouts.coordinator')

@section('title', 'Campus Settings - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Campus settings</h1>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger small">{{ $errors->first() }}</div>
@endif

<div class="il-card" style="max-width: 560px;">
    <h2 class="h6 fw-bold mb-1">Signatories for printable forms</h2>
    <p class="small text-muted mb-3">
        These names are printed automatically on every {{ $campus->name }} student form that has these
        signature lines (Application, Internship Agreement, Certificate of Units Earned, and others).
        Leave blank to print an empty signature line. The Subject Instructor and Coordinator come from
        their accounts, and each program's Department Chair is set on the Programs page.
    </p>

    <form method="POST" action="{{ route('coordinator.campus-settings.update') }}">
        @csrf
        @method('PUT')

        <label class="form-label small fw-semibold" for="ced_name">Campus Executive Director</label>
        <input type="text" id="ced_name" name="ced_name" class="form-control mb-3"
               value="{{ old('ced_name', $campus->ced_name) }}" maxlength="255">

        <label class="form-label small fw-semibold" for="college_dean_name">College Dean</label>
        <input type="text" id="college_dean_name" name="college_dean_name" class="form-control mb-3"
               value="{{ old('college_dean_name', $campus->college_dean_name) }}" maxlength="255">

        <label class="form-label small fw-semibold" for="registrar_name">Registrar</label>
        <input type="text" id="registrar_name" name="registrar_name" class="form-control mb-3"
               value="{{ old('registrar_name', $campus->registrar_name) }}" maxlength="255">

        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
</div>
@endsection
