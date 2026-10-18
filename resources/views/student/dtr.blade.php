@extends('layouts.student')

@section('title', 'Form 17 – Internship Time Frame - PSU Intern-Link')

@section('content')
@php
    use App\Support\TimeFrame;
    $fmt = fn ($h) => TimeFrame::format($h);
    $hm = fn ($t) => filled($t) ? \Carbon\Carbon::parse($t)->format('H:i') : '';
    $form = $editing;
    $percent = $totals['required'] > 0 ? min(100, (int) round($totals['approved'] / $totals['required'] * 100)) : 0;
@endphp

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Form 17 – Internship Time Frame</h1>
        <p class="text-secondary small mb-0">Your daily time record. Enter your morning and afternoon time in and out; the system totals the hours. Your HTE supervisor approves each day.</p>
    </div>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->has('form17'))<div class="alert alert-danger">{{ $errors->first('form17') }}</div>@endif

@if (! $deployment || $deployment->status === 'pending')
    @if ($deployment?->status === 'pending')
        @include('student._deployment-blockers', ['internship' => $deployment->internship])
    @endif
    <div class="il-card"><p class="text-secondary mb-0">Your time frame opens once your subject instructor starts your deployment. Check <a href="{{ route('student.hte') }}">My HTE Info</a>.</p></div>
@else
    {{-- Totals --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="il-card h-100">
                <div class="small text-secondary">Approved hours</div>
                <div class="h4 fw-bold mb-1">{{ $fmt($totals['approved']) }} <span class="small text-secondary fw-normal">/ {{ $fmt($totals['required']) }} required</span></div>
                <div class="progress" style="height:6px"><div class="progress-bar {{ $percent >= 100 ? 'bg-success' : '' }}" style="width: {{ $percent }}%"></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="il-card h-100">
                <div class="small text-secondary">Waiting for your supervisor</div>
                <div class="h4 fw-bold mb-0">{{ $totals['waiting'] }} <span class="small text-secondary fw-normal">{{ \Illuminate\Support\Str::plural('day', $totals['waiting']) }} · {{ $fmt($totals['pending']) }} h</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="il-card h-100 {{ $totals['declined'] ? 'border border-danger' : '' }}">
                <div class="small text-secondary">Declined - correct and resend</div>
                <div class="h4 fw-bold mb-0 {{ $totals['declined'] ? 'text-danger' : '' }}">{{ $totals['declined'] }} <span class="small text-secondary fw-normal">{{ \Illuminate\Support\Str::plural('day', $totals['declined']) }}</span></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Enter / correct a day: the date, then the morning and the afternoon, each saved on its own --}}
        <div class="col-lg-4">
            <div style="position: sticky; top: 1rem;" id="dayForm">
                @php
                    $locked = $form?->status === 'approved';
                    $maxDate = min(today()->toDateString(), $deployment->end_date?->toDateString() ?? today()->toDateString());
                @endphp
                <div class="il-card mb-3">
                    <form method="GET" action="{{ route('student.dtr') }}" id="dateForm">
                        <label class="il-form-label" for="log_date">Date</label>
                        <input type="date" class="form-control @error('log_date') is-invalid @enderror" id="log_date" name="date"
                               value="{{ old('log_date', $selectedDate) }}"
                               min="{{ $deployment->start_date?->toDateString() }}" max="{{ $maxDate }}" required>
                        @error('log_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </form>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small text-secondary" id="dayName">{{ \Carbon\Carbon::parse($selectedDate)->format('l') }}</span>
                        @if ($form)
                            <span class="badge rounded-pill {{ ['approved' => 'text-bg-success', 'flagged' => 'text-bg-danger'][$form->status] ?? 'text-bg-warning' }}">{{ $form->statusLabel() }}</span>
                        @else
                            <span class="badge rounded-pill text-bg-light text-secondary border">Not entered yet</span>
                        @endif
                    </div>
                    @if ($form?->status === 'flagged')
                        <div class="alert alert-danger small py-2 mt-2 mb-0">
                            <strong>Declined{{ $form->decliner ? ' by '.$form->decliner->name : '' }}:</strong> {{ $form->flag_reason }}
                            @if ($form->flag_note)<div class="mt-1">{{ $form->flag_note }}</div>@endif
                            <div class="mt-1">Correct the morning or afternoon below and save it again.</div>
                        </div>
                    @elseif ($locked)
                        <div class="small text-success mt-2"><i class="ti ti-lock"></i> Approved by your supervisor - this day can no longer be changed.</div>
                    @endif
                    <div class="d-flex justify-content-between align-items-center border rounded p-2 mt-3 bg-light">
                        <span class="small text-secondary">Total for the day</span>
                        <span class="fw-bold" id="dayTotal">0 hours</span>
                    </div>
                    <div class="small text-secondary mt-1">The lunch break between the morning and the afternoon is not counted.</div>
                </div>

                @if ($deployment->status !== 'active')
                    <div class="il-card"><p class="small text-secondary mb-0">Your deployment has ended, so no more days can be added.</p></div>
                @else
                    @foreach (['am' => ['Morning', 'ti-sun'], 'pm' => ['Afternoon', 'ti-sun-low']] as $p => [$label, $icon])
                        @php
                            $saved = $form && filled($form->{$p.'_in'});
                            $failedHere = old('part') === $p;
                        @endphp
                        <div class="il-card mb-3">
                            <form method="POST" action="{{ route('student.dtr.store') }}" class="js-half" data-part="{{ $p }}">
                                @csrf
                                <input type="hidden" name="part" value="{{ $p }}">
                                <input type="hidden" name="log_date" value="{{ $selectedDate }}">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h2 class="h6 fw-bold mb-0"><i class="ti {{ $icon }}"></i> {{ $label }}</h2>
                                    <span class="small fw-semibold" id="{{ $p }}Total"></span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small mb-1" for="{{ $p }}_in">Time in</label>
                                        <input type="time" class="form-control @if($failedHere) @error($p.'_in') is-invalid @enderror @endif js-time" id="{{ $p }}_in" name="{{ $p }}_in"
                                               value="{{ $failedHere ? old($p.'_in') : $hm($form?->{$p.'_in'}) }}" @disabled($locked)>
                                        @if ($failedHere) @error($p.'_in')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-1" for="{{ $p }}_out">Time out</label>
                                        <input type="time" class="form-control @if($failedHere) @error($p.'_out') is-invalid @enderror @endif js-time" id="{{ $p }}_out" name="{{ $p }}_out"
                                               value="{{ $failedHere ? old($p.'_out') : $hm($form?->{$p.'_out'}) }}" @disabled($locked)>
                                        @if ($failedHere) @error($p.'_out')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                                    </div>
                                </div>
                                @if ($p === 'pm')
                                    <div class="mt-2">
                                        <label class="form-label small mb-1" for="notes">Note for your supervisor <span class="text-secondary">(optional)</span></label>
                                        <input type="text" class="form-control form-control-sm" id="notes" name="notes" maxlength="500" value="{{ old('notes', $form?->notes) }}" placeholder="e.g. Left early for a medical check-up" @disabled($locked)>
                                    </div>
                                @endif
                                @unless ($locked)
                                    <button type="submit" class="btn btn-primary w-100 mt-3">
                                        <i class="ti ti-send"></i> {{ $saved ? 'Save '.strtolower($label).' and resend' : 'Save '.strtolower($label) }}
                                    </button>
                                @endunless
                            </form>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- The days --}}
        <div class="col-lg-8">
            <div class="il-card mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 fw-bold mb-0">My days</h2>
                    <span class="small text-secondary">{{ $days->count() }} {{ \Illuminate\Support\Str::plural('day', $days->count()) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead>
                            <tr class="text-secondary" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;">
                                <th>Date</th><th>Morning</th><th>Afternoon</th><th class="text-end">Hours</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($days as $day)
                                <tr class="{{ $day->status === 'flagged' ? 'table-danger' : '' }} {{ $form?->id === $day->id ? 'table-active' : '' }}">
                                    <td class="text-nowrap"><div class="fw-semibold">{{ $day->log_date->format('M d, Y') }}</div><div class="text-secondary">{{ $day->log_date->format('l') }}</div></td>
                                    <td class="text-nowrap">{{ $day->period('am') ?: '—' }}</td>
                                    <td class="text-nowrap">{{ $day->period('pm') ?: '—' }}</td>
                                    <td class="text-end fw-semibold">{{ $fmt($day->hours_logged) }}</td>
                                    <td>
                                        <span class="badge rounded-pill {{ ['approved' => 'text-bg-success', 'flagged' => 'text-bg-danger'][$day->status] ?? 'text-bg-warning' }}">{{ $day->statusLabel() }}</span>
                                        @if ($day->status === 'flagged' && $day->flag_reason)
                                            <div class="text-danger mt-1" style="font-size:.75rem;">{{ $day->flag_reason }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if ($day->status !== 'approved' && $deployment->status === 'active')
                                            <a href="{{ route('student.dtr', ['date' => $day->log_date->toDateString()]) }}#dayForm" class="btn btn-sm {{ $day->status === 'flagged' ? 'btn-danger' : 'btn-outline-secondary' }} px-2" title="{{ $day->status === 'flagged' ? 'Correct' : 'Edit' }}"><i class="ti ti-pencil"></i></a>
                                            <form method="POST" action="{{ route('student.dtr.destroy', $day) }}" class="d-inline" onsubmit="return confirm('Remove {{ $day->log_date->format('M d, Y') }}?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-1" title="Remove"><i class="ti ti-trash"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-secondary py-4">No days yet. Add your first day on the left.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($days->isNotEmpty())
                            <tfoot>
                                <tr><td colspan="3" class="text-end fw-semibold">Total approved</td><td class="text-end fw-bold">{{ $fmt($totals['approved']) }}</td><td colspan="2"></td></tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            {{-- Printing Form 17 --}}
            <div class="il-card">
                <h2 class="h6 fw-bold mb-1">Print Form 17</h2>
                @php $status = $form17?->status; @endphp
                @if (! $form17 || in_array($status, ['draft', 'returned'], true))
                    @if ($status === 'returned' && $form17->latestReview?->remarks)
                        <div class="alert alert-danger small py-2">Returned by your instructor: {{ $form17->latestReview->remarks }}</div>
                    @endif
                    <p class="small text-secondary mb-2">When your supervisor has approved your days, send Form 17 to your instructor. After they approve it for printing, download it, have it signed and upload the signed copy.</p>
                    <form method="POST" action="{{ route('student.dtr.submit') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary" @disabled($totals['approved'] <= 0)><i class="ti ti-send"></i> Send Form 17 to my instructor</button>
                    </form>
                @else
                    <p class="small mb-2">
                        <strong>{{ match ($status) { 'submitted', 'for_review' => 'With your instructor for review.', 'printed' => 'Approved for printing - download it, have it signed, then upload the signed copy.', 'completed' => 'Signed copy uploaded - waiting for your instructor\'s approval.', 'approved' => 'Approved.', default => ucfirst($status) } }}</strong>
                    </p>
                    <a href="{{ route('student.documents', ['form' => $requirement->id]) }}" class="btn btn-sm btn-outline-primary">Open in My Documents</a>
                @endif
            </div>
        </div>
    </div>

    <script>
    (function () {
        const f = id => document.getElementById(id);
        const mins = v => { if (!v) return null; const [h, m] = v.split(':').map(Number); return h * 60 + m; };
        const fmt = m => { const h = Math.round(m / 60 * 100) / 100; return h + (h === 1 ? ' hour' : ' hours'); };
        function update() {
            let total = 0;
            ['am', 'pm'].forEach(p => {
                const s = mins(f(p + '_in')?.value), e = mins(f(p + '_out')?.value);
                const part = (s !== null && e !== null && e > s) ? e - s : 0;
                total += part;
                if (f(p + 'Total')) f(p + 'Total').textContent = part ? fmt(part) : '';
            });
            if (f('dayTotal')) f('dayTotal').textContent = fmt(total);
        }
        document.querySelectorAll('.js-time').forEach(el => el.addEventListener('input', update));
        // Picking another date opens that day in the Morning and Afternoon cards.
        f('log_date')?.addEventListener('change', e => { if (e.target.value) f('dateForm').submit(); });
        update();
    })();
    </script>
@endif
@endsection
