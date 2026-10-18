@extends('layouts.hte')

@section('title', 'My Profile - PSU Intern-Link')
@section('page_title', 'My Profile')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger small">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="il-card">
                <h2 class="h6 fw-bold mb-3">Company Profile</h2>

                <form method="POST" action="{{ route('hte.profile.company') }}">
                    @csrf
                    @method('PUT')

                    <label class="form-label small fw-semibold">HTE name</label>
                    <input
                        type="text"
                        name="name"
                        id="hteName"
                        class="form-control mb-3"
                        value="{{ old('name', $representative->hte->name) }}"
                        required
                    >

                    <label class="form-label small fw-semibold">Address</label>
                    <input
                        type="text"
                        name="address"
                        id="hteAddress"
                        class="form-control mb-3"
                        value="{{ old('address', $representative->hte->address) }}"
                    >

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact person</label>
                            <input
                                type="text"
                                name="contact_person"
                                class="form-control"
                                value="{{ old('contact_person', $representative->hte->contact_person) }}"
                            >
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact email</label>
                            <input
                                type="email"
                                name="contact_email"
                                class="form-control"
                                value="{{ old('contact_email', $representative->hte->contact_email) }}"
                            >
                        </div>
                    </div>

                    <label class="form-label small fw-semibold">Contact phone</label>
                    <input
                        type="text"
                        name="contact_phone"
                        class="form-control mb-4"
                        value="{{ old('contact_phone', $representative->hte->contact_phone) }}"
                    >


                    <button type="submit" class="btn btn-primary">Save company profile</button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="il-card">
                <h2 class="h6 fw-bold mb-3">My Account</h2>

                <form method="POST" action="{{ route('hte.profile.account') }}">
                    @csrf
                    @method('PUT')

                    <label class="form-label small fw-semibold">Full name</label>
                    <input
                        type="text"
                        name="name"
                        class="form-control mb-3"
                        value="{{ old('name', $representative->user->name) }}"
                        required
                    >

                    <label class="form-label small fw-semibold">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control mb-3"
                        value="{{ old('email', $representative->user->email) }}"
                        required
                    >

                    <label class="form-label small fw-semibold">ID number</label>
                    <input
                        type="text"
                        class="form-control mb-4"
                        value="{{ $representative->user->id_number }}"
                        disabled
                    >
                    <p class="small text-secondary mt-n3 mb-4">
                        Your ID number is set by your OJT Coordinator and can't be changed here.
                    </p>

                    <button type="submit" class="btn btn-primary">Save account details</button>
                </form>
            </div>

            <div class="mt-3">
                @include('partials.profile-photo-card', ['prefix' => 'hte'])
            </div>

            <div class="il-card mt-3">
                <h2 class="h6 fw-bold mb-2">Password</h2>
                <p class="small text-secondary mb-3">
                    Change the password you use to sign in.
                </p>
                <a href="{{ route('password.change') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-lock"></i> Change password
                </a>
            </div>
        </div>
    </div>

@endsection
