@extends('layouts.student')

@section('title', $requirement->name.' - PSU Intern-Link')

@section('content')
@if ($requirement->code === 'WEEKLY_REPORT')
    @php
        $selectedDate = old('entry_date', now()->toDateString());
        $selected = \Carbon\Carbon::parse($selectedDate);
        // Weeks start on the day the student first logged their DTR; with no DTR
        // or entries yet, the day being added starts Week 1.
        $weekStart = isset($weekAnchor) && $weekAnchor
            ? \App\Support\TrainingWeeks::weekStart($selected, $weekAnchor)
            : $selected->copy()->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6);
    @endphp

    <div class="il-page-header">
        <div>
            <h1 class="il-page-title">Add Training Day to Form 19</h1>
            <p class="text-secondary mb-0">This entry becomes one row inside the weekly report for {{ $weekStart->format('M d') }} – {{ $weekEnd->format('M d, Y') }}.</p>
        </div>
        <a href="{{ route('student.documents.log', $requirement) }}" class="il-btn-secondary text-decoration-none">
            <i class="ti ti-arrow-left"></i> Back to Weekly Report
        </a>
    </div>

    <div class="il-card" style="max-width: 850px;">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="alert alert-light border">
            <strong>Form 19 columns:</strong> Date · Tasks Accomplished · Knowledge, Skills, Values Learned
        </div>

        <form method="POST" action="{{ route('student.documents.log.entry.store', $requirement) }}">
            @csrf

            <div class="mb-3">
                <label class="il-form-label" for="entry_date">Training Date</label>
                <input type="date" class="form-control" id="entry_date" name="entry_date"
                       value="{{ $selectedDate }}" max="{{ now()->toDateString() }}"
                       @if (! empty($weekOneFixed) && $weekAnchor) min="{{ $weekAnchor->toDateString() }}" @endif required>
                <div class="form-text">Choose the actual training day. The system automatically groups it into the correct weekly report. Each day can only be logged once.</div>
                <div class="text-danger small mt-1 d-none" id="duplicateDateWarning">
                    You already have an entry for this date. Delete it from the weekly report first, or choose another day.
                </div>
                <div class="text-danger small mt-1 d-none" id="lockedWeekWarning"></div>
            </div>

            <div class="mb-3">
                <label class="il-form-label" for="tasks_accomplished">Tasks Accomplished</label>
                <textarea class="form-control" id="tasks_accomplished" name="tasks_accomplished" rows="7" required>{{ old('tasks_accomplished') }}</textarea>
            </div>

            <div class="mb-3">
                <label class="il-form-label" for="knowledge_skills_learned">Knowledge, Skills, Values Learned</label>
                <textarea class="form-control" id="knowledge_skills_learned" name="knowledge_skills_learned" rows="7" required>{{ old('knowledge_skills_learned') }}</textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="il-btn-primary" id="saveEntryButton"><i class="ti ti-device-floppy"></i> Save Training Day</button>
                <a href="{{ route('student.documents.log', $requirement) }}" class="il-btn-secondary text-decoration-none">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        (function () {
            var logged = @json($loggedDates ?? []);
            var input = document.getElementById('entry_date');
            var warning = document.getElementById('duplicateDateWarning');
            var button = document.getElementById('saveEntryButton');

            var locked = @json($lockedWeeks ?? []);
            var lockedWarning = document.getElementById('lockedWeekWarning');

            function check() {
                var duplicate = logged.indexOf(input.value) !== -1;
                warning.classList.toggle('d-none', !duplicate);
                var week = null;
                locked.forEach(function (w) { if (input.value >= w.start && input.value <= w.end) { week = w; } });
                if (week) {
                    var next = new Date(week.end + 'T00:00:00');
                    next.setDate(next.getDate() + 1);
                    lockedWarning.textContent = 'Week ' + week.number + ' (' + week.label + ') has already been sent to your instructor, so no more days can be added to it. The next week starts on '
                        + next.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) + '.';
                }
                lockedWarning.classList.toggle('d-none', !week);
                button.disabled = duplicate || !!week;
            }

            input.addEventListener('input', check);
            input.addEventListener('change', check);
            check();
        })();
    </script>
@else
    <div class="il-page-header">
        <h1 class="il-page-title">Add entry &mdash; {{ $requirement->name }}</h1>
        <a href="{{ route('student.documents.log', $requirement) }}" class="il-btn-secondary text-decoration-none">
            <i class="ti ti-arrow-left"></i> Back to log
        </a>
    </div>

    <div class="il-card" style="max-width: 640px;">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('student.documents.log.entry.store', $requirement) }}">
            @csrf

            @if ($requirement->entry_cadence === 'weekly')
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="il-form-label" for="entry_date">Week start date</label>
                        <input type="date" class="form-control" id="entry_date" name="entry_date" value="{{ old('entry_date') }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="il-form-label" for="entry_end_date">Week end date</label>
                        <input type="date" class="form-control" id="entry_end_date" name="entry_end_date" value="{{ old('entry_end_date') }}" required>
                    </div>
                </div>
            @else
                <div class="mb-3">
                    <label class="il-form-label" for="entry_date">Date</label>
                    <input type="date" class="form-control" id="entry_date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required>
                </div>
            @endif

            @foreach ($requirement->fieldDefinitions as $field)
                <div class="mb-3">
                    <label class="il-form-label" for="{{ $field->field_key }}">{{ $field->label }}</label>
                    @if ($field->field_type === 'textarea')
                        <textarea class="form-control" id="{{ $field->field_key }}" name="{{ $field->field_key }}" rows="3" {{ $field->is_required ? 'required' : '' }}>{{ old($field->field_key) }}</textarea>
                    @else
                        <input type="text" class="form-control" id="{{ $field->field_key }}" name="{{ $field->field_key }}" value="{{ old($field->field_key) }}" {{ $field->is_required ? 'required' : '' }}>
                    @endif
                </div>
            @endforeach

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="il-btn-primary">Save entry</button>
                <a href="{{ route('student.documents.log', $requirement) }}" class="il-btn-secondary text-decoration-none">Cancel</a>
            </div>
        </form>
    </div>
@endif
@endsection
