@extends('layouts.student')

@section('title', 'Evaluate Agency - PSU Intern-Link')

@section('content')
<style>
    .eval-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #6c757d; }
    .eval-done { color: #1f9d3a; font-weight: 700; letter-spacing: .02em; font-size: .85rem; }
</style>
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Evaluate Agency</h1>
        <p class="text-secondary small mb-0">Your evaluation goes to your Subject Instructor. Your agency does not see it.</p>
    </div>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="il-card">
    @if (! $deployment?->hte)
        <p class="text-secondary mb-0">You have no agency yet.</p>
    @elseif (! $completed)
        <div class="fw-semibold">{{ $deployment->hte->name }}</div>
        <p class="text-secondary small mb-0 mt-1"><i class="ti ti-lock"></i> You can evaluate your agency once your internship is completed.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0 eval-table">
                <thead>
                    <tr><th>Agency</th><th>Evaluation</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                @if ($row->done)
                                    <span class="fw-semibold">{{ $deployment->hte->name }}</span>
                                @else
                                    <a href="{{ route('student.documents.fill', $row->requirement) }}" class="fw-semibold">{{ $deployment->hte->name }}</a>
                                @endif
                                <div class="text-secondary" style="font-size:.76rem;">{{ $deployment->hte->address }}</div>
                            </td>
                            <td class="small">{{ $row->requirement->name }}</td>
                            <td class="text-nowrap">
                                @if ($row->done)
                                    <span class="eval-done">EVALUATED</span>
                                @else
                                    <a href="{{ route('student.documents.fill', $row->requirement) }}" class="btn btn-sm btn-primary"><i class="ti ti-star"></i> Evaluate</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
