@extends('layouts.student')

@section('title', 'Form 17 – Internship Time Frame - PSU Intern-Link')

@section('content')
@php
    use App\Support\TimeFrame;
    $fmt = fn ($h) => TimeFrame::format($h);
    $form = $editing;
    $percent = $totals['required'] > 0 ? min(100, (int) round($totals['rendered'] / $totals['required'] * 100)) : 0;
    $remaining = max(0, $totals['required'] - $totals['rendered']);
@endphp

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Form 17 – Internship Time Frame</h1>
        <p class="text-secondary small mb-0">Enter how many hours you finished each day. They are added to your rendered hours right away.</p>
    </div>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

@if (! $deployment || $deployment->status === 'pending')
    @if ($deployment?->status === 'pending')
        @include('student._deployment-blockers', ['internship' => $deployment->internship])
    @endif
    <div class="il-card"><p class="text-secondary mb-0">Your time frame opens once your subject instructor starts your deployment. Check <a href="{{ route('student.hte') }}">My HTE</a>.</p></div>
@else
    {{-- Totals --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="il-card h-100">
                <div class="small text-secondary d-flex align-items-center gap-2"><span class="il-tile-icon sm"><i class="ti ti-clock-check"></i></span> Rendered hours</div>
                <div class="h4 fw-bold mb-1 mt-2">{{ $fmt($totals['rendered']) }} <span class="small text-secondary fw-normal">/ {{ $fmt($totals['required']) }} required</span></div>
                <div class="progress" style="height:6px"><div class="progress-bar {{ $percent >= 100 ? 'bg-success' : '' }}" style="width: {{ $percent }}%"></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="il-card h-100">
                <div class="small text-secondary d-flex align-items-center gap-2"><span class="il-tile-icon sm"><i class="ti ti-hourglass"></i></span> Hours left</div>
                <div class="h4 fw-bold mb-0 mt-2">{{ $fmt($remaining) }} <span class="small text-secondary fw-normal">{{ $percent }}% done</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="il-card h-100">
                <div class="small text-secondary d-flex align-items-center gap-2"><span class="il-tile-icon sm"><i class="ti ti-calendar-stats"></i></span> Days entered</div>
                <div class="h4 fw-bold mb-0 mt-2">{{ $totals['days'] }} <span class="small text-secondary fw-normal">{{ \Illuminate\Support\Str::plural('day', $totals['days']) }}</span></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Enter a day's finished hours --}}
        <div class="col-lg-4">
            <div style="position: sticky; top: 1rem;" id="dayForm">
                @if ($deployment->status !== 'active')
                    <div class="il-card"><p class="small text-secondary mb-0">Your deployment has ended, so no more days can be added.</p></div>
                @else
                    @php $maxDate = min(today()->toDateString(), $deployment->end_date?->toDateString() ?? today()->toDateString()); @endphp
                    <div class="il-card">
                        <h2 class="h6 fw-bold mb-3">{{ $form ? 'Edit '.$form->log_date->format('M d, Y') : 'Add a day' }}</h2>
                        <form method="POST" action="{{ route('student.dtr.store') }}">
                            @csrf
                            @if ($form)<input type="hidden" name="dtr_id" value="{{ $form->id }}">@endif

                            <label class="il-form-label" for="log_date">Date</label>
                            <input type="date" class="form-control @error('log_date') is-invalid @enderror" id="log_date" name="log_date"
                                   value="{{ old('log_date', $selectedDate) }}"
                                   min="{{ $deployment->start_date?->toDateString() }}" max="{{ $maxDate }}" required>
                            @error('log_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="small text-secondary mt-1 mb-3" id="dayName">{{ \Carbon\Carbon::parse(old('log_date', $selectedDate))->format('l') }}</div>

                            <label class="il-form-label" for="hours">Hours finished</label>
                            <div class="input-group">
                                <input type="number" class="form-control @error('hours') is-invalid @enderror" id="hours" name="hours"
                                       min="0.25" max="24" step="0.25" inputmode="decimal" placeholder="e.g. 8"
                                       value="{{ old('hours', $form ? $fmt($form->hours_logged) : '') }}" required>
                                <span class="input-group-text">hours</span>
                                @error('hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="small text-secondary mt-1 mb-3">Use .5 for half an hour, e.g. 7.5.</div>

                            <label class="il-form-label" for="notes">Note <span class="text-secondary fw-normal">(optional)</span></label>
                            <input type="text" class="form-control" id="notes" name="notes" maxlength="500"
                                   value="{{ old('notes', $form?->notes) }}" placeholder="e.g. Half day for a medical check-up">

                            <button type="submit" class="btn btn-primary w-100 mt-3">
                                <i class="ti ti-device-floppy"></i> {{ $form ? 'Save changes' : 'Save day' }}
                            </button>
                            @if ($form)
                                <a href="{{ route('student.dtr') }}" class="btn btn-link w-100 btn-sm mt-1 text-decoration-none">Add a different day instead</a>
                            @endif
                        </form>
                    </div>
                @endif

                @if ($days->isNotEmpty())
                    <div class="il-card mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h2 class="h6 fw-bold mb-0 d-flex align-items-center gap-2"><span class="il-tile-icon sm"><i class="ti ti-calendar"></i></span> My days</h2>
                            <span class="small text-secondary">{{ $fmt($totals['rendered']) }} hours</span>
                        </div>
                        <ul class="list-unstyled mb-0 small" style="max-height: 340px; overflow-y: auto;">
                            @foreach ($days as $day)
                                <li class="d-flex align-items-center gap-2 py-2 border-top {{ $form?->id === $day->id ? 'bg-body-tertiary' : '' }}">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">{{ $day->log_date->format('M d, Y') }} <span class="text-secondary fw-normal">{{ $day->log_date->format('D') }}</span></div>
                                        @if ($day->notes)<div class="text-secondary text-truncate" style="max-width: 200px;">{{ $day->notes }}</div>@endif
                                    </div>
                                    <span class="fw-semibold">{{ $fmt($day->countedHours()) }} h</span>
                                    @if ($deployment->status === 'active')
                                        <a href="{{ route('student.dtr', ['edit' => $day->id]) }}#dayForm" class="btn btn-sm btn-outline-secondary px-2" title="Edit"><i class="ti ti-pencil"></i></a>
                                        <form method="POST" action="{{ route('student.dtr.destroy', $day) }}" class="d-inline" onsubmit="return confirm('Remove {{ $day->log_date->format('M d, Y') }}? Its hours come off your rendered hours.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-1" title="Remove"><i class="ti ti-trash"></i></button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        {{-- Preview of the printable Form 17, filled from the days entered --}}
        <div class="col-lg-8">
            @include('student._form17-card', ['days' => $days, 'internship' => $internship])
        </div>
    </div>


    <script>
        // Shows the weekday of the chosen date.
        (function () {
            var input = document.getElementById('log_date'), out = document.getElementById('dayName');
            if (!input || !out) return;
            input.addEventListener('change', function () {
                if (!input.value) { out.textContent = ''; return; }
                out.textContent = new Date(input.value + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
            });
        })();
    </script>
@endif
@endsection
