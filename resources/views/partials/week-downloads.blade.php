{{--
    "Download by week" buttons for Form 17 (Time Frame) and Form 19 (Weekly
    Report). Each one saves a PDF of that one week only.
    $weeks: TrainingWeeks::list(...); $route: printable route name; $document.
--}}
@if (! empty($weeks))
    <div class="d-flex flex-wrap align-items-center gap-1 mt-2 {{ $class ?? '' }}">
        <span class="small text-secondary me-1"><i class="ti ti-calendar-week"></i> Download by week:</span>
        @foreach ($weeks as $week)
            <a href="{{ route($route, ['document' => $document, 'download' => 1, 'week' => $week['start']->toDateString()]) }}" target="_blank"
               class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem;"
               title="{{ $week['count'] }} {{ \Illuminate\Support\Str::plural('day', $week['count']) }}">
                Week {{ $week['number'] }} <span class="text-secondary">({{ $week['start']->format('M d') }}&ndash;{{ $week['end']->format('M d') }})</span>
            </a>
        @endforeach
    </div>
@endif
