<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $document->requirement->name }} — {{ $document->internship->student->user->name }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5px; color:#000; margin:0 auto; max-width:760px; line-height:1.28; }
        .no-print { margin-bottom: 12px; }
        .header { display:flex; align-items:center; gap:12px; border-bottom:2px solid #1c4f82; padding-bottom:8px; margin-bottom:12px; }
        .header img { width:58px; height:58px; object-fit:contain; }
        .header-title { flex:1; text-align:center; }
        .header-title h1 { font-size:18px; margin:0 0 2px; }
        .header-title p { margin:0; font-size:10px; font-weight:700; }
        .form-meta { text-align:right; font-size:8.5px; line-height:1.15; }
        .form-number { font-size:10px; font-weight:700; margin-bottom:3px; }
        .section-title { font-weight:700; text-transform:uppercase; font-size:11px; border-bottom:1px solid #000; padding-bottom:3px; margin:13px 0 7px; }
        .field-table { width:100%; border-collapse:collapse; }
        .field-table td { border:1px solid #777; padding:5px 6px; vertical-align:top; }
        .field-table td.label { width:33%; font-weight:700; background:#f5f5f5; }
        .rating-table { width:100%; border-collapse:collapse; margin-bottom:8px; }
        .rating-table th, .rating-table td { border:1px solid #777; padding:5px; vertical-align:top; }
        .rating-table th { background:#f5f5f5; }
        .rating { width:9%; text-align:center; font-weight:700; }
        .muted { color:#666; }
        .signature { margin-top:25px; text-align:center; width:46%; }
        .signature .line { border-bottom:1px solid #000; height:22px; }
        .signature .name { font-weight:700; margin-top:3px; }
        .signature-row { display:flex; justify-content:space-between; gap:40px; }
        .entry-table { width:100%; border-collapse:collapse; }
        .entry-table th, .entry-table td { border:1px solid #777; padding:5px; vertical-align:top; }
        .entry-table th { background:#f5f5f5; }
        @media print { .no-print { display:none; } body { max-width:none; } }
            /* Signatory names are printed in capital letters on every form. */
        .sig-person { text-transform: uppercase; }
    </style>
</head>
<body @if ($autoPrint ?? true) onload="window.print()" @endif>
@isset($backUrl)
    <a class="no-print" href="{{ $backUrl }}" style="margin-right: 8px;">&larr; Back</a>
@endisset
<button class="no-print" onclick="window.print()">{{ isset($backUrl) ? 'Download PDF' : 'Print' }}</button>

@php
    $requirement = $document->requirement;
    $student = $document->internship->student;
    $campus = $document->internship->campus;
    $values = $document->fieldValues->keyBy('field_key');
    $value = fn (string $key) => $values->get($key)?->value;
    $isRating = fn ($field) => $field->field_type === 'rating_scale';
    $ratingText = in_array($requirement->code, ['STUDENT_HTE_EVALUATION','PARTNER_AGENCY_EVAL_SELF','PARTNER_AGENCY_EVAL_INSTRUCTOR'], true)
        ? [5=>'Extremely Satisfied',4=>'Highly Satisfied',3=>'Satisfied',2=>'Moderately Satisfied',1=>'Not Satisfied']
        : (in_array($requirement->code, ['TRAINING_SUPERVISOR_FEEDBACK','STUDENT_FEEDBACK_FORM'], true)
            ? [5=>'Strongly Agree',4=>'Agree',3=>'Neither Agree nor Disagree',2=>'Disagree',1=>'Strongly Disagree']
            : [5=>'5',4=>'4',3=>'3',2=>'2',1=>'1']);
    $form13 = \App\Support\InternshipEvaluationForm::COMPETENCIES;
    // Official control numbers: every post-OJT form here is FM-AA-INT-<n>, Rev. 0, 01-Feb-2017.
    $controlNo = $requirement->form_number ? 'FM-AA-INT-'.$requirement->form_number : null;
    $preparedBy = $value('rater_name') ?: $value('supervisor_name') ?: $student?->user?->name;
    $signatoryLabel = match ($requirement->audience) {
        'hte' => 'HTE Representative',
        'instructor' => 'Subject Instructor',
        default => 'Student-Intern',
    }; 
@endphp

<div class="header">
    <img src="{{ asset('images/psu-logo.png') }}" alt="PSU">
    <div class="header-title">
        <h1>{{ $requirement->name }}</h1>
        <p>PANGASINAN STATE UNIVERSITY</p>
        <p>{{ $campus?->name ? preg_replace('/^Pangasinan State University\s*[-,:]\s*/i', '', $campus->name) : '' }}</p>
    </div>
    <div class="form-meta">
        @if ($controlNo)
            <div class="form-number">{{ $controlNo }}</div>
            Rev. 0<br>01-Feb-2017
        @else
            Official OJT Record<br>{{ optional($document->submitted_at)->format('d-M-Y') }}
        @endif
    </div>
</div>

@if ($requirement->code === 'TRAINING_LIABILITY')
    <p>I, <strong>{{ $value('student_name') ?: $student?->user?->name }}</strong>, a student of Pangasinan State University, agree to undergo overtime training at <strong>{{ $value('company_name') }}</strong>. I and my parent/guardian consent to the overtime arrangement known to the company. PSU and the company shall not be held responsible for injury or incidents arising during overtime training, as stated in the official form.</p>
@elseif ($requirement->code === 'INTERNSHIP_RELEASE_FORM')
    <p>Upon completion and submission of all required documents, the student is released to undergo the required internship/practicum at the cooperating company/agency/firm for the indicated period.</p>
@endif

@foreach ($requirement->groupedFieldDefinitions() as $groupLabel => $fields)
    @if ($groupLabel)
        <div class="section-title">{{ $groupLabel }}</div>
    @endif

    @php
        $ratingFields = $fields->filter(fn($f) => $isRating($f));
        $normalFields = $fields->reject(fn($f) => $isRating($f));
    @endphp

    @if ($normalFields->isNotEmpty())
        <table class="field-table">
            @foreach ($normalFields as $field)
                <tr>
                    <td class="label">{{ $field->label }}</td>
                    <td>
                        @if ($field->field_type === 'checkbox')
                            {{ $value($field->field_key) === '1' ? 'Yes' : 'No' }}
                        @else
                            {{ $value($field->field_key) ?: '—' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($ratingFields->isNotEmpty())
        <table class="rating-table">
            <thead><tr><th>Criteria</th><th class="rating">Rating</th><th>Interpretation</th></tr></thead>
            <tbody>
                @foreach ($ratingFields as $field)
                    @php $rate = $value($field->field_key); @endphp
                    <tr>
                        <td>{{ $field->label }}</td>
                        <td class="rating">{{ $rate ?: '—' }}</td>
                        @php
                            $competencyKey = \Illuminate\Support\Str::after($field->field_key, 'rating_');
                            $descriptor = ($requirement->code === 'EVALUATION_FORM' && $rate) ? ($form13[$competencyKey]['levels'][(int) $rate] ?? null) : null;
                        @endphp
                        <td>{{ $rate ? ($descriptor ?? ($ratingText[(int)$rate] ?? '')) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endforeach

@if ($document->entries->isNotEmpty())
    <div class="section-title">Entries</div>
    <table class="entry-table">
        <thead>
            <tr><th style="width:15%;">Date</th><th style="width:15%;">Coverage</th>
                @foreach ($requirement->fieldDefinitions as $field)<th>{{ $field->label }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
        @foreach ($document->entries as $entry)
            <tr>
                <td>{{ $entry->entry_date?->format('M d, Y') }}</td>
                <td>{{ $entry->entry_end_date?->format('M d, Y') ?: '—' }}</td>
                @foreach ($requirement->fieldDefinitions as $field)<td>{{ $entry->value($field->field_key) ?: '—' }}</td>@endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if ($requirement->code === 'EVALUATION_FORM')
    @php
        $ratings = \App\Support\InternshipEvaluationForm::ratingsFrom($value);
        $totalPoints = \App\Support\InternshipEvaluationForm::totalPoints($ratings);
        $ojtGrade = \App\Support\InternshipEvaluationForm::grade($ratings);
    @endphp
    <div class="section-title">Evaluation Summary</div>
    <table class="field-table">
        <tr><td class="label">Total points (A + B + C + D + E + F + G + H × 10)</td><td>{{ $totalPoints !== null ? number_format($totalPoints, 2).' / 50' : 'Incomplete — not all competencies rated' }}</td></tr>
        <tr><td class="label">OJT GRADE = Total Points / 50 × 50 + 50</td><td><strong>{{ $ojtGrade !== null ? number_format($ojtGrade, 2) : '—' }}</strong></td></tr>
        <tr><td class="label">Disposition / remarks</td><td>{{ $value('other_termination_reason') ?: '—' }}</td></tr>
        <tr><td class="label">Evaluation Date</td><td>{{ $value('evaluation_date') ?: '—' }}</td></tr>
    </table>
@endif

@php $sig = \App\Support\Signatories::for($document->internship); @endphp
<div class="signature-row">
    <div class="signature">
        @if ($requirement->audience === 'instructor' && $sig['instructor'] !== '')
            <div class="fw-bold sig-person" style="font-weight:700;">{{ $sig['instructor'] }}</div>
        @endif
        <div class="line"></div>
        <div class="name">{{ $signatoryLabel }}</div>
        <div class="muted">{{ $preparedBy ?: '____________________________' }}</div>
    </div>
    <div class="signature">
        @if ($sig['coordinator'] !== '')
            <div class="sig-person" style="font-weight:700;">{{ $sig['coordinator'] }}</div>
        @endif
        <div class="line"></div>
        <div class="name">Campus Internship Coordinator</div>
        <div class="muted">Review / record copy</div>
    </div>
</div>

</body>
</html>
