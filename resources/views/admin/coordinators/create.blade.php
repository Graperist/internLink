@extends('layouts.admin')

@section('title', 'Add OJT Coordinator - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Add OJT Coordinator</h1>
    <a href="{{ route('admin.coordinators.index') }}" class="il-btn-secondary text-decoration-none"><i class="ti ti-arrow-left"></i> Back to coordinators</a>
</div>

<div class="il-card" style="max-width: 640px;">
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if ($campuses->isEmpty())
        <p class="text-secondary mb-0">There's no active campus yet. <a href="{{ route('admin.campuses.create') }}">Add a campus</a> first.</p>
    @else
    <form method="POST" action="{{ route('admin.coordinators.store') }}">
        @csrf
        <div class="mb-3">
            <label class="il-form-label" for="campus_id">Campus</label>
            <select class="form-select" id="campus_id" name="campus_id" required>
                <option value="" disabled @selected(! old('campus_id', $selectedCampus))>Select campus</option>
                @foreach ($campuses as $campus)
                    <option value="{{ $campus->id }}" data-code="{{ $campus->code }}" @selected((string) old('campus_id', $selectedCampus) === (string) $campus->id)>{{ $campus->code }} - {{ $campus->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="id_number">Employee ID number</label>
            <input type="text" class="form-control text-uppercase" id="id_number" name="id_number" value="{{ old('id_number') }}" placeholder="e.g. BMC-COORD-001" required>
            <p class="il-form-hint" id="idHint">Must include the campus code, so sign-in knows the campus.</p>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="name">Full name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Dr. Maria Santos" required>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" placeholder="e.g. maria.santos@psu.edu.ph" required>
            <p class="il-form-hint">For the sign-in details, password changes and account recovery. Sign-in uses the ID number.</p>
        </div>

        <div class="form-check mb-3">
            <input type="hidden" name="send_email" value="0">
            <input class="form-check-input" type="checkbox" id="send_email" name="send_email" value="1" @checked(old('send_email', '1') === '1')>
            <label class="form-check-label" for="send_email">Email the sign-in details (ID number and temporary password) to this address</label>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">Create coordinator account</button>
            <a href="{{ route('admin.coordinators.index') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
    <script>
        // Suggest an ID with the chosen campus's code, e.g. BMC-COORD-001.
        (function () {
            const campus = document.getElementById('campus_id');
            const id = document.getElementById('id_number');
            const hint = document.getElementById('idHint');
            const update = () => {
                const code = campus.selectedOptions[0]?.dataset.code;
                if (!code) return;
                id.placeholder = 'e.g. ' + code + '-COORD-001';
                hint.textContent = 'Must include the campus code ' + code + ', e.g. ' + code + '-COORD-001.';
            };
            campus.addEventListener('change', update);
            update();
        })();
    </script>
    @endif
</div>
@endsection
