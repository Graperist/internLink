@php
    $deadlineProgram = $instructor->program_id ? \App\Models\Program::find($instructor->program_id) : null;
    if ($deadlineProgram && ! \App\Models\Internship::where('campus_id', $instructor->campus_id)->where('program_id', $deadlineProgram->id)
            ->whereHas('deployment', fn ($q) => $q->whereIn('status', ['active', 'completed']))->exists()) {
        $deadlineProgram = null;   // nobody deployed yet
    }
@endphp
@if ($deadlineProgram)
    <div class="collapse {{ $errors->has('weekly_report_due_days') ? 'show' : '' }}" id="form19Deadline">
        <div class="il-card mb-3">
            <h2 class="h6 fw-bold mb-1"><i class="ti ti-calendar-due"></i> Form 19 default deadline &middot; {{ $deadlineProgram->code }}</h2>
            <p class="small text-secondary mb-3">
                The default: each week is due at 11:59 PM this many days after it ends (0 = on its last day).
                You can give any week of an intern its own due date and time on that intern's Form 19.
                A week first submitted after that is marked <strong>Late</strong> and you are notified; a week past due and not submitted shows as <strong>Missing</strong>.
                Applies to all your {{ $deadlineProgram->code }} interns.
            </p>
            <form method="POST" action="{{ route('instructor.documents.deadline') }}" class="d-flex flex-wrap align-items-start gap-2">
                @csrf
                @method('PUT')
                <div>
                    <div class="input-group input-group-sm" style="max-width: 320px;">
                        <input type="number" min="0" max="14" name="weekly_report_due_days" aria-label="Days after the week ends"
                               class="form-control @error('weekly_report_due_days') is-invalid @enderror"
                               value="{{ old('weekly_report_due_days', $deadlineProgram->weekly_report_due_days ?? 2) }}" required>
                        <span class="input-group-text">days after the week ends</span>
                    </div>
                    @error('weekly_report_due_days')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Save</button>
            </form>
        </div>
    </div>
@endif
