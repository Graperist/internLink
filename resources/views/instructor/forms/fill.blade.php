@extends('layouts.instructor')

@section('title', $requirement->name.' - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">{{ $requirement->name }}</h1>
        <p class="text-secondary mb-0">{!! isset($subtitle) ? e($subtitle) : 'Student: <strong>'.e($student?->user?->name).'</strong> · '.e($deployment->hte?->name) !!}</p>
    </div>
    <a href="{{ route('instructor.evaluations') }}" class="il-btn-secondary text-decoration-none">Back to Evaluations</a>
</div>

<div class="il-card" style="max-width: 1050px;">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($document && $document->status === 'returned' && $document->latestReview?->remarks)
        <div class="alert alert-danger"><strong>Returned for correction:</strong> {{ $document->latestReview->remarks }}</div>
    @endif

    <div class="alert alert-info small">
        This form follows the official PSU Partner Agency Evaluation Instrument. Submit it after reviewing the HTE's
        performance from the university/instructor perspective.
    </div>

    @php
        $ratingScaleLegend = ['5' => 'Extremely Satisfied', '4' => 'Highly Satisfied', '3' => 'Satisfied', '2' => 'Moderately Satisfied', '1' => 'Not Satisfied'];
    @endphp

    @php $ratingCount = $requirement->fieldDefinitions->where('field_type', 'rating_scale')->count(); $ratingNo = 0; @endphp
    @if ($ratingCount)
        @include('partials.rating.head', ['legend' => $ratingScaleLegend, 'count' => $ratingCount])
    @endif

    <form method="POST" action="{{ $formAction }}">
        @csrf
        @foreach ($requirement->groupedFieldDefinitions() as $groupLabel => $fields)
            @if ($groupLabel)
                @if ($fields->contains('field_type', 'rating_scale'))
                    <div class="rate-group"><h2>{{ $groupLabel }}</h2><span class="rate-group-line"></span></div>
                @else
                    <h2 class="h6 fw-bold mt-4 mb-3">{{ $groupLabel }}</h2>
                @endif
            @endif
            @foreach ($fields as $field)
                @php $value = old($field->field_key, $document?->fieldValue($field->field_key) ?? ($fieldDefaults[$field->field_key] ?? '')); @endphp
                <div class="mb-3">
                    @if ($field->field_type === 'rating_scale')
                        @php $ratingNo++; @endphp
                        @include('partials.rating.question', ['field' => $field, 'value' => $value, 'number' => $ratingNo, 'legend' => $ratingScaleLegend, 'required' => true])
                    @elseif ($field->field_type === 'select')
                        @php $options = str_starts_with((string)$field->validation_rule, 'enum:') ? array_filter(array_map('trim', explode(',', substr($field->validation_rule, 5)))) : []; @endphp
                        <label class="il-form-label" for="{{ $field->field_key }}">{{ $field->label }}</label>
                        <select class="form-select" id="{{ $field->field_key }}" name="{{ $field->field_key }}" {{ $field->is_required ? 'required' : '' }}>
                            <option value="" disabled {{ $value === '' ? 'selected' : '' }}>Select&hellip;</option>
                            @foreach ($options as $option)<option value="{{ $option }}" @selected((string)$value === (string)$option)>{{ $option }}</option>@endforeach
                        </select>
                    @elseif ($field->field_type === 'textarea')
                        <label class="il-form-label" for="{{ $field->field_key }}">{{ $field->label }}</label>
                        <textarea class="form-control" id="{{ $field->field_key }}" name="{{ $field->field_key }}" rows="3" {{ $field->is_required ? 'required' : '' }}>{{ $value }}</textarea>
                    @else
                        <label class="il-form-label" for="{{ $field->field_key }}">{{ $field->label }}</label>
                        <input type="{{ in_array($field->field_type, ['date','number','email']) ? $field->field_type : 'text' }}"
                               class="form-control" id="{{ $field->field_key }}" name="{{ $field->field_key }}"
                               value="{{ $value }}" {{ $field->is_required ? 'required' : '' }}>
                    @endif
                </div>
            @endforeach
        @endforeach

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="il-btn-primary">Submit Form</button>
            <a href="{{ route('instructor.evaluations') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@if ($ratingCount)
    @include('partials.rating.script')
@endif
@endsection
