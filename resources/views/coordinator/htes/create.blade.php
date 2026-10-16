@extends('layouts.coordinator')

@section('title', 'Add Partner HTE - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Add partner HTE</h1>
</div>

<div class="il-card" style="max-width: 560px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">New Host Training Establishment</h2>
        <a href="{{ route('instructor.htes') }}" class="btn btn-sm btn-outline-secondary">&larr; Back</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger small">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('coordinator.htes.store') }}">
        @csrf
        @include('coordinator.htes._form')
        <button type="submit" class="btn btn-primary">Add HTE</button>
    </form>
</div>
@endsection
