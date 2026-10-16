@extends('layouts.coordinator')

@section('title', 'Edit '.$hte->name.' - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Edit partner HTE</h1>
</div>

<div class="il-card" style="max-width: 760px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0">{{ $hte->name }}</h2>
        <a href="{{ route('instructor.htes') }}" class="btn btn-sm btn-outline-secondary">&larr; Back</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger small">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('coordinator.htes.update', $hte) }}">
        @csrf
        @method('PUT')
        @include('coordinator.htes._form')
        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>
</div>
@endsection
