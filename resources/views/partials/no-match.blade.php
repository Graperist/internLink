{{--
    Shown when nothing matches the academic year / semester (or other) filter.

    @include('partials.no-match', ['term' => $term, 'what' => 'interns', 'resetUrl' => route('instructor.monitoring')])
--}}
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="status">
    <i class="ti ti-filter-off fs-5"></i>
    <div>
        <strong>Nothing matches this filter.</strong>
        No {{ $what ?? 'records' }} for {{ isset($term) ? $term->label() : 'the chosen filter' }}.
        Try another academic year or semester,
        @if (isset($term) && $term->year !== 'all')
            <a href="{{ request()->fullUrlWithQuery(['academic_year' => 'all', 'semester' => null, 'page' => null]) }}" class="alert-link">show all years</a>,
        @endif
        or <a href="{{ $resetUrl ?? url()->current() }}" class="alert-link">clear the filter</a>.
    </div>
</div>
