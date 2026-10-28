@extends('layouts.student')

@section('title', 'My HTE - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">My HTE</h1>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@if (! $deployment)
    <div class="il-card">
        <p class="text-secondary mb-0">
            You don't have a Host Training Establishment yet. Choose your firm or agency on your
            <a href="{{ route('student.documents') }}">Application for OJT</a> - once your subject instructor accepts it,
            that HTE is recorded here automatically.
        </p>
    </div>
@else
    @if ($deployment->status === 'pending')
        @include('student._deployment-blockers', ['internship' => $deployment->internship])
    @endif
    <div class="il-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold mb-0 d-flex align-items-center gap-2"><span class="il-tile-icon sm"><i class="ti ti-building"></i></span> My Host Training Establishment</h2>
            <span class="badge rounded-pill text-bg-primary">{{ $deployment->status === 'pending' ? 'Placed - not started yet' : ucfirst($deployment->status) }}</span>
        </div>

        <div class="p-3 mb-3" style="background: #f4f6f9; border-radius: 10px;">
            <div class="fw-bold mb-1">{{ $deployment->hte->name }}</div>
            <div class="text-secondary small mb-3">{{ $deployment->hte->address ?? 'No address on file' }}</div>

            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.68rem;">Deployment date</div>
                    <div>{{ $deployment->start_date?->format('F d, Y') ?? '—' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.68rem;">Contact person</div>
                    <div>{{ $deployment->hte->contact_person ?? '—' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.68rem;">Contact</div>
                    <div>{{ $deployment->hte->contact_phone ?? $deployment->hte->contact_email ?? '—' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.68rem;">MOA status</div>
                    <div>{{ ucfirst($deployment->hte->moa_status) }}</div>
                </div>
            </div>
        </div>

        <p class="text-secondary small mb-0">
            Your OJT schedule is arranged directly with your HTE Supervisor and isn't tracked
            in the system yet — check with them for exact hours.
        </p>
    </div>
    @include('student._agency-evaluations', $evaluation)
@endif


@endsection
