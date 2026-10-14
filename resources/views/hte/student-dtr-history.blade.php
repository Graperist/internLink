@extends('layouts.hte')

@section('title', 'DTR History - PSU Intern-Link')
@section('page_title', 'DTR History')

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
                <div class="fw-semibold text-primary">{{ number_format($totalHours, 1) }}h logged</div>
                <div class="text-secondary small">{{ $entries->count() }} DTR {{ \Illuminate\Support\Str::plural('entry', $entries->count()) }}</div>
            </div>
        </div>
    </div>

    <div class="il-card p-0 overflow-hidden">
        <div class="hte-section-heading d-flex justify-content-between align-items-center gap-3">
            <span>Time In / Out History</span>
            <span class="text-secondary fw-normal" style="font-size:.78rem;">
                Every clock-in/clock-out recorded at {{ $deployment->hte?->name ?? 'this HTE' }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table mb-0 align-middle hte-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Hours</th>
                        <th>Location Check</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $dtr)
                        <tr>
                            <td>{{ $dtr->log_date?->format('M d, Y') }}</td>

                            <td>
                                <div class="fw-semibold">
                                    {{ $dtr->time_in ? \Carbon\Carbon::parse($dtr->time_in)->format('g:i A') : '—' }}
                                </div>
                                @if ($dtr->original_time_in)
                                    <div class="small text-danger">
                                        Original: {{ \Carbon\Carbon::parse($dtr->original_time_in)->format('g:i A') }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    {{ $dtr->time_out ? \Carbon\Carbon::parse($dtr->time_out)->format('g:i A') : '—' }}
                                </div>
                                @if ($dtr->original_time_out)
                                    <div class="small text-danger">
                                        Original: {{ \Carbon\Carbon::parse($dtr->original_time_out)->format('g:i A') }}
                                    </div>
                                @endif
                            </td>

                            <td class="fw-semibold">
                                {{ $dtr->hours_logged !== null ? number_format($dtr->hours_logged, 1).'h' : '—' }}
                            </td>

                            <td class="small">
                                @if ($dtr->hasLocationRisk())
                                    <span class="badge text-bg-warning">Needs review</span>
                                @elseif ($dtr->clock_in_distance_m !== null || $dtr->clock_out_distance_m !== null)
                                    <span class="badge text-bg-success">Passed</span>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>

                            <td>
                                @if ($dtr->status === 'approved')
                                    <span class="badge text-bg-success">Verified</span>
                                @elseif ($dtr->status === 'flagged')
                                    <span class="badge text-bg-danger">Flagged</span>
                                    @if ($dtr->flagTargetLabel())
                                        <div class="small mt-1"><strong>Flagged:</strong> {{ $dtr->flagTargetLabel() }}</div>
                                    @endif
                                    @if ($dtr->flag_reason)
                                        <div class="small text-secondary mt-1">{{ $dtr->flag_reason }}</div>
                                    @endif
                                @elseif (! $dtr->time_in || ! $dtr->time_out)
                                    <span class="badge text-bg-secondary">Incomplete</span>
                                @else
                                    <span class="badge text-bg-warning">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-5">
                                No DTR entries recorded yet for this student.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
