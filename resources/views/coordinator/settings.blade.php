@extends('layouts.coordinator')

@section('title', 'Settings - PSU Intern-Link')

@section('content')
@php
    $items = [
        ['route' => 'coordinator.campus-settings.edit', 'icon' => 'ti-building', 'title' => 'Campus Settings',
            'text' => 'The Campus Executive Director, College Dean and Registrar printed on the forms.'],
        ['route' => 'instructor.audit-trail', 'icon' => 'ti-list-details', 'title' => 'Audit Trail',
            'text' => 'Who did what in the system, and when.'],
    ];
@endphp
<style>
    .st-list { max-width: 640px; padding: .4rem; }
    .st-item { display: flex; align-items: center; gap: .9rem; padding: .75rem .8rem; border-radius: 10px; color: #14213a; text-decoration: none; }
    .st-item:hover, .st-item:focus-visible { background: #f1f4f9; color: #14213a; }
    .st-icon { width: 42px; height: 42px; border-radius: 50%; background: #e8eef7; color: #1c3f66; display: grid; place-items: center; font-size: 1.25rem; flex: none; }
    .st-title { font-weight: 700; font-size: .98rem; }
    .st-text { color: #5d6b80; font-size: .82rem; }
    .st-chev { margin-left: auto; color: #9aa6b8; font-size: 1.1rem; }
</style>

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Settings</h1>
        <p class="text-secondary small mb-0">System settings for {{ auth()->user()->campus?->name ?? 'your campus' }}. Only the OJT Coordinator can see these.</p>
    </div>
</div>

<div class="il-card st-list">
    @foreach ($items as $item)
        <a href="{{ route($item['route']) }}" class="st-item">
            <span class="st-icon"><i class="ti {{ $item['icon'] }}"></i></span>
            <span>
                <span class="st-title d-block">{{ $item['title'] }}</span>
                <span class="st-text d-block">{{ $item['text'] }}</span>
            </span>
            <i class="ti ti-chevron-right st-chev"></i>
        </a>
    @endforeach
</div>
@endsection
