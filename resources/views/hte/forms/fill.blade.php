@extends('layouts.hte')

@section('title', $requirement->name.' - PSU Intern-Link')
@section('page_title', $requirement->name)

@section('content')
<div class="il-page-header">
    <div>
        <h1 class="il-page-title">{{ $requirement->name }}</h1>
        <p class="text-secondary mb-0">{!! isset($subtitle) ? e($subtitle) : 'Student: <strong>'.e($student?->user?->name).'</strong> · '.e($deployment->hte?->name) !!}</p>
    </div>
    <a href="{{ route('hte.evaluations') }}" class="il-btn-secondary text-decoration-none">Back to Evaluations</a>
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
        Complete Form 13 using the official PSU Internship Evaluation Form dated 14-Oct-2024. Rate all eight competencies, add remarks where appropriate, and complete the disposition and evaluation details. Once submitted, it will appear in the Subject Instructor's Document Review queue.
    </div>

    @php
        $competencies = \App\Support\InternshipEvaluationForm::COMPETENCIES;
        $isForm13 = $requirement->code === 'EVALUATION_FORM';
        $ratingScaleLegend = match ($requirement->code) {
            'STUDENT_HTE_EVALUATION', 'PARTNER_AGENCY_EVAL_SELF', 'PARTNER_AGENCY_EVAL_INSTRUCTOR' => ['5' => 'Extremely Satisfied', '4' => 'Highly Satisfied', '3' => 'Satisfied', '2' => 'Moderately Satisfied', '1' => 'Not Satisfied'],
            'TRAINING_SUPERVISOR_FEEDBACK', 'STUDENT_FEEDBACK_FORM' => ['5' => 'Strongly Agree', '4' => 'Agree', '3' => 'Neither Agree nor Disagree', '2' => 'Disagree', '1' => 'Strongly Disagree'],
            default => ['5' => '5', '4' => '4', '3' => '3', '2' => '2', '1' => '1'],
        };
    @endphp

    <form method="POST" action="{{ $formAction ?? route('hte.forms.store', [$requirement, $deployment]) }}">
        @csrf
        @foreach ($requirement->groupedFieldDefinitions() as $groupLabel => $fields)
            @if ($groupLabel)
                <h2 class="h6 fw-bold mt-4 mb-3">{{ $groupLabel }}</h2>
            @endif
            @foreach ($fields as $field)
                @php $value = old($field->field_key, $document?->fieldValue($field->field_key) ?? ($fieldDefaults[$field->field_key] ?? '')); @endphp
                <div class="mb-3">
                    @if ($field->field_type === 'rating_scale' && $isForm13 && isset($competencies[\Illuminate\Support\Str::after($field->field_key, 'rating_')]))
                        @php $competency = $competencies[\Illuminate\Support\Str::after($field->field_key, 'rating_')]; @endphp
                        <label class="il-form-label">{{ $competency['letter'] }}. {{ $competency['name'] }} <span class="text-secondary fw-normal">({{ $competency['weight'] }}%)</span></label>
                        <div class="border rounded">
                            @foreach ($competency['levels'] as $rate => $descriptor)
                                <label class="d-flex align-items-start gap-2 small p-2 {{ ! $loop->last ? 'border-bottom' : '' }}" style="cursor:pointer;">
                                    <input type="radio" class="mt-1 form13-rating" name="{{ $field->field_key }}" value="{{ $rate }}" @checked((string)$value === (string)$rate) {{ $field->is_required ? 'required' : '' }}>
                                    <span><strong>{{ $rate }}</strong> – {{ $descriptor }}</span>
                                </label>
                            @endforeach
                        </div>
                    @elseif ($field->field_type === 'rating_scale')
                        <label class="il-form-label">{{ $field->label }}</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach ($ratingScaleLegend as $rate => $text)
                                <label class="d-flex align-items-center gap-2 small">
                                    <input type="radio" name="{{ $field->field_key }}" value="{{ $rate }}" @checked((string)$value === (string)$rate) {{ $field->is_required ? 'required' : '' }}>
                                    <span><strong>{{ $rate }}</strong> – {{ $text }}</span>
                                </label>
                            @endforeach
                        </div>
                    @elseif ($field->field_type === 'checkbox')
                        <input type="hidden" name="{{ $field->field_key }}" value="0">
                        <label class="d-flex align-items-start gap-2 small">
                            <input type="checkbox" class="form-check-input mt-1" name="{{ $field->field_key }}" value="1" @checked(in_array((string)$value, ['1','true','on'], true)) {{ $field->is_required ? 'required' : '' }}>
                            <span>{{ $field->label }}</span>
                        </label>
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
            <button type="submit" class="il-btn-primary">Submit for Review</button>
            <a href="{{ route('hte.evaluations') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@endsection
