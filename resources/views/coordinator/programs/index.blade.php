@extends('layouts.coordinator')

@section('title', 'Programs - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Programs</h1>
    <a href="{{ route('coordinator.programs.create') }}" class="il-btn-primary text-decoration-none">
        <i class="ti ti-plus"></i> Add program
    </a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->has('program'))
    <div class="alert alert-danger">{{ $errors->first('program') }}</div>
@endif

@php $missingHours = $programs->filter(fn ($p) => ! $p->required_hours); @endphp
@if ($missingHours->isNotEmpty())
    <div class="alert alert-warning small">
        <strong>Required OJT hours not set:</strong> {{ $missingHours->pluck('code')->join(', ') }}.
        Students in these programs can't complete forms that print their required hours, and completion can't be confirmed, until you set them.
    </div>
@endif

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <h2 class="h6 fw-bold mb-0">Courses &amp; majors offered for OJT</h2>
        <span class="text-secondary small">{{ $programs->count() }} total</span>
    </div>
    <p class="text-secondary small mb-3">
        Each row is one course, optionally with a major. Instructors are assigned to a program and add their interns into it,
        and students pick their Course and Major from this list on their forms.
    </p>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                    <th>Code</th>
                    <th>Course</th>
                    <th>Major</th>
                    <th>Required hours</th>
                    <th>Instructors</th>
                    <th>Students</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($programs as $program)
                    <tr>
                        <td class="fw-semibold">{{ $program->code }}</td>
                        <td>{{ $program->courseTitle() }}</td>
                        <td>{{ $program->majorLabel() === 'N/A' ? '—' : $program->majorLabel() }}</td>
                        <td>
                            @if ($program->required_hours)
                                {{ $program->required_hours }} hrs
                            @else
                                <a href="{{ route('coordinator.programs.edit', $program) }}" class="badge rounded-pill text-bg-danger text-decoration-none">Not set</a>
                            @endif
                        </td>
                        <td>
                            @if ($program->instructors_count === 0)
                                <span class="badge rounded-pill text-bg-warning">None yet</span>
                            @else
                                {{ $program->instructors_count }}
                            @endif
                        </td>
                        <td>{{ $program->students_count }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('coordinator.programs.edit', $program) }}" class="small fw-semibold text-decoration-none me-2">Edit</a>
                            @if ($program->students_count === 0 && $program->instructors_count === 0)
                                <form method="POST" action="{{ route('coordinator.programs.destroy', $program) }}" class="d-inline"
                                      onsubmit="return confirm('Remove {{ $program->code }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link btn-sm p-0 small text-danger text-decoration-none">Remove</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No programs yet. Add the first course offered for OJT.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
