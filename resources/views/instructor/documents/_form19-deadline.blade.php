{{--
    The Form 19 weekly report deadline of this instructor's own program, set
    right here in Document Review. $instructor is the signed-in instructor.
--}}
@php
    $deadlineProgram = $instructor->program_id ? \App\Models\Program::find($instructor->program_id) : null;
    // Form 19 deadlines only matter once an intern of the program is deployed.
    if ($deadlineProgram && ! \App\Models\Internship::where('campus_id', $instructor->campus_id)->where('program_id', $deadlineProgram->id)
            ->whereHas('deployment', fn ($q) => $q->whereIn('status', ['active', 'completed']))->exists()) {
        $deadlineProgram = null;
    }
    $deadlineDays = (int) ($deadlineProgram?->weekly_report_due_days ?? 2);
    $deadlineText = $deadlineDays === 0 ? 'on the last day of each week' : $deadlineDays.' '.\Illuminate\Support\Str::plural('day', $deadlineDays).' after each week';
@endphp
@if ($deadlineProgram)
    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#form19Deadline" aria-expanded="false"
            title="When each Form 19 week of your {{ $deadlineProgram->code }} interns is due">
        <i class="ti ti-calendar-due"></i> Form 19 due: {{ $deadlineText }}
    </button>
@endif
