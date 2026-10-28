@extends('layouts.coordinator')

@section('title', 'Manage Instructors - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Manage instructors</h1>
    <a href="{{ route('coordinator.instructors.create') }}" class="il-btn-primary text-decoration-none">
        <i class="ti ti-plus"></i> Add instructor
    </a>
</div>

@if (session('provisioned_account'))
    @php $acct = session('provisioned_account'); @endphp
    <div class="il-badge-generated mb-3">
        <strong>{{ $acct['name'] }}</strong>'s account was created.
        Share these credentials with the instructor - this password is shown once and cannot be recovered here:
        <div class="mt-2">
            ID number: <code>{{ $acct['id_number'] }}</code>
            &nbsp;&middot;&nbsp;
            Temporary password: <code>{{ $acct['temporary_password'] }}</code>
        </div>
    </div>
@endif

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($uncoveredPrograms->isNotEmpty())
    <div class="alert alert-warning">
        <strong>No instructor assigned yet:</strong>
        {{ $uncoveredPrograms->pluck('code')->join(', ') }}
    </div>
@endif

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">Subject Instructors</h2>
        <span class="text-secondary small">{{ $instructors->total() }} total</span>
    </div>

    <form method="GET" class="row g-2 mb-3 align-items-center">
        <div class="col-md il-search">
            <i class="ti ti-search"></i>
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by name or ID number..."
                value="{{ request('search') }}"
            >
        </div>
        <div class="col-md-4">
            <select name="program_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>
                        {{ $program->code }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-auto d-flex align-items-center gap-1">
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-search"></i> Search
            </button>
            @if (request()->hasAny(['search', 'program_id']))
                <a href="{{ route('coordinator.instructors.index') }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Instructor</th>
                <th>Program</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($instructors as $instructor)
                <tr>
                    <td>
                        @php $ini = \App\Models\User::initialsOf($instructor->user->name); @endphp
                        <div class="d-flex align-items-center gap-2">
                            <span class="il-avatar-sm">{{ $ini }}</span>
                            <div>
                                <div class="fw-semibold">{{ $instructor->user->name }}</div>
                                <div class="text-secondary" style="font-size: 0.78rem;">{{ $instructor->user->id_number }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if ($instructor->program)
                            <span class="badge rounded-pill text-bg-primary">{{ $instructor->program->code }}</span>
                        @else
                            <span class="badge rounded-pill text-bg-light text-secondary">Unassigned</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('coordinator.instructors.edit', $instructor) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ti ti-arrows-exchange"></i> Reassign
                        </a>
                        <form method="POST" action="{{ route('coordinator.instructors.reset-password', $instructor) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="il-icon-btn" title="Reset password"
                                    onclick="return confirm('Generate a new temporary password for {{ $instructor->user->name }}?')">
                                <i class="ti ti-key"></i><span class="visually-hidden">Reset password</span>
                            </button>
                        </form>
                        @if (\App\Support\CampusManager::active())
                            {{-- Super Admin only --}}
                            @include('partials.admin-delete-button', [
                                'action' => route('admin.campus-accounts.destroy', $instructor->user),
                                'message' => "Delete the instructor account of {$instructor->user->name} ({$instructor->user->id_number})? They will no longer be able to sign in. The reviews they made stay on the students' documents. This cannot be undone.",
                            ])
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center text-secondary py-4">
                        @if (request()->hasAny(['search', 'program_id']))
                            No instructors match this search or filter.
                        @else
                            No instructors yet.
                            <a href="{{ route('coordinator.instructors.create') }}">Add your first one</a>.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-3">
    {{ $instructors->links() }}
</div>
@endsection
