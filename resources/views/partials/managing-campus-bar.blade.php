{{-- Shown while the Super Admin is working in a campus as its OJT Coordinator. --}}
@if ($manager = \App\Support\CampusManager::superAdmin())
    <div class="il-manage-bar" role="status">
        <i class="ti ti-shield-check"></i>
        <span><strong>Super Admin:</strong> you're managing <strong>{{ auth()->user()->campus?->name }}</strong> as its OJT Coordinator ({{ auth()->user()->name }}).</span>
        <form method="POST" action="{{ route('admin.campuses.leave') }}" class="ms-auto">
            @csrf
            <button type="submit" class="btn btn-sm btn-light fw-semibold"><i class="ti ti-arrow-back-up"></i> Back to Super Admin</button>
        </form>
    </div>
    <style>
        .il-manage-bar { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; background: #1c3f66; color: #fff; border-radius: 10px; padding: .55rem .6rem .55rem .9rem; margin-bottom: 1rem; font-size: .88rem; }
        .il-manage-bar > i { font-size: 1.2rem; color: #ffc928; }
    </style>
@endif
