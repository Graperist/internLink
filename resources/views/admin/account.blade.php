@extends('layouts.admin')

@section('title', 'My Account - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">My account</h1>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="il-card" style="max-width: 640px;">
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('admin.account.update') }}">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="il-form-label">ID number</label>
            <input type="text" class="form-control" value="{{ $user->id_number }}" disabled>
        </div>
        <div class="mb-3">
            <label class="il-form-label" for="name">Full name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required>
        </div>
        <div class="mb-3">
            <label class="il-form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $user->email) }}" required>
            <p class="il-form-hint">Use an inbox you can open: password change codes are sent here.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="il-btn-primary">Save</button>
            <a href="{{ route('password.change') }}" class="il-btn-secondary text-decoration-none"><i class="ti ti-lock"></i> Change password</a>
        </div>
    </form>
</div>
@endsection
