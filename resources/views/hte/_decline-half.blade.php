{{-- Decline one half of a day ($half 'am' / 'pm'): it goes back to the student to be corrected. --}}
<div class="modal fade" id="declineDtr{{ $dtr->id }}{{ $half }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Decline the {{ strtolower($halfName) }} — {{ $student->user->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('hte.dtr.flag', [$dtr, $half]) }}">
                @csrf
                <div class="modal-body">
                    <div class="small text-secondary mb-3">
                        {{ $dtr->log_date?->format('l, F d, Y') }}<br>
                        {{ $halfName }}: {{ $dtr->period($half) }} · {{ \App\Support\TimeFrame::format($dtr->halfHours($half)) }} hours
                    </div>
                    <label class="form-label small fw-semibold">Reason</label>
                    <select name="flag_reason" class="form-select" required>
                        <option value="">Select a reason</option>
                        <option>Time in or time out is wrong</option>
                        <option>The photo is not at the HTE</option>
                        <option>The student was absent</option>
                        <option>The student was undertime</option>
                        <option>Other</option>
                    </select>
                    <label class="form-label small fw-semibold mt-3">Note to the student <span class="text-secondary fw-normal">(optional)</span></label>
                    <textarea name="flag_note" class="form-control" rows="3" maxlength="1000" placeholder="e.g. You left at 11:30 AM, not 12:00 PM."></textarea>
                    <div class="alert alert-info small mt-3 mb-0">Only the {{ strtolower($halfName) }} goes back to the student to be corrected, then it comes back to you.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Decline</button>
                </div>
            </form>
        </div>
    </div>
</div>
