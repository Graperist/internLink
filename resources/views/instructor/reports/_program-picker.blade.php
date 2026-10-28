{{-- The coordinator prints any program's report: all programs, or one. --}}
@if ($instructor->isCoordinator())
    <div class="mb-3">
        <label class="form-label small mb-1" for="{{ $id }}">Program</label>
        <select class="form-select form-select-sm" id="{{ $id }}" name="program_id">
            <option value="">All programs</option>
            @foreach ($programs as $program)
                <option value="{{ $program->id }}">{{ $program->code }}</option>
            @endforeach
        </select>
    </div>
@endif
