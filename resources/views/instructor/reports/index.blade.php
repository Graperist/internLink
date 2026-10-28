@extends('layouts.instructor')

@section('title', 'Generate Reports - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Generate reports</h1>
</div>

@if ($campusSummary)
    {{-- The coordinator: the campus at a glance, then every program's official forms. --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="il-card d-flex align-items-center gap-3 py-3">
                <span class="il-tile-icon"><i class="ti ti-users"></i></span>
                <div><div class="il-stat-number">{{ $campusSummary['programStats']->sum('total') }}</div>
                <div class="text-secondary small">Total Enrolled Interns</div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="il-card d-flex align-items-center gap-3 py-3">
                <span class="il-tile-icon"><i class="ti ti-building-community"></i></span>
                <div><div class="il-stat-number">{{ $campusSummary['hteCount'] }}</div>
                <div class="text-secondary small">Partner HTEs</div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="il-card d-flex align-items-center gap-3 py-3">
                <span class="il-tile-icon"><i class="ti ti-file-certificate"></i></span>
                <div><div class="il-stat-number">{{ $campusSummary['activeMoaCount'] }}</div>
                <div class="text-secondary small">Active MOAs</div></div>
            </div>
        </div>
    </div>

    <div class="il-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold mb-0">Program Summary</h2>
            <a href="{{ route('coordinator.reports.export-roster') }}" class="small fw-semibold text-decoration-none">
                <i class="ti ti-download"></i> Export Intern Roster (CSV)
            </a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em;">
                        <th>Program</th>
                        <th>Total interns</th>
                        <th>Deployed</th>
                        <th>Pending</th>
                        <th>Incomplete docs</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campusSummary['programStats'] as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['program']->code }}</td>
                            <td>{{ $row['total'] }}</td>
                            <td>{{ $row['deployed'] }}</td>
                            <td>{{ $row['pending'] }}</td>
                            <td>{{ $row['incomplete'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">No programs set up for this campus yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="il-card">
    <p class="text-secondary small mb-3">
        Opens a printable copy of the official form, filled in from the system for your
        {{ $instructor->isCoordinator() ? 'campus students: all programs, or choose one' : ($instructor->program->code ?? '').' students' }}. The campus, instructor, coordinator, College Dean and CED names come from your records.
    </p>

    <div class="row g-3">
        {{-- FM-AA-INT-09 --}}
        <div class="col-md-4">
            <form method="GET" action="{{ route('instructor.reports.student-trainees') }}" target="_blank"
                  class="il-report-card h-100 d-flex flex-column">
                <div class="d-flex align-items-center gap-2 mb-2"><span class="il-tile-icon sm"><i class="ti ti-users-group"></i></span><p class="fw-semibold mb-0">Masterlist of Student-Trainees</p></div>
                <p class="text-secondary small mb-3">
                    FM-AA-INT-09 &middot; One page per company, listing the student-trainees placed there
                </p>
                <div class="mb-3">
                    <label class="form-label small mb-1" for="mst_hte">Company</label>
                    <select class="form-select form-select-sm" id="mst_hte" name="hte_id" @disabled($htes->isEmpty())>
                        @if ($htes->isNotEmpty())
                            <option value="">All companies</option>
                        @endif
                        @forelse ($htes as $hte)
                            <option value="{{ $hte->id }}">{{ $hte->name }}</option>
                        @empty
                            <option value="">No deployed students yet</option>
                        @endforelse
                    </select>
                </div>
                @include('instructor.reports._program-picker', ['id' => 'mst_program'])
                <div class="mt-auto">
                    <button type="submit" class="btn btn-primary btn-sm" @disabled($htes->isEmpty())>
                        <i class="ti ti-printer"></i> Generate
                    </button>
                </div>
            </form>
        </div>

        {{-- FM-AA-INT-06 --}}
        <div class="col-md-4">
            <form method="GET" action="{{ route('instructor.reports.partner-industries') }}" target="_blank"
                  class="il-report-card h-100 d-flex flex-column">
                <div class="d-flex align-items-center gap-2 mb-2"><span class="il-tile-icon sm"><i class="ti ti-building-community"></i></span><p class="fw-semibold mb-0">Masterlist of Partner Industries</p></div>
                <p class="text-secondary small mb-3">
                    FM-AA-INT-06 &middot; Printed from <a href="{{ route('instructor.htes') }}">Partner Agencies</a>:
                    each agency on the term's list with the number of student-trainees
                </p>
                @php [$mpiYear, $mpiSemester] = \App\Support\Term::current(); @endphp
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small mb-1" for="mpi_semester">Semester</label>
                        <input type="text" class="form-control form-control-sm" id="mpi_semester" name="semester" value="{{ \App\Support\Term::SEMESTER }}" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="mpi_ay">Academic Year</label>
                        @include('partials.academic-year-input', ['name' => 'academic_year', 'id' => 'mpi_ay', 'value' => $mpiYear, 'small' => true])
                    </div>
                </div>
                @include('instructor.reports._program-picker', ['id' => 'mpi_program'])
                <div class="mt-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ti ti-printer"></i> Generate
                    </button>
                </div>
            </form>
        </div>

        {{-- FM-AA-INT-05 --}}
        <div class="col-md-4">
            <form method="GET" action="{{ route('instructor.reports.directory') }}" target="_blank"
                  class="il-report-card h-100 d-flex flex-column">
                <div class="d-flex align-items-center gap-2 mb-2"><span class="il-tile-icon sm"><i class="ti ti-address-book"></i></span><p class="fw-semibold mb-0">Directory of Student-Interns</p></div>
                <p class="text-secondary small mb-3">
                    FM-AA-INT-05 &middot; Name, home address, email and contact number of your student-interns
                </p>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small mb-1" for="dsi_semester">Semester</label>
                        <input type="text" class="form-control form-control-sm" id="dsi_semester" name="semester" value="2nd Semester" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="dsi_ay">Academic Year</label>
                        @include('partials.academic-year-input', ['name' => 'academic_year', 'id' => 'dsi_ay', 'value' => $academicYear, 'small' => true])
                    </div>
                </div>
                @include('instructor.reports._program-picker', ['id' => 'dsi_program'])
                <div class="mt-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ti ti-printer"></i> Generate
                    </button>
                </div>
            </form>
        </div>

        {{-- FM-AA-INT-16 --}}
        <div class="col-md-4">
            <form method="GET" action="{{ route('instructor.reports.site-monitoring') }}" target="_blank"
                  class="il-report-card h-100 d-flex flex-column">
                <div class="d-flex align-items-center gap-2 mb-2"><span class="il-tile-icon sm"><i class="ti ti-map-pin-check"></i></span><p class="fw-semibold mb-0">Internship Site Monitoring Report</p></div>
                <p class="text-secondary small mb-3">
                    FM-AA-INT-16 &middot; For a visit to a company: its student-trainees are filled in; the feedback is written in during the visit
                </p>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small mb-1" for="ism_hte">Company</label>
                        <select class="form-select form-select-sm" id="ism_hte" name="hte_id" @disabled($htes->isEmpty())>
                            @if ($htes->isNotEmpty())
                                <option value="">All companies</option>
                            @endif
                            @forelse ($htes as $hte)
                                <option value="{{ $hte->id }}">{{ $hte->name }}</option>
                            @empty
                                <option value="">No deployed students yet</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="ism_date">Date of monitoring</label>
                        <input type="date" class="form-control form-control-sm" id="ism_date" name="monitoring_date" value="{{ now()->toDateString() }}">
                    </div>
                </div>
                @include('instructor.reports._program-picker', ['id' => 'ism_program'])
                <div class="mt-auto">
                    <button type="submit" class="btn btn-primary btn-sm" @disabled($htes->isEmpty())>
                        <i class="ti ti-printer"></i> Generate
                    </button>
                </div>
            </form>
        </div>

        {{-- FM-AA-INT-24 --}}
        <div class="col-md-4">
            <form method="GET" action="{{ route('instructor.reports.accreditation') }}" target="_blank"
                  class="il-report-card h-100 d-flex flex-column">
                <div class="d-flex align-items-center gap-2 mb-2"><span class="il-tile-icon sm"><i class="ti ti-certificate"></i></span><p class="fw-semibold mb-0">Accreditation Instrument of Partner Agencies</p></div>
                <p class="text-secondary small mb-3">
                    FM-AA-INT-24 &middot; The eight criteria for accrediting a partner agency as a Host Training Establishment, to check on paper
                </p>
                <div class="mb-3">
                    <label class="form-label small mb-1" for="acc_hte">Partner agency</label>
                    <select class="form-select form-select-sm" id="acc_hte" name="hte_id">
                        <option value="">Blank form (no agency)</option>
                        @foreach ($partnerAgencies as $agency)
                            <option value="{{ $agency->id }}">{{ $agency->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mt-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ti ti-printer"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
