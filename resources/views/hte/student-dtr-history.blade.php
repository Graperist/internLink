@extends('layouts.hte')

@section('title', 'Time Frame - PSU Intern-Link')
@section('page_title', 'Internship Time Frame')

@section('content')
    <div class="il-card mb-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="il-row-avatar" style="width:48px;height:48px;font-size:1rem;">
                {{ collect(explode(' ', $student->user->name))->map(fn ($p) => $p[0] ?? '')->join('') }}
            </div>
            <div>
                <div class="fw-bold h6 mb-0">{{ $student->user->name }}</div>
                <div class="text-secondary small">
                    {{ $student->user->id_number }}
                    @if($student->program)
                        &middot; {{ $student->program->code }}
                    @endif
                </div>
            </div>
            <div class="ms-auto text-end">
                <div class="fw-semibold text-primary">{{ \App\Support\TimeFrame::format($entries->sum(fn ($d) => $d->countedHours())) }} approved hours</div>
                <div class="text-secondary small">{{ $entries->count() }} {{ \Illuminate\Support\Str::plural('day', $entries->count()) }}</div>
            </div>
        </div>
    </div>

    <div class="il-card p-0 overflow-hidden">
        <div class="hte-section-heading d-flex justify-content-between align-items-center gap-3">
            <span>Form 17 – Internship Time Frame</span>
            <span class="text-secondary fw-normal" style="font-size:.78rem;">
                Days entered at {{ $deployment->hte?->name ?? 'this HTE' }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table mb-0 align-middle hte-table">
                <thead>
                    <tr><th>Date</th><th>Morning</th><th>Afternoon</th><th>Hours</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($entries as $dtr)
                        <tr>
                            <td><div class="fw-semibold">{{ $dtr->log_date?->format('M d, Y') }}</div><div class="text-secondary" style="font-size:.76rem;">{{ $dtr->log_date?->format('l') }}</div></td>
                            <td>{{ $dtr->period('am') ?: '—' }}@include('partials.dtr-photos', ['dtr' => $dtr, 'part' => 'am'])</td>
                            <td>{{ $dtr->period('pm') ?: '—' }}@include('partials.dtr-photos', ['dtr' => $dtr, 'part' => 'pm'])</td>
                            <td class="fw-semibold">{{ \App\Support\TimeFrame::format($dtr->hours_logged) }}</td>
                            <td>
                                <span class="badge {{ ['approved' => 'text-bg-success', 'flagged' => 'text-bg-danger'][$dtr->status] ?? 'text-bg-warning' }}">{{ $dtr->statusLabel() }}</span>
                                @if ($dtr->status === 'flagged' && $dtr->flag_reason)<div class="small text-secondary mt-1">{{ $dtr->flag_reason }}</div>@endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-5">
                                No days entered yet for this student.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
