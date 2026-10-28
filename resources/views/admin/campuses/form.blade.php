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

    <form method="POST" action="{{ $editing ? route('admin.campuses.update', $campus) : route('admin.campuses.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="mb-3">
            <label class="il-form-label" for="code">Campus code</label>
            <input type="text" class="form-control text-uppercase" id="code" name="code" maxlength="6"
                   value="{{ old('code', $campus->code) }}" placeholder="e.g. BMC" required @readonly($locked)>
            <p class="il-form-hint">
                @if ($locked)
                    The code can't be changed: this campus's accounts sign in with it (their IDs contain {{ $campus->code }}).
                @else
                    2 to 6 letters. It goes inside this campus's employee IDs, e.g. <code>{{ old('code', $campus->code) ?: 'BMC' }}-COORD-001</code>, so sign-in knows the campus.
                @endif
            </p>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="name">Campus name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $campus->name) }}"
                   placeholder="e.g. Pangasinan State University - Binmaley Campus" required>
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="address">Address <span class="text-secondary fw-normal">(optional)</span></label>
            <input type="text" class="form-control" id="address" name="address" value="{{ old('address', $campus->address) }}" placeholder="e.g. Binmaley, Pangasinan">
        </div>

        <div class="mb-3">
            <label class="il-form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="active" @selected(old('status', $campus->status) === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $campus->status) === 'inactive')>Inactive</option>
            </select>
            <p class="il-form-hint">Inactive closes the campus: its coordinator, instructors, students and HTEs can no longer sign in (anyone signed in is signed out). All its records are kept, and setting it back to Active lets everyone in again. You can still open it with Manage.</p>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">{{ $editing ? 'Save changes' : 'Add campus' }}</button>
            <a href="{{ route('admin.campuses.index') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@endsection
