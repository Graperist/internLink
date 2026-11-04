@extends('layouts.coordinator')

@section('title', 'Campus Settings - PSU Intern-Link')

@section('content')
@include('coordinator._settings-back')
<div class="il-page-header">
    <h1 class="il-page-title">Campus settings</h1>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger small">{{ $errors->first() }}</div>
@endif

{{-- The campus this coordinator manages --}}
<div class="il-card mb-3" style="max-width: 760px;">
    <div class="d-flex align-items-center gap-3">
        <span class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:44px;height:44px;background:#eef2f7;color:#1c3f66;"><i class="ti ti-building-bank fs-4"></i></span>
        <div>
            <div class="small text-secondary text-uppercase fw-semibold" style="letter-spacing:.03em;">Your campus</div>
            <div class="fw-bold">{{ $campus->name }} <span class="badge rounded-pill text-bg-light border ms-1">{{ $campus->code }}</span></div>
            @if ($campus->address)<div class="small text-secondary"><i class="ti ti-map-pin"></i> {{ $campus->address }}</div>@endif
        </div>
    </div>
</div>

<div class="il-card" style="max-width: 760px;">
    <h2 class="h6 fw-bold mb-1">Signatories for printable forms</h2>
    <p class="small text-muted mb-3">
        These names are printed automatically on every {{ $campus->name }} student form that has these
        signature lines (Application, Internship Agreement, Certificate of Units Earned, and others).
        Leave blank to print an empty signature line. The Subject Instructor and Coordinator come from
        their accounts.
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

        <h3 class="h6 fw-bold mt-4 mb-1">Department Chairs</h3>
        <p class="small text-muted mb-2">Printed on each program's student forms (e.g. the Application for Internship).</p>
        @forelse ($programs as $program)
            <div class="row g-2 align-items-center mb-2">
                <label class="col-sm-4 col-form-label small fw-semibold" for="chair-{{ $program->id }}">
                    {{ $program->code }}
                    <span class="d-block fw-normal text-secondary" style="font-size:.75rem;">{{ $program->courseTitle() }}{{ $program->majorLabel() !== 'N/A' ? ' – '.$program->majorLabel() : '' }}</span>
                </label>
                <div class="col-sm-8">
                    <input type="text" id="chair-{{ $program->id }}" name="department_chairs[{{ $program->id }}]" class="form-control"
                           value="{{ old('department_chairs.'.$program->id, $program->department_chair_name) }}" maxlength="255" placeholder="e.g. Dr. Maria Santos">
                </div>
            </div>
        @empty
            <p class="small text-secondary">No programs yet &mdash; add them on the Programs page.</p>
        @endforelse

        <button type="submit" class="btn btn-primary mt-2">Save settings</button>
    </form>
</div>
@endsection
