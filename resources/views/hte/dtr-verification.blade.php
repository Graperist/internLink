@extends('layouts.hte')

@section('title', 'Time Frame Approval - PSU Intern-Link')
@section('page_title', 'Time Frame Approval')

@section('content')
@php
    $fmt = fn ($h) => \App\Support\TimeFrame::format($h);
    $waiting = $entries->where('status', 'pending');
    $done = $entries->where('status', '!=', 'pending');
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
                            <td>{{ $dtr->period('am') ?: '—' }}@include('partials.dtr-photos', ['dtr' => $dtr, 'part' => 'am'])</td>
                            <td>{{ $dtr->period('pm') ?: '—' }}@include('partials.dtr-photos', ['dtr' => $dtr, 'part' => 'pm'])</td>
                            <td class="fw-semibold">{{ $fmt($dtr->hours_logged) }}</td>
                            <td>
                                @if ($dtr->am_notes)
                                    <div class="small text-secondary mb-1"><i class="ti ti-message"></i> <span class="fw-semibold">Morning:</span> {{ $dtr->am_notes }}</div>
                                @endif
                                @if ($dtr->notes)
                                    <div class="small text-secondary mb-1"><i class="ti ti-message"></i> <span class="fw-semibold">{{ $dtr->am_notes || $dtr->am_in ? 'Afternoon:' : '' }}</span> {{ $dtr->notes }}</div>
                                @endif
                                @if ($dtr->status === 'approved')
                                    <span class="badge text-bg-success">Approved</span>
                                @elseif ($dtr->status === 'flagged')
                                    <span class="badge text-bg-danger">Declined</span>
                                    <div class="small mt-1">{{ $dtr->flag_reason }}</div>
                                    <div class="small text-secondary">Waiting for the student to correct it.</div>
                                @else
                                    @if ($dtr->resolved_at)
                                        <div class="small text-secondary mb-1">Corrected by the student</div>
                                    @endif
                                    <div class="d-flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('hte.dtr.verify', $dtr) }}">
                                            @csrf
                                            <button class="hte-action-btn verify" type="submit">Approve</button>
                                        </form>
                                        <button class="hte-action-btn flag" type="button" data-bs-toggle="modal" data-bs-target="#declineDtr{{ $dtr->id }}">Decline</button>
                                    </div>

                                    <div class="modal fade" id="declineDtr{{ $dtr->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Decline time — {{ $student->user->name }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="{{ route('hte.dtr.flag', $dtr) }}">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <div class="small text-secondary mb-3">
                                                            {{ $dtr->log_date?->format('l, F d, Y') }}<br>
                                                            Morning: {{ $dtr->period('am') ?: '—' }} · Afternoon: {{ $dtr->period('pm') ?: '—' }}<br>
                                                            Total: {{ $fmt($dtr->hours_logged) }} hours
                                                        </div>
                                                        <label class="form-label small fw-semibold">Reason</label>
                                                        <select name="flag_reason" class="form-select" required>
                                                            <option value="">Select a reason</option>
                                                            <option>Time in or time out is wrong</option>
                                                            <option>The student was absent on this date</option>
                                                            <option>The student was undertime</option>
                                                            <option>Other</option>
                                                        </select>
                                                        <label class="form-label small fw-semibold mt-3">Note to the student <span class="text-secondary fw-normal">(optional)</span></label>
                                                        <textarea name="flag_note" class="form-control" rows="3" maxlength="1000" placeholder="e.g. You left at 4:00 PM, not 5:00 PM."></textarea>
                                                        <div class="alert alert-info small mt-3 mb-0">The day goes back to the student to be corrected, then comes back to you.</div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Decline</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
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
