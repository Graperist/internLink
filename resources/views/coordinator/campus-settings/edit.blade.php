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

        <hr class="my-4">
        <label class="form-label small fw-semibold" for="weekly_report_due_days">Form 19 weekly report deadline</label>
        <div class="input-group mb-1" style="max-width: 360px;">
            <input type="number" min="0" max="14" id="weekly_report_due_days" name="weekly_report_due_days" class="form-control"
                   value="{{ old('weekly_report_due_days', $campus->weekly_report_due_days ?? 2) }}" required>
            <span class="input-group-text">days after the week ends</span>
        </div>
        <p class="small text-muted mb-3">Each week is due at 11:59 PM this many days after its last day (0 = on its last day). A week first submitted after that is marked <strong>Late</strong> and the Subject Instructor is notified.</p>

        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
</div>
@endsection
