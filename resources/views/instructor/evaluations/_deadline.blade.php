{{--
    One editing deadline (for a partner agency, or for one intern): shows what
    applies now and, for the instructor of the program, a date/time to set it.
    Params: who, current (text), set (Carbon|null), action (url|null), field (unique id).
--}}
<div class="ev-deadline mt-2">
    <div class="small"><i class="ti ti-calendar-due text-primary"></i> <strong>Deadline:</strong> {{ $who }} {{ $current }}.</div>
    @if ($action)
        <form method="POST" action="{{ $action }}" class="d-flex align-items-center gap-2 flex-wrap mt-1">
            @csrf @method('PUT')
            <input type="datetime-local" name="due_at" class="form-control form-control-sm" style="width: 210px;" aria-label="Deadline"
                   value="{{ $set?->format('Y-m-d\TH:i') }}" id="deadline-{{ $field }}">
            <button type="submit" class="btn btn-sm btn-outline-primary">{{ $set ? 'Change deadline' : 'Set deadline' }}</button>
            @if ($set)
                <button type="submit" name="clear" value="1" class="btn btn-sm btn-link text-secondary px-1" formnovalidate>Use the program default</button>
            @endif
        </form>
    @endif
</div>
<style>.ev-deadline { border-top: 1px dashed #dfe5ec; padding-top: 8px; }</style>
