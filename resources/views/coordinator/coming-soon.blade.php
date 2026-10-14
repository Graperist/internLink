@extends('layouts.coordinator')

@section('title', $title.' - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">{{ $title }}</h1>
</div>

<div class="il-card text-center py-5">
    <i class="ti {{ $icon ?? 'ti-tools' }}" style="font-size: 2.25rem; color: var(--il-navy-field-border);"></i>
    <h2 class="h6 fw-bold mt-3 mb-1">{{ $title }} is coming soon</h2>
    <p class="text-secondary small mb-0">
        {{ $description ?? 'This module is still being built.' }}
    </p>
</div>
@endsection
