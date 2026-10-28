@extends('layouts.hte')

@section('title', $requirement->name.' - PSU Intern-Link')

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

    @if (\App\Support\EvaluationRouting::isSubmitted($document))
        <div class="alert alert-warning small"><i class="ti ti-pencil"></i> <strong>Editing your submitted evaluation.</strong> Change any answer and click Save changes; the Subject Instructor sees the new answers. {{ \App\Support\EvaluationEditWindow::note($document) }}.</div>
    @endif
    <div class="alert alert-info small">
        @switch ($requirement->code)
            @case('EVALUATION_FORM')
                Complete Form 13 using the official PSU Internship Evaluation Form dated 14-Oct-2024. Rate all eight competencies, add remarks where appropriate, and complete the disposition and evaluation details. Once submitted, it goes to the Subject Instructor's Evaluations.
                @break
            @case('TRAINING_SUPERVISOR_FEEDBACK')
                Form 21 is your feedback on the student-trainee. Rate every statement from 5 (Strongly Agree) to 1 (Strongly Disagree). Once submitted, it goes to the Subject Instructor's Evaluations.
                @break
            @case('PARTNER_AGENCY_EVAL_SELF')
                Form 23b is your agency's self-evaluation as a PSU partner. Rate every statement from 5 (Extremely Satisfied) to 1 (Not Satisfied). Once submitted, it goes to the Subject Instructor's Evaluations.
                @break
            @default
                Rate every statement and complete the details. Once submitted, it goes to the Subject Instructor's Evaluations.
        @endswitch
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

    @php $ratingCount = $requirement->fieldDefinitions->where('field_type', 'rating_scale')->count(); $ratingNo = 0; @endphp
    @if ($ratingCount)
        @include('partials.rating.head', ['legend' => $ratingScaleLegend, 'count' => $ratingCount])
    @endif

    <form method="POST" action="{{ $formAction ?? route('hte.forms.store', [$requirement, $deployment]) }}">
        @csrf
        @php
            $valueOf = fn ($f) => old($f->field_key, $document?->fieldValue($f->field_key) ?? ($fieldDefaults[$f->field_key] ?? ''));
            // Form 13: each competency's remarks box goes inside its rating card.
            $remarksFor = $isForm13
                ? $requirement->fieldDefinitions->filter(fn ($f) => str_starts_with($f->field_key, 'remarks_'))->keyBy(fn ($f) => \Illuminate\Support\Str::after($f->field_key, 'remarks_'))
                : collect();
        @endphp
        @foreach ($requirement->groupedFieldDefinitions() as $groupLabel => $fields)
            @php
                $fields = $fields->reject(fn ($f) => $isForm13 && str_starts_with($f->field_key, 'remarks_'));
                $isRatingGroup = $fields->contains('field_type', 'rating_scale');
                $checks = $fields->where('field_type', 'checkbox');
            @endphp
            @continue($fields->isEmpty())
            @if ($groupLabel)
                <div class="rate-group"><h2>{{ $groupLabel }}</h2><span class="rate-group-line"></span></div>
            @endif
            @unless ($isRatingGroup)<div class="rate-section mb-3">@endunless
            @if ($checks->isNotEmpty())
                <div class="rate-checks">
                    @foreach ($checks as $field)
                        <input type="hidden" name="{{ $field->field_key }}" value="0">
                        <input type="checkbox" class="btn-check" id="{{ $field->field_key }}" name="{{ $field->field_key }}" value="1" autocomplete="off"
                               @checked(in_array((string) $valueOf($field), ['1', 'true', 'on'], true)) {{ $field->is_required ? 'required' : '' }}>
                        <label class="rate-check" for="{{ $field->field_key }}"><span class="rate-tick"><i class="ti ti-check"></i></span><span>{{ $field->label }}</span></label>
                    @endforeach
                </div>
            @endif
            @foreach ($fields->where('field_type', '!=', 'checkbox') as $field)
                @php $value = $valueOf($field); @endphp
                <div class="mb-3">
                    @if ($field->field_type === 'rating_scale' && $isForm13 && isset($competencies[\Illuminate\Support\Str::after($field->field_key, 'rating_')]))
                        @php
                            $competencyKey = \Illuminate\Support\Str::after($field->field_key, 'rating_');
                            $competency = $competencies[$competencyKey]; $ratingNo++;
                            $remarksField = $remarksFor->get($competencyKey);
                        @endphp
                        @include('partials.rating.question-rows', [
                            'field' => $field, 'value' => $value, 'number' => $ratingNo,
                            'label' => $competency['letter'].'. '.$competency['name'], 'meta' => $competency['weight'].'%',
                            'levels' => $competency['levels'], 'required' => (bool) $field->is_required, 'extraClass' => 'form13-rating',
                            'remarksField' => $remarksField, 'remarksValue' => $remarksField ? $valueOf($remarksField) : null,
                            'remarksName' => $competency['name'],
                        ])
                    @elseif ($field->field_type === 'rating_scale')
                        @php $ratingNo++; @endphp
                        @include('partials.rating.question', ['field' => $field, 'value' => $value, 'number' => $ratingNo, 'legend' => $ratingScaleLegend, 'required' => (bool) $field->is_required])
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
            @unless ($isRatingGroup)</div>@endunless
        @endforeach


        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="il-btn-primary">{{ \App\Support\EvaluationRouting::isSubmitted($document) ? 'Save changes' : 'Submit for Review' }}</button>
            <a href="{{ route('hte.evaluations') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>
@if ($ratingCount)
    @include('partials.rating.script')
@endif
@endsection
