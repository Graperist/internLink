@extends('layouts.instructor')

@section('title', 'My Students - PSU Intern-Link')

@section('content')
@php
    $isCoordinator = $instructor->isCoordinator();
    $hasProgram = $instructor->canAddForPrograms();
@endphp

<div class="il-page-header">
    <h1 class="il-page-title">{{ $isCoordinator ? 'Students' : 'My students' }}</h1>
    @if ($hasProgram)
        <div class="d-flex gap-2">
            <a href="{{ route('instructor.interns.import') }}" class="btn btn-outline-secondary text-decoration-none">
                <i class="ti ti-upload"></i> Bulk enroll (import)
            </a>
            <a href="{{ route('instructor.interns.create') }}" class="il-btn-primary text-decoration-none">
                <i class="ti ti-user-plus"></i> Enroll student
            </a>
        </div>
    @endif
</div>

@if ($isCoordinator && $instructor->program_id === null)
    <div class="alert alert-warning small">
        You can see and enroll students of every program on the campus. To review documents for the program you teach,
        <a href="{{ route('instructor.profile') }}" class="alert-link">set your program in My Profile</a>.
    </div>
@endif

@if (session('provisioned_account'))
    @php $acct = session('provisioned_account'); @endphp
    <div class="il-badge-generated mb-3">
        <strong>{{ $acct['name'] }}</strong>{{ ! empty($acct['reset']) ? "'s password was reset." : "'s account was created." }}
        Share these credentials with the student - this password is shown once and cannot be recovered here:
        <div class="mt-2">
            ID number: <code>{{ $acct['id_number'] }}</code>
            &nbsp;&middot;&nbsp;
            Temporary password: <code>{{ $acct['temporary_password'] }}</code>
        </div>
    </div>
@endif

@if (session('import_results'))
    @php $results = session('import_results'); @endphp
    <div class="il-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 fw-bold mb-0">Import results</h2>
            <span class="small text-secondary">{{ count($results['created']) }} created &middot; {{ count($results['skipped']) }} skipped</span>
        </div>
        @if (count($results['created']))
            <div class="small text-secondary mb-1">Share each temporary password with the student - it's shown once.</div>
            <div class="table-responsive mb-3" style="max-height: 220px; overflow-y: auto;">
                <table class="table table-sm mb-0">
                    <thead><tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase;"><th>Row</th><th>Name</th><th>ID number</th><th>Temporary password</th></tr></thead>
                    <tbody>
                        @foreach ($results['created'] as $row)
                            <tr><td>{{ $row['row'] }}</td><td>{{ $row['name'] }}</td><td><code>{{ $row['id_number'] }}</code></td><td><code>{{ $row['temporary_password'] }}</code></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if (count($results['skipped']))
            <div class="small fw-semibold text-danger mb-1">Skipped rows</div>
            <ul class="small text-danger mb-0">
                @foreach ($results['skipped'] as $row)
                    <li>Row {{ $row['row'] }}: {{ $row['reason'] }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif
@foreach (['completion', 'activation'] as $errorKey)
    @if ($errors->has($errorKey))
        <div class="alert alert-danger" role="alert">{{ $errors->first($errorKey) }}</div>
    @endif
@endforeach

<div class="il-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">
            @if ($isCoordinator)
                All Student Interns &middot; {{ $instructor->campus->name ?? '' }}
            @else
                My {{ $instructor->program->code ?? '' }} Student Interns
            @endif
        </h2>
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('instructor.advisees.export', request()->query()) }}" class="small fw-semibold text-decoration-none">
                Export via Excel
            </a>
            <span class="text-secondary small">{{ $advisees->total() }} total</span>
        </div>
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
        @if ($isCoordinator)
            <div class="col-md-3">
                <select name="program_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All programs</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                            {{ $program->code }}{{ $program->id === $instructor->program_id ? ' (mine)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-md-3">
            <select name="hte_id" class="form-select" onchange="this.form.submit()" aria-label="Partner agency">
                <option value="">All partner agencies</option>
                @foreach ($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected((string) request('hte_id') === (string) $agency->id)>{{ $agency->name }}</option>
                @endforeach
                <option value="none" @selected(request('hte_id') === 'none')>No partner agency yet</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="pending" @selected(request('status') === 'pending')>Placed (not yet started)</option>
                <option value="active" @selected(request('status') === 'active')>Deployed</option>
                <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                <option value="terminated" @selected(request('status') === 'terminated')>Terminated</option>
                <option value="not_deployed" @selected(request('status') === 'not_deployed')>Not Deployed</option>
            </select>
        </div>
        <div class="col-md-auto d-flex align-items-center gap-1">
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-search"></i> Search
            </button>
            @if (request()->hasAny(['search', 'status', 'program_id', 'hte_id']))
                <a href="{{ route('instructor.advisees') }}" class="btn btn-sm btn-link">Clear</a>
            @endif
        </div>
    </form>

    <table class="table align-middle mb-0">
        <thead>
            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                <th>Student</th>
                @if ($isCoordinator)<th>Program</th>@endif
                <th>Required hours</th>
                <th>HTE assignment</th>
                <th>Doc status</th>
                <th>Deployment status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @php
                $deploymentColors = [
                    'pending' => 'text-bg-warning',
                    'active' => 'text-bg-primary',
                    'completed' => 'text-bg-secondary',
                    'terminated' => 'text-bg-danger',
                ];
                $deploymentLabels = [
                    'pending' => 'Placed - Not Started',
                    'active' => 'Deployed',
                    'completed' => 'Completed',
                    'terminated' => 'Terminated',
                ];
            @endphp
            @forelse ($advisees as $student)
                @php
                    $internship = $student->currentInternship;
                    $deployment = $internship?->deployment;
                    $requiredHours = $internship?->requiredHours() ?? $student->program?->required_hours;
                    $canAct = $instructor->canActOn($student);
                    $approved = $student->approved_documents_count;
                    $missing = max($requiredDocsCount - $approved, 0);
                    // Completion goes by hours: the hours entered in Time Frame against the required hours.
                    $verifiedHours = $deployment ? (float) $deployment->dtrs->sum(fn ($dtr) => $dtr->countedHours()) : 0;
                    $completionReady = $deployment
                        && $internship
                        && $deployment->status === 'active'
                        && $internship->status === 'deployed'
                        && $requiredHours > 0
                        && $verifiedHours >= $requiredHours;
                @endphp
                <tr>
                    <td>
                        @php $initials = \App\Models\User::initialsOf($student->user->name); @endphp
                        <div class="d-flex align-items-center gap-2">
                            <span class="il-avatar-sm">{{ $initials }}</span>
                            <div>
                                <div class="fw-semibold">{{ $student->user->name }}</div>
                                <div class="text-secondary" style="font-size: 0.78rem;">{{ $student->user->id_number }}</div>
                            </div>
                        </div>
                    </td>
                    @if ($isCoordinator)
                        <td>
                            <span class="badge rounded-pill {{ $canAct ? 'text-bg-primary' : 'text-bg-light text-secondary border' }}">{{ $student->program->code ?? '—' }}</span>
                            @if ($canAct)
                                <div class="text-secondary" style="font-size: 0.72rem;">Your program</div>
                            @endif
                        </td>
                    @endif
                    <td>{!! $requiredHours ? e($requiredHours).' hrs' : '<span class="text-danger small">Not set</span>' !!}</td>
                    <td class="small">@if ($deployment?->hte)<i class="ti ti-building text-secondary"></i> {{ $deployment->hte->name }}@else <span class="text-secondary">&mdash;</span> @endif</td>
                    <td>
                        @if ($missing === 0)
                            <span class="badge rounded-pill text-bg-primary">All Complete</span>
                        @else
                            <span class="badge rounded-pill text-bg-danger">{{ $missing }} Missing</span>
                        @endif
                    </td>
                    <td>
                        @if ($deployment)
                            <span class="badge rounded-pill {{ $deploymentColors[$deployment->status] ?? 'text-bg-light' }}">
                                {{ $deploymentLabels[$deployment->status] ?? ucfirst($deployment->status) }}
                            </span>
                        @else
                            <span class="badge rounded-pill text-bg-light text-secondary">Not Deployed</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            @if ($missing > 0 && $canAct)
                                <a href="{{ route('instructor.documents', ['student' => $student->id]) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="ti ti-file-search"></i> Review Docs
                                </a>
                            @else
                                <a href="{{ route($canAct ? 'instructor.documents' : 'coordinator.documents.student', $canAct ? ['student' => $student->id] : $student) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-eye"></i> View
                                </a>
                            @endif
                            @if ($canAct)
                                <form method="POST" action="{{ route('instructor.interns.reset-password', $student) }}" class="d-inline"
                                      onsubmit="return confirm('Generate a new temporary password for {{ addslashes($student->user->name) }}?');">
                                    @csrf
                                    <button type="submit" class="il-icon-btn" title="Reset password"><i class="ti ti-key"></i><span class="visually-hidden">Reset password</span></button>
                                </form>
                            @endif
                            @if (\App\Support\CampusManager::active())
                                {{-- Super Admin only --}}
                                @include('partials.admin-delete-button', [
                                    'action' => route('admin.campus-accounts.destroy', $student->user),
                                    'message' => "Delete the student {$student->user->name} ({$student->user->id_number})? This deletes their account, internship, documents, uploaded files, time frame and evaluations. This cannot be undone.",
                                ])
                            @endif
                        </div>
                        @if ($canAct && $deployment && $deployment->status === 'pending')
                            @if ($missing === 0)
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-primary"
                                            data-bs-toggle="modal" data-bs-target="#activateModal"
                                            data-action="{{ route('instructor.advisees.activate', $deployment) }}"
                                            data-student="{{ $student->user->name }}"
                                            data-hte="{{ $deployment->hte?->name }}">
                                        Start deployment
                                    </button>
                                </div>
                            @else
                                <div class="small text-secondary mt-1">Start deployment unlocks once all pre-OJT requirements are approved.</div>
                            @endif
                        @elseif ($canAct && ! $deployment && $internship)
                            <div class="small text-secondary mt-1">Placement is created when you accept the Application for OJT.</div>
                        @elseif (! $canAct)
                            <div class="mt-2"><span class="il-viewonly"><i class="ti ti-eye"></i> View only</span></div>
                            <div class="text-secondary mt-1" style="font-size:.74rem;">{{ $student->program->code ?? 'Their' }} instructor handles this student</div>
                        @endif
                        @if ($deployment && $deployment->status === 'active')
                            @php $renderedPct = $requiredHours > 0 ? min(100, (int) round($verifiedHours / $requiredHours * 100)) : 0; @endphp
                            <div class="mt-2 small text-secondary text-nowrap">Rendered: {{ \App\Support\TimeFrame::format($verifiedHours) }} / {{ \App\Support\TimeFrame::format((float) $requiredHours) }} hrs</div>
                            <div class="progress mt-1" style="height:4px; max-width:180px;"><div class="progress-bar {{ $renderedPct >= 100 ? 'bg-success' : '' }}" style="width: {{ $renderedPct }}%"></div></div>
                            @if (! $canAct)
                                {{-- another program's student: the coordinator only views --}}
                            @elseif ($completionReady)
                                <form method="POST" action="{{ route('instructor.advisees.confirm-completion', $deployment) }}" class="mt-2" onsubmit="return confirm('Confirm that this student has completed their required hours? This ends the internship and unlocks the post-OJT requirements.');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Confirm completion</button>
                                </form>
                            @else
                                <div class="small text-secondary mt-1">Completion unlocks once the student's rendered hours reach the required hours.</div>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isCoordinator ? 7 : 6 }}" class="text-center text-secondary py-4">
                        @if (request()->hasAny(['search', 'status', 'program_id', 'hte_id']))
                            No students match this search or filter.
                        @else
                            {{ $isCoordinator ? 'No students on this campus yet.' : 'No students are enrolled in your program yet. Use Enroll student to create their accounts.' }}
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-3">
    {{ $advisees->links() }}
</div>

{{-- Start deployment: the placement came from the accepted Application for OJT --}}
<div class="modal fade" id="activateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="activateForm" action="">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold">Start deployment</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary">
                        <strong id="activateStudent"></strong> will be deployed at <strong id="activateHte"></strong>,
                        the firm chosen on their accepted Application for OJT. The student and the HTE are notified.
                    </p>
                    <p class="small mb-0">
                        <i class="ti ti-info-circle text-primary"></i>
                        The deployment starts today. There are no dates to set: the internship runs until the student's rendered
                        hours reach their required hours (from their Application for OJT), then you confirm completion.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Start deployment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('activateModal')?.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('activateForm').action = button.getAttribute('data-action');
    document.getElementById('activateStudent').textContent = button.getAttribute('data-student');
    document.getElementById('activateHte').textContent = button.getAttribute('data-hte');
});
</script>
@endsection
