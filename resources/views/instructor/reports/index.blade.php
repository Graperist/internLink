@extends('layouts.instructor')

@section('title', 'Generate Reports - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Generate reports</h1>
</div>

<div class="il-card">
    <p class="text-secondary small mb-3">
        Opens a printable copy of the official form, filled in from the system for your
        {{ $instructor->isCoordinator() ? 'campus' : ($instructor->program->code ?? '') }} students. The campus, instructor, coordinator and CED names come from your records.
    </p>

    <div class="row g-3">
        {{-- FM-AA-INT-09 --}}
        <div class="col-md-4">
            <form method="GET" action="{{ route('instructor.reports.student-trainees') }}" target="_blank"
                  class="border rounded p-3 h-100 d-flex flex-column">
                <p class="fw-semibold small mb-1">Masterlist of Student-Trainees</p>
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
                  class="border rounded p-3 h-100 d-flex flex-column">
                <p class="fw-semibold small mb-1">Masterlist of Partner Industries</p>
                <p class="text-secondary small mb-3">
                    FM-AA-INT-06 &middot; Partner companies with the number of your student-trainees in each
                </p>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small mb-1" for="mpi_semester">Semester</label>
                        <input type="text" class="form-control form-control-sm" id="mpi_semester" name="semester" value="2nd Semester" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="mpi_ay">Academic Year</label>
                        <input type="text" class="form-control form-control-sm" id="mpi_ay" name="academic_year"
                               value="{{ $academicYear }}" maxlength="20">
                    </div>
                </div>
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
                  class="border rounded p-3 h-100 d-flex flex-column">
                <p class="fw-semibold small mb-1">Directory of Student-Interns</p>
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
                        <input type="text" class="form-control form-control-sm" id="dsi_ay" name="academic_year"
                               value="{{ $academicYear }}" maxlength="20">
                    </div>
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
