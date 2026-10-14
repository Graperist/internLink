@extends('layouts.hte')

@section('title', 'DTR Verification - PSU Intern-Link')
@section('page_title', 'DTR Verification')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="il-card p-0 overflow-hidden">
        <div class="hte-section-heading d-flex justify-content-between align-items-center gap-3">
            <span>DTR Records &amp; Verification</span>
            <span class="text-secondary fw-normal" style="font-size:.78rem;">
                Server-calculated distance + GPS accuracy + audit trail
            </span>
        </div>

        <div class="table-responsive">
            <table class="table mb-0 align-middle hte-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Location Check</th>
                        <th>Status / Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $dtr)
                        @php
                            $student = $dtr->deployment->internship->student;
                            $hte = $dtr->deployment->hte;
                            $flags = $dtr->locationFlagLabels();
                            $clean = ! $dtr->hasLocationRisk();
                            $incomplete = ! $dtr->time_in || ! $dtr->time_out;
                            $isToday = $dtr->log_date && $dtr->log_date->isToday();
                            $missedClockOut = $incomplete && ! $isToday;
                            // Verify buttons only make sense while the entry is still open for review.
                            $reviewable = $dtr->status === 'pending' && ! $missedClockOut;
                            $canFlag = ! in_array($dtr->status, ['approved', 'flagged'], true) && ! $missedClockOut;
                            $confirmIn = $dtr->clockInHasLocationRisk()
                                ? 'This clock-in has a location warning. Verify it anyway?'
                                : 'Verify this clock-in?';
                            $confirmOut = $dtr->clockOutHasLocationRisk()
                                ? 'This clock-out has a location warning. Verify it anyway?'
                                : 'Verify this clock-out?';
                            $confirmBoth = $dtr->hasLocationRisk()
                                ? 'This entry has a location warning. Verify clock-in and clock-out anyway?'
                                : 'Verify both clock-in and clock-out?';
                        @endphp

                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="il-row-avatar">
                                        {{ collect(explode(' ', $student->user->name))->map(fn ($p) => $p[0] ?? '')->join('') }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $student->user->name }}</div>
                                        <div class="text-secondary" style="font-size:.76rem;">{{ $student->user->id_number }}</div>
                                    </div>
                                </div>
                            </td>

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
                                @if ($dtr->isClockInVerified())
                                    <span class="badge text-bg-success mt-1"><i class="ti ti-check"></i> Verified</span>
                                    <div class="text-secondary" style="font-size:.72rem;">{{ $dtr->clock_in_verified_at->format('M d, g:i A') }}</div>
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
                                @if ($dtr->isClockOutVerified())
                                    <span class="badge text-bg-success mt-1"><i class="ti ti-check"></i> Verified</span>
                                    <div class="text-secondary" style="font-size:.72rem;">{{ $dtr->clock_out_verified_at->format('M d, g:i A') }}</div>
                                @endif
                            </td>

                            <td class="small">
                                <div>
                                    <strong>In:</strong>
                                    {{ $dtr->clock_in_distance_m !== null ? number_format($dtr->clock_in_distance_m, 1).' m' : '—' }}
                                    · ±{{ number_format($dtr->clock_in_accuracy ?? 0, 0) }} m
                                </div>
                                <div>
                                    <strong>Out:</strong>
                                    {{ $dtr->clock_out_distance_m !== null ? number_format($dtr->clock_out_distance_m, 1).' m' : '—' }}
                                    · ±{{ number_format($dtr->clock_out_accuracy ?? 0, 0) }} m
                                </div>

                                @if ($clean)
                                    <span class="badge text-bg-success mt-1">Location checks passed</span>
                                @else
                                    <span class="badge text-bg-warning mt-1">Needs location review</span>
                                    @foreach ($flags as $flag)
                                        <div class="text-danger mt-1">• {{ $flag }}</div>
                                    @endforeach
                                @endif

                                <div class="text-secondary mt-1">
                                    Geofence: {{ $hte->geofence_radius_m ?? 100 }} m
                                </div>

                                @php
                                    $hasAnyLocation = $dtr->clock_in_latitude || $dtr->clock_out_latitude;
                                @endphp
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary mt-2"
                                    data-bs-toggle="modal"
                                    data-bs-target="#locationDtr{{ $dtr->id }}"
                                    @disabled(! $hasAnyLocation)
                                >
                                    <i class="ti ti-map-pin"></i> View Location
                                </button>

                                <div
                                    class="modal fade"
                                    id="locationDtr{{ $dtr->id }}"
                                    tabindex="-1"
                                    aria-hidden="true"
                                >
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    Clock In / Out Location — {{ $student->user->name }}
                                                    <span class="text-secondary fw-normal small d-block">{{ $dtr->log_date?->format('F d, Y') }}</span>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                @if ($hasAnyLocation)
                                                    <div class="d-flex flex-column gap-2 mb-3">
                                                        @if ($dtr->clock_in_latitude !== null && $dtr->clock_in_longitude !== null)
                                                            <a
                                                                href="https://www.google.com/maps/search/?api=1&query={{ $dtr->clock_in_latitude }},{{ $dtr->clock_in_longitude }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="btn btn-outline-success text-start"
                                                            >
                                                                <i class="ti ti-map-pin"></i> Open Clock-In Location in Google Maps
                                                            </a>
                                                        @endif
                                                        @if ($dtr->clock_out_latitude !== null && $dtr->clock_out_longitude !== null)
                                                            <a
                                                                href="https://www.google.com/maps/search/?api=1&query={{ $dtr->clock_out_latitude }},{{ $dtr->clock_out_longitude }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="btn btn-outline-danger text-start"
                                                            >
                                                                <i class="ti ti-map-pin"></i> Open Clock-Out Location in Google Maps
                                                            </a>
                                                        @endif
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-3 small">
                                                        <div>
                                                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#2e7d32;margin-right:5px;"></span>
                                                            Clock-in
                                                            @if ($dtr->clock_in_captured_at)
                                                                &middot; {{ $dtr->clock_in_captured_at->format('g:i A') }}
                                                            @endif
                                                            @if ($dtr->clock_in_distance_m !== null)
                                                                &middot; {{ number_format($dtr->clock_in_distance_m, 1) }} m from HTE
                                                            @endif
                                                            @if ($dtr->clock_in_accuracy !== null)
                                                                &middot; ±{{ number_format($dtr->clock_in_accuracy, 0) }} m accuracy
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#c62828;margin-right:5px;"></span>
                                                            Clock-out
                                                            @if ($dtr->clock_out_captured_at)
                                                                &middot; {{ $dtr->clock_out_captured_at->format('g:i A') }}
                                                            @endif
                                                            @if ($dtr->clock_out_distance_m !== null)
                                                                &middot; {{ number_format($dtr->clock_out_distance_m, 1) }} m from HTE
                                                            @endif
                                                            @if ($dtr->clock_out_accuracy !== null)
                                                                &middot; ±{{ number_format($dtr->clock_out_accuracy, 0) }} m accuracy
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <p class="text-secondary mt-2 mb-0" style="font-size:.75rem;">
                                                        Each button opens that exact captured GPS point in a new Google Maps tab. A wide GPS
                                                        accuracy margin (shown above) means the true position could still be at the HTE even if
                                                        the captured point looks far away on the map.
                                                    </p>
                                                @else
                                                    <p class="text-secondary small mb-0">
                                                        No GPS coordinates were captured for this clock-in/clock-out.
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                @if ($dtr->status === 'approved')
                                    <span class="badge text-bg-success mb-2">Verified</span>
                                    <div class="small text-secondary">Clock-in and clock-out verified. Hours credited.</div>
                                @elseif ($dtr->status === 'flagged')
                                    <span class="badge text-bg-danger mb-2">Flagged</span>
                                    <div class="small text-secondary">Waiting for student correction.</div>

                                    @if ($dtr->flagTargetLabel())
                                        <div class="small mt-1">
                                            <strong>Flagged:</strong> {{ $dtr->flagTargetLabel() }}
                                        </div>
                                    @endif

                                    @if ($dtr->flag_reason)
                                        <div class="small mt-1">
                                            <strong>Reason:</strong> {{ $dtr->flag_reason }}
                                        </div>
                                    @endif

                                    @if ($dtr->flag_note)
                                        <div class="small mt-1 text-secondary">{{ $dtr->flag_note }}</div>
                                    @endif
                                @elseif ($missedClockOut)
                                    <span class="badge text-bg-secondary mb-2">Missed Clock-Out</span>
                                    <div class="small text-secondary">
                                        @if ($dtr->time_in)
                                            Student clocked in but never clocked out on this date. This entry can no longer be verified as-is.
                                        @else
                                            No clock-in or clock-out was recorded for this date.
                                        @endif
                                    </div>
                                @else
                                    @if ($incomplete)
                                        <span class="badge text-bg-info mb-2">Awaiting Clock-Out</span>
                                        <div class="small text-secondary mb-2">
                                            You can verify the clock-in now. The clock-out can be verified after the student clocks out.
                                        </div>
                                    @else
                                        <span class="badge text-bg-warning mb-2">
                                            Pending &middot; {{ (int) $dtr->isClockInVerified() + (int) $dtr->isClockOutVerified() }} of 2 verified
                                        </span>
                                    @endif

                                    <div class="d-flex flex-wrap gap-2">
                                        @if ($reviewable && $dtr->time_in && ! $dtr->isClockInVerified())
                                            <form method="POST" action="{{ route('hte.dtr.verify', $dtr) }}">
                                                @csrf
                                                <input type="hidden" name="target" value="clock_in">
                                                <button class="hte-action-btn verify" type="submit" onclick="return confirm('{{ $confirmIn }}')">
                                                    Verify Clock-In
                                                </button>
                                            </form>
                                        @endif

                                        @if ($reviewable && $dtr->time_out && ! $dtr->isClockOutVerified())
                                            <form method="POST" action="{{ route('hte.dtr.verify', $dtr) }}">
                                                @csrf
                                                <input type="hidden" name="target" value="clock_out">
                                                <button class="hte-action-btn verify" type="submit" onclick="return confirm('{{ $confirmOut }}')">
                                                    Verify Clock-Out
                                                </button>
                                            </form>
                                        @endif

                                        @if (! $incomplete && ! $dtr->isClockInVerified() && ! $dtr->isClockOutVerified())
                                            <form method="POST" action="{{ route('hte.dtr.verify', $dtr) }}">
                                                @csrf
                                                <input type="hidden" name="target" value="both">
                                                <button class="hte-action-btn verify" type="submit" onclick="return confirm('{{ $confirmBoth }}')">
                                                    Verify Both
                                                </button>
                                            </form>
                                        @endif

                                        <button
                                            class="hte-action-btn flag"
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#flagDtr{{ $dtr->id }}"
                                        >
                                            Flag
                                        </button>
                                    </div>
                                @endif

                                @if ($canFlag)
                                    <div class="modal fade" id="flagDtr{{ $dtr->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Flag DTR — {{ $student->user->name }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>

                                                <form method="POST" action="{{ route('hte.dtr.flag', $dtr) }}">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <div class="small text-secondary mb-3">
                                                            Student: {{ $student->user->name }}<br>
                                                            DTR Date: {{ $dtr->log_date?->format('F d, Y') }}<br>
                                                            HTE: {{ $hte->name }}<br>
                                                            Time In: {{ $dtr->time_in ? \Carbon\Carbon::parse($dtr->time_in)->format('g:i A') : '—' }}
                                                            &middot;
                                                            Time Out: {{ $dtr->time_out ? \Carbon\Carbon::parse($dtr->time_out)->format('g:i A') : '—' }}
                                                        </div>

                                                        @if ($incomplete)
                                                            <input type="hidden" name="flag_target" value="clock_in">
                                                            <div class="small mb-3">
                                                                <strong>What are you flagging:</strong> Clock-In
                                                                <span class="text-secondary">— the student hasn't clocked out yet, so only the clock-in can be flagged right now.</span>
                                                            </div>
                                                        @else
                                                            <label class="form-label small fw-semibold">What are you flagging?</label>
                                                            <div class="d-flex flex-column gap-1 mb-3">
                                                                @foreach (['clock_in' => 'Clock-In only', 'clock_out' => 'Clock-Out only', 'both' => 'Both Clock-In and Clock-Out'] as $value => $label)
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="flag_target" id="flagTarget{{ $value }}{{ $dtr->id }}" value="{{ $value }}" required @checked($value === 'both')>
                                                                        <label class="form-check-label small" for="flagTarget{{ $value }}{{ $dtr->id }}">{{ $label }}</label>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif

                                                        <label class="form-label small fw-semibold">Flag reason</label>
                                                        <select name="flag_reason" class="form-select" required>
                                                            <option value="">Select a reason</option>
                                                            <option>Outside HTE geofence</option>
                                                            <option>Low / unreliable GPS accuracy</option>
                                                            @unless ($incomplete)
                                                                <option>Time entry needs correction</option>
                                                            @endunless
                                                            <option>DTR information needs clarification</option>
                                                            <option>Other</option>
                                                        </select>

                                                        <label class="form-label small fw-semibold mt-3">HTE note to student</label>
                                                        <textarea
                                                            name="flag_note"
                                                            class="form-control"
                                                            rows="4"
                                                            maxlength="1000"
                                                            placeholder="Explain what the student needs to correct or clarify..."
                                                        ></textarea>

                                                        <div class="alert alert-info small mt-3 mb-0">
                                                            @if ($incomplete)
                                                                Flagging now, before clock-out, notifies the Student, Subject Instructor, and OJT
                                                                Coordinator right away. The student can keep working — clocking out as usual will
                                                                resend this entry for your re-verification.
                                                            @else
                                                                Flagging automatically notifies the Student, Subject Instructor, and OJT Coordinator.
                                                                Any verification of the flagged clock-in/clock-out is withdrawn.
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Flag DTR</button>
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
                            <td colspan="6" class="text-center text-secondary py-5">
                                No DTR records are available for interns assigned to this HTE.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
