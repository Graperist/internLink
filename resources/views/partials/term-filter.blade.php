{{--
    Academic year + semester filter, placed inside a GET filter form.
    The current academic year and both semesters by default.

    @include('partials.term-filter', ['term' => $term, 'idPrefix' => 'dash'])
--}}
@php $tp = $idPrefix ?? 'term'; @endphp
<div class="input-group flex-nowrap" style="width: 190px; flex: 0 0 auto;">
    <span class="input-group-text small px-2">AY</span>
    @include('partials.academic-year-input', ['name' => 'academic_year', 'id' => $tp.'_ay', 'value' => $term->year === 'all' ? '' : $term->year, 'autoSubmit' => true])
    @if ($term->year === 'all')
        <a href="{{ request()->fullUrlWithQuery(['academic_year' => null, 'page' => null]) }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center" title="Back to AY {{ $term->currentYear }}">{{ substr($term->currentYear, 2, 2) }}-{{ substr($term->currentYear, 7, 2) }}</a>
    @else
        <a href="{{ request()->fullUrlWithQuery(['academic_year' => 'all', 'page' => null]) }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center" title="Show all academic years">All</a>
    @endif
</div>
<div style="width: 170px; flex: 0 0 auto;">
    @include('partials.semester-select', ['name' => 'semester', 'id' => $tp.'_sem', 'value' => (string) ($term->semester ?? ''), 'allowAll' => true, 'autoSubmit' => true])
</div>
