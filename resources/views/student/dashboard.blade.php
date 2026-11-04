@extends('layouts.student')

@section('title', 'My Dashboard - PSU Intern-Link')

@section('content')
<style>
    .il-card-link {
        display: block;
        text-decoration: none;
        color: inherit;
        transition: box-shadow .15s ease, transform .15s ease;
    }
    .il-card-link:hover,
    .il-card-link:focus {
        box-shadow: 0 .35rem .9rem rgba(0, 0, 0, .08);
        transform: translateY(-1px);
        color: inherit;
    }
    .il-row-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none;
        color: inherit;
        border-radius: .375rem;
        margin: 0 -.5rem;
        padding: .5rem;
        transition: background-color .15s ease;
    }
    .il-row-link:hover {
        background-color: rgba(0, 0, 0, .035);
        color: inherit;
    }
</style>

<div class="il-page-header">
    <h1 class="il-page-title">My dashboard</h1>
</div>

<div class="il-hero mb-3">
    <p class="il-hero-title">Good morning, {{ explode(' ', $student->user->name)[0] }}!</p>
    <p class="il-hero-subtitle">
        You have {{ $hoursRendered }} hours rendered. Keep it up — you're {{ $hoursPercent }}% done!
    </p>
</div>



<div class="row g-3 mb-3">
    <div class="col-md-3">
        <a href="{{ route('student.dtr') }}" class="il-card il-card-link d-flex align-items-center gap-3 py-3 h-100">
            <span class="il-tile-icon"><i class="ti ti-clock-check"></i></span>
            <div><div class="il-stat-number">{{ $hoursRendered }}</div>
            <div class="text-secondary small">Hours Rendered</div></div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('student.progress') }}" class="il-card il-card-link d-flex align-items-center gap-3 py-3 h-100">
            <span class="il-tile-icon"><i class="ti ti-target"></i></span>
            <div><div class="il-stat-number">{{ $requiredHours }}</div>
            <div class="text-secondary small">Required Hours</div></div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('student.dtr') }}" class="il-card il-card-link d-flex align-items-center gap-3 py-3 h-100">
            <span class="il-tile-icon"><i class="ti ti-hourglass"></i></span>
            <div><div class="il-stat-number">{{ $hoursRemaining }}</div>
            <div class="text-secondary small">Hours Remaining</div></div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('student.documents') }}" class="il-card il-card-link d-flex align-items-center gap-3 py-3 h-100">
            <span class="il-tile-icon"><i class="ti ti-files"></i></span>
            <div><div class="il-stat-number">{{ $submittedCount }}/{{ $checklist->count() }}</div>
            <div class="text-secondary small">Documents Submitted</div></div>
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="il-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0 d-flex align-items-center gap-2"><span class="il-tile-icon sm"><i class="ti ti-history"></i></span> Recent Updates</h2>
                <a href="{{ route('student.documents') }}" class="small fw-semibold text-decoration-none">My Documents &rarr;</a>
            </div>

            @php
                $where = fn ($doc) => match ($doc->status) {
                    'submitted', 'for_review' => ['With your instructor', 'text-warning-emphasis', 'ti-clock'],
                    'returned' => ['Returned - correct it', 'text-danger', 'ti-arrow-back-up'],
                    'printed' => ['Print, sign & upload', 'text-primary', 'ti-printer'],
                    'completed' => filled($doc->file_path) ? ['Signed copy - with your instructor', 'text-warning-emphasis', 'ti-signature'] : ['Upload the signed copy', 'text-primary', 'ti-upload'],
                    'approved' => ['Approved', 'text-success', 'ti-circle-check'],
                    default => [ucfirst((string) $doc->status), 'text-secondary', 'ti-point'],
                };
            @endphp
            @forelse ($recentSubmissions as $doc)
                @php [$label, $color, $icon] = $where($doc); @endphp
                <a href="{{ $doc->requirement->is_repeating ? route('student.documents.log', $doc->requirement) : route('student.documents', ['form' => $doc->document_requirement_id]) }}"
                   class="il-row-link {{ ! $loop->last ? 'border-bottom' : '' }}">
                    <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                        <i class="ti {{ $icon }} {{ $color }}"></i>
                        <span class="text-truncate">{{ $doc->requirement->name }}{{ $doc->packet_number ? ' · Week '.$doc->packet_number : '' }}</span>
                    </div>
                    <div class="text-end flex-shrink-0 ms-2">
                        <div class="small fw-semibold {{ $color }}">{{ $label }}</div>
                        <div class="text-secondary" style="font-size: .75rem;">Submitted {{ $doc->submitted_at?->format('M d') }}</div>
                    </div>
                </a>
            @empty
                <p class="text-center text-secondary py-4 mb-0">
                    Nothing submitted yet. Start with your forms in <a href="{{ route('student.documents') }}">My Documents</a>.
                </p>
            @endforelse
        </div>
    </div>

    <div class="col-lg-4">
        <a href="{{ route('student.progress') }}" class="il-card il-card-link d-block">
            <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><span class="il-tile-icon sm"><i class="ti ti-chart-donut"></i></span> Hours Progress</h2>
            <div class="text-center mb-3">
                <div class="il-stat-number" style="font-size: 2.5rem;">{{ $hoursPercent }}%</div>
                <div class="text-secondary small">{{ $hoursPercent }}% of required {{ $requiredHours }} hours completed</div>
            </div>
            <div class="progress mb-1" style="height: 10px;">
                <div class="progress-bar bg-success" style="width: {{ $hoursPercent }}%"></div>
            </div>
            <div class="d-flex justify-content-between small text-secondary">
                <span>Overall</span>
                <span>{{ $hoursRendered }} / {{ $requiredHours }}h</span>
            </div>
        </a>
    </div>
</div>
@endsection
