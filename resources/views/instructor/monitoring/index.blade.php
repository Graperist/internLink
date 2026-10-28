@extends('layouts.instructor')

@section('title', 'Time Frame Monitoring - PSU Intern-Link')

@section('content')
@php $fmt = fn ($h) => \App\Support\TimeFrame::format($h); @endphp

<div class="il-page-header">
    <div>
        <h1 class="il-page-title">Time Frame Monitoring</h1>
        <p class="text-secondary small mb-0">Form 17 – Internship Time Frame. The hours each intern entered per day; they count toward the required hours right away.</p>
    </div>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3">
    @php
        // The coordinator: one dropdown per program - their own program first
        // (open), then the other programs (closed until opened). An instructor
        // has the single list of their program.
        $me = auth()->user()->instructor;
        $isCoordinator = $me->isCoordinator();
        $mineId = (int) $me->program_id;
        $selectedProgramId = $selected ? (int) $selected['student']->program_id : null;
        if ($isCoordinator) {
            $programIds = $rows->pluck('student.program_id')->merge($notDeployed->pluck('program_id'))->push($mineId)->filter()->unique();
            $programs = \App\Models\Program::whereIn('id', $programIds)->get()
                ->sortBy(fn ($p) => ($p->id === $mineId ? '0' : '1').$p->code, SORT_NATURAL | SORT_FLAG_CASE);
            $groups = $programs->map(fn ($program) => [
                'key' => 'tfProgram'.$program->id,
                'title' => $program->code,
                'subtitle' => $program->id === $mineId ? 'Your program' : $program->name,
                'mine' => $program->id === $mineId,
                'open' => $program->id === $mineId || $program->id === $selectedProgramId,
                'rows' => $rows->filter(fn ($r) => (int) $r['student']->program_id === $program->id)->values(),
                'notDeployed' => $notDeployed->filter(fn ($st) => (int) $st->program_id === $program->id)->values(),
            ])->values();
        } else {
            $groups = collect([['key' => null, 'title' => 'Deployed interns', 'subtitle' => null, 'mine' => true, 'open' => true, 'rows' => $rows, 'notDeployed' => $notDeployed]]);
        }
    @endphp
    <div class="{{ $selected ? 'col-lg-4' : 'col-12' }} d-grid gap-3 align-content-start">
        @foreach ($groups as $group)
        <div class="il-card p-0 overflow-hidden">
            @if ($group['key'])
                <button type="button" class="tf-toggle w-100 px-3 py-3 d-flex justify-content-between align-items-center {{ $group['open'] ? '' : 'collapsed' }}"
                        data-bs-toggle="collapse" data-bs-target="#{{ $group['key'] }}" aria-expanded="{{ $group['open'] ? 'true' : 'false' }}" aria-controls="{{ $group['key'] }}">
                    <span class="text-start" style="min-width:0;">
                        <span class="fw-bold">{{ $group['title'] }}</span>
                        @if ($group['mine'])<span class="badge rounded-pill text-bg-primary ms-1">Your program</span>@endif
                        @unless ($group['mine'])<span class="d-block small text-secondary text-truncate">{{ $group['subtitle'] }}</span>@endunless
                    </span>
                    <span class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="small text-secondary">{{ $group['rows']->count() }} deployed{{ $group['notDeployed']->isNotEmpty() ? ' · '.$group['notDeployed']->count().' not yet' : '' }}</span>
                        <i class="ti ti-chevron-down tf-chev"></i>
                    </span>
                </button>
            @else
                <div class="px-3 pt-3 pb-2 d-flex justify-content-between align-items-center">
                    <span class="small fw-bold text-uppercase text-secondary" style="letter-spacing:.03em;">{{ $group['title'] }}</span>
                    <span class="small text-secondary">{{ $group['rows']->count() }}</span>
                </div>
            @endif
            <div @if ($group['key']) id="{{ $group['key'] }}" class="collapse {{ $group['open'] ? 'show' : '' }}" @endif>
            <div class="list-group list-group-flush il-form-list {{ $group['key'] ? 'border-top' : '' }}">
                @forelse ($group['rows'] as $row)
                    @php
                        $percent = $row['required'] > 0 ? min(100, (int) round($row['approved'] / $row['required'] * 100)) : 0;
                        $isOpen = $selected && $selected['student']->id === $row['student']->id;
                    @endphp
                    <a href="{{ route('instructor.monitoring', ['student' => $row['student']->id]) }}" class="list-group-item list-group-item-action {{ $isOpen ? 'active-form' : '' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <div style="min-width:0;">
                                <div class="fw-semibold small">{{ $row['student']->user->name }}</div>
                                <div class="text-secondary" style="font-size:.72rem;">{{ $row['student']->program?->code }} · {{ $row['deployment']?->hte?->name }}</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="small fw-semibold">{{ $fmt($row['approved']) }} / {{ $fmt($row['required']) }} h</div>
                                <div class="text-secondary" style="font-size:.72rem;">{{ $row['count'] }} {{ \Illuminate\Support\Str::plural('day', $row['count']) }}{{ $row['last'] ? ' · last '.\Carbon\Carbon::parse($row['last'])->format('M d') : '' }}</div>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height:5px;"><div class="progress-bar {{ $percent >= 100 ? 'bg-success' : '' }}" style="width: {{ $percent }}%"></div></div>
                    </a>
                @empty
                    <div class="list-group-item text-center text-secondary py-4 small">
                        @if ($group['key'])
                            No deployed interns in {{ $group['title'] }} yet.
                        @else
                            No deployed interns yet. <a href="{{ route('instructor.advisees', ['status' => 'pending']) }}">Start a deployment</a> first.
                        @endif
                    </div>
                @endforelse
            </div>
            @if ($group['notDeployed']->isNotEmpty())
                <div class="px-3 py-2 border-top small text-secondary">
                    <div class="fw-semibold mb-1">Not deployed yet ({{ $group['notDeployed']->count() }})</div>
                    <div style="font-size:.75rem;">A Time Frame starts once the intern is deployed:
                        {{ $group['notDeployed']->take(30)->map(fn ($s) => $s->user?->name.($s->currentInternship?->deployment ? ' ('.$s->currentInternship->deployment->status.')' : ''))->join(', ') }}{{ $group['notDeployed']->count() > 30 ? ' and '.($group['notDeployed']->count() - 30).' more' : '' }}
                    </div>
                </div>
            @endif
            </div>
        </div>
        @endforeach
    </div>

    @if ($selected)
    <div class="col-lg-8">
        <div class="il-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h6 fw-bold mb-0">{{ $selected['student']->user->name }}</h2>
                    <div class="small text-secondary">{{ $selected['deployment']?->hte?->name }}</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold">{{ $fmt($selected['approved']) }} <span class="small text-secondary fw-normal">/ {{ $fmt($selected['required']) }} hours rendered</span></div>
                </div>
            </div>
            @if ($days->isNotEmpty())
                @php
                    $tfWeeks = \App\Support\TrainingWeeks::list($days->pluck('log_date'), \App\Support\TrainingWeeks::anchorForInternship($selected['student']->currentInternship));
                    $tfMonths = \App\Support\TrainingWeeks::months($days->pluck('log_date'));
                    $tfSheets = \App\Support\TimeFrameForm::sheetList($days);
                @endphp
                {{-- View or download Form 17 for the whole time frame, one month or one week. --}}
                <form method="GET" action="{{ route('instructor.monitoring.form', $selected['student']) }}" target="_blank"
                      class="d-flex flex-wrap align-items-center gap-2 mb-3 p-2 rounded" style="background:#f4f6f9;" id="tfPeriodForm">
                    <label for="tfPeriod" class="small fw-semibold text-secondary mb-0"><i class="ti ti-calendar"></i> Form 17 for</label>
                    <select name="period" id="tfPeriod" class="form-select form-select-sm" style="width:auto;min-width:260px;">
                        <option value="">Whole time frame ({{ $days->count() }} {{ \Illuminate\Support\Str::plural('day', $days->count()) }})</option>
                        @if (count($tfSheets) > 1)
                            <optgroup label="By sheet ({{ \App\Support\TimeFrameForm::ROWS }} days each)">
                                @foreach ($tfSheets as $sheet)
                                    <option value="sheet:{{ $sheet['number'] }}">Sheet {{ $sheet['number'] }} &ndash; {{ $sheet['first']->format('M d') }} to {{ $sheet['last']->format('M d, Y') }} ({{ $sheet['count'] }} {{ \Illuminate\Support\Str::plural('day', $sheet['count']) }})</option>
                                @endforeach
                            </optgroup>
                        @endif
                        <optgroup label="By month">
                            @foreach ($tfMonths as $month)
                                <option value="month:{{ $month['start']->format('Y-m') }}">{{ $month['start']->format('F Y') }} ({{ $month['count'] }} {{ \Illuminate\Support\Str::plural('day', $month['count']) }})</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="By week">
                            @foreach ($tfWeeks as $week)
                                <option value="week:{{ $week['start']->toDateString() }}">Week {{ $week['number'] }} &ndash; {{ $week['start']->format('M d') }} to {{ $week['end']->format('M d, Y') }} ({{ $week['count'] }} {{ \Illuminate\Support\Str::plural('day', $week['count']) }})</option>
                            @endforeach
                        </optgroup>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="ti ti-eye"></i> View</button>
                    <button type="submit" name="download" value="1" class="btn btn-sm btn-outline-primary"><i class="ti ti-download"></i> Download</button>
                </form>
            @endif
            {{-- The printable Form 17, one sheet at a time --}}
            @if ($days->isEmpty())
                <p class="small text-secondary mb-2">No days yet. The student hasn't entered any hours.</p>
            @endif
            @include('partials.form17-sheets', [
                'days' => $days,
                'previewUrl' => fn ($sheet) => route('instructor.monitoring.form', array_filter(['student' => $selected['student']->id, 'preview' => 1, 'sheet' => $sheet])),
            ])
        </div>
    </div>
    @endif
</div>

<style>
    .tf-toggle { background: #fff; border: 0; color: #14213a; }
    .tf-toggle:hover { background: #f8f9fb; }
    .tf-chev { transition: transform .2s; color: #5d6b80; }
    .tf-toggle.collapsed .tf-chev { transform: rotate(-90deg); }
    .il-form-list .list-group-item { border-left: 3px solid transparent; }
    .il-form-list .active-form { background: rgba(28, 63, 102, 0.07); border-left-color: var(--il-navy, #1c3f66); }
</style>
@endsection
