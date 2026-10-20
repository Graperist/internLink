@extends('layouts.hte')

@section('title', 'Time Frame Approval - PSU Intern-Link')
@section('page_title', 'Time Frame Approval')

@section('content')
@php
    $fmt = fn ($h) => \App\Support\TimeFrame::format($h);
    // A day waits while its morning or afternoon still waits for approval.
    $isWaiting = fn ($d) => $d->halfStatus('am') === 'pending' || $d->halfStatus('pm') === 'pending';
    $waiting = $entries->filter($isWaiting);
    $done = $entries->reject($isWaiting);
    $halfBadge = fn ($status) => match ($status) {
        'approved' => ['Approved', 'text-bg-success'],
        'flagged' => ['Declined', 'text-bg-danger'],
        default => ['Waiting', 'text-bg-warning'],
    };
@endphp

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @foreach ([['Waiting for your approval', $waiting, true], ['Approved and declined days', $done, false]] as [$heading, $list, $actionable])
    <div class="il-card p-0 overflow-hidden mb-3">
        <div class="hte-section-heading d-flex justify-content-between align-items-center gap-3">
            <span>{{ $heading }}</span>
            <span class="text-secondary fw-normal" style="font-size:.78rem;">
                @if ($actionable)
                    Form 17 – Internship Time Frame · {{ $list->count() }} {{ \Illuminate\Support\Str::plural('day', $list->count()) }}
                @else
                    Approved hours count toward the student's required hours
                @endif
            </span>
        </div>

        <div class="table-responsive">
            <table class="table mb-0 align-middle hte-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Date</th>
                        <th>Morning</th>
                        <th>Afternoon</th>
                        <th>Hours</th>
                        <th>{{ $actionable ? 'Action' : 'Status' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($list as $dtr)
                        @php $student = $dtr->deployment->internship->student; @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="il-row-avatar">{{ collect(explode(' ', $student->user->name))->map(fn ($p) => $p[0] ?? '')->join('') }}</div>
                                    <div>
                                        <div class="fw-semibold">{{ $student->user->name }}</div>
                                        <div class="text-secondary" style="font-size:.76rem;">{{ $student->user->id_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $dtr->log_date?->format('M d, Y') }}</div>
                                <div class="text-secondary" style="font-size:.76rem;">{{ $dtr->log_date?->format('l') }}</div>
                            </td>
                            @foreach (\App\Models\Dtr::HALVES as $half => $halfName)
                                <td>
                                    @if ($dtr->hasHalf($half))
                                        @php [$halfLabel, $halfColor] = $halfBadge($dtr->halfStatus($half)); @endphp
                                        <div class="text-nowrap">{{ $dtr->period($half) }}</div>
                                        <div class="text-secondary" style="font-size:.72rem;">{{ $fmt($dtr->halfHours($half)) }} hours</div>
                                        @include('partials.dtr-photos', ['dtr' => $dtr, 'part' => $half])
                                        <div class="mt-1"><span class="badge {{ $halfColor }}">{{ $halfLabel }}</span></div>
                                        @if ($dtr->halfStatus($half) === 'flagged' && $dtr->{$half.'_decline_reason'})
                                            <div class="small mt-1">{{ $dtr->{$half.'_decline_reason'} }}</div>
                                        @endif
                                        @if ($dtr->halfStatus($half) === 'pending')
                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                <form method="POST" action="{{ route('hte.dtr.verify', [$dtr, $half]) }}">
                                                    @csrf
                                                    <button class="hte-action-btn verify" type="submit" title="Approve the {{ strtolower($halfName) }}">Approve</button>
                                                </form>
                                                <button class="hte-action-btn flag" type="button" data-bs-toggle="modal" data-bs-target="#declineDtr{{ $dtr->id }}{{ $half }}">Decline</button>
                                            </div>
                                            @include('hte._decline-half', ['dtr' => $dtr, 'half' => $half, 'halfName' => $halfName, 'student' => $student])
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                            @endforeach
                            <td class="fw-semibold">
                                {{ $fmt($dtr->hours_logged) }}
                                @if ((float) $dtr->approved_hours > 0 && (float) $dtr->approved_hours < (float) $dtr->hours_logged)
                                    <div class="text-success" style="font-size:.72rem;">{{ $fmt($dtr->approved_hours) }} approved</div>
                                @endif
                            </td>
                            <td>
                                @if ($dtr->am_notes)
                                    <div class="small text-secondary mb-1"><i class="ti ti-message"></i> <span class="fw-semibold">Morning:</span> {{ $dtr->am_notes }}</div>
                                @endif
                                @if ($dtr->notes)
                                    <div class="small text-secondary mb-1"><i class="ti ti-message"></i> <span class="fw-semibold">{{ $dtr->am_notes || $dtr->am_in ? 'Afternoon:' : '' }}</span> {{ $dtr->notes }}</div>
                                @endif
                                @if ($dtr->status === 'approved')
                                    <span class="badge text-bg-success">Approved</span>
                                @elseif ($dtr->status === 'flagged' && ! $isWaiting($dtr))
                                    <span class="badge text-bg-danger">Declined</span>
                                    <div class="small text-secondary mt-1">Waiting for the student to correct it.</div>
                                @else
                                    @if ($dtr->resolved_at)
                                        <div class="small text-secondary mb-1">Corrected by the student</div>
                                    @endif
                                    @if ($dtr->halfStatus('am') === 'pending' && $dtr->halfStatus('pm') === 'pending')
                                        <form method="POST" action="{{ route('hte.dtr.verify', $dtr) }}">
                                            @csrf
                                            <button class="hte-action-btn verify" type="submit">Approve both</button>
                                        </form>
                                    @else
                                        <span class="small text-secondary">Approve or decline the morning and the afternoon separately.</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">
                                {{ $actionable ? 'Nothing waiting. Days your interns enter appear here.' : 'No approved or declined days yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
@endsection
