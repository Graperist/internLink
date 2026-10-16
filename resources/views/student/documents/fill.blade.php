@extends('layouts.student')

@section('title', $requirement->name.' - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">{{ $requirement->name }}</h1>
    <a href="{{ route('student.documents') }}" class="il-btn-secondary text-decoration-none">
        <i class="ti ti-arrow-left"></i> Back to My Documents
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

    @if ($document && $document->status === 'returned' && $document->latestReview?->remarks)
        <div class="alert alert-danger">
            <strong>Returned for correction:</strong> {{ $document->latestReview->remarks }}
        </div>
    @endif

    <p class="text-secondary small">
        Fill this out completely and accurately. Once your Subject Instructor reviews and prints it, this
        form can no longer be edited here &mdash; you'll sign the printed copy physically instead.
    </p>

    @if ($requirement->code === 'STUDENT_FEEDBACK_FORM')
        <div class="alert alert-info small mb-3">
            <div class="fw-semibold mb-1"><i class="ti ti-info-circle"></i> What is this form for?</div>
            <p class="mb-2">
                The Student-Intern&rsquo;s Feedback Form is where you tell the university how your internship
                is going at your host company. You rate whether your training matches your field of
                specialization, whether it is challenging and gives you chances to learn, whether you know the
                company&rsquo;s policies, how you get along with your supervisor and co-workers, and whether
                your workplace is safe and healthy for student-interns.
            </p>
            <p class="mb-2">
                You can also write down any <strong>problems you have met</strong> and <strong>other concerns</strong>
                so they can be addressed. Your Subject Instructor reviews your answers as part of monitoring your
                internship.
            </p>
            <ul class="mb-0 ps-3">
                <li>Rate every statement honestly &mdash; there are no right or wrong answers.</li>
                <li>&ldquo;Problems met&rdquo; and &ldquo;Other concerns&rdquo; are optional; leave them blank if you have none.</li>
                <li>After your Subject Instructor reviews and prints it, you will sign the printed copy.</li>
            </ul>
        </div>
    @endif

    @if ($requirement->code === 'STUDENT_HTE_EVALUATION')
        <div class="alert alert-info small mb-3">
            <div class="fw-semibold mb-1"><i class="ti ti-info-circle"></i> What is this form for?</div>
            <p class="mb-2">
                Form 23c, the Evaluation Instrument of PSU Partner Agencies, is where you rate the host training
                establishment where you did your internship. The university uses it to decide whether an agency
                remains a good partner for future student-interns.
            </p>
            <p class="mb-2">
                You rate 15 statements in five areas: <strong>Communication</strong>, <strong>Ethical Dealings</strong>,
                <strong>Student Satisfaction &ndash; PSU</strong>, <strong>Student Satisfaction &ndash; Host Training
                Establishment</strong> and <strong>Quality Delivery</strong>. This form is about the agency and the
                university&rsquo;s support, not about your own performance.
            </p>
            <ul class="mb-0 ps-3">
                <li>Your name, agency and rater type (Student) are filled in for you.</li>
                <li>Rate every statement from 5 (Extremely Satisfied) to 1 (Not Satisfied), as listed in the scale below.</li>
                <li>After your Subject Instructor reviews and prints it, you will sign the printed copy.</li>
            </ul>
        </div>
    @endif

    @php
        $ratingScaleLegend = match ($requirement->code) {
            'STUDENT_FEEDBACK_FORM' => ['5' => 'Strongly Agree', '4' => 'Agree', '3' => 'Neither Agree nor Disagree', '2' => 'Disagree', '1' => 'Strongly Disagree'],
            'STUDENT_HTE_EVALUATION' => ['5' => 'Extremely Satisfied', '4' => 'Highly Satisfied', '3' => 'Satisfied', '2' => 'Moderately Satisfied', '1' => 'Not Satisfied'],
            default => ['5' => '5', '4' => '4', '3' => '3', '2' => '2', '1' => '1'],
        };
    @endphp

    @if ($requirement->fieldDefinitions->contains('field_type', 'rating_scale'))
        <div class="alert alert-secondary small mb-3">
            <strong>Rating scale:</strong>
            @foreach ($ratingScaleLegend as $value => $text)
                {{ $value }} &ndash; {{ $text }}@if (! $loop->last), @endif
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('student.documents.fill.store', $requirement) }}">
        @csrf

        @if ($requirement->code === 'OJT_RESUME')
            @php
                $resumeValue = fn (string $key) => old($key, $document?->fieldValue($key) ?? '');
            @endphp

            <div class="alert alert-info small mb-4">
                Enter your information exactly as you want it to appear on the official Internship Resume.
                The 2x2 photo is attached physically to the printed copy.
            </div>

            <h2 class="h6 fw-bold mt-2 mb-3">Personal Information</h2>
            <div class="row g-3">
                <div class="col-12">
                    <label class="il-form-label" for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2" required>{{ $resumeValue('address') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="contact_number">Telephone / Mobile Number</label>
                    <input class="form-control" id="contact_number" name="contact_number" value="{{ $resumeValue('contact_number') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="email">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="{{ $resumeValue('email') }}" required>
                </div>
                <div class="col-12">
                    <label class="il-form-label" for="career_objective">Career Objective</label>
                    <textarea class="form-control" id="career_objective" name="career_objective" rows="4">{{ $resumeValue('career_objective') }}</textarea>
                </div>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Work Experience</h2>
            <p class="text-secondary small">Leave a row blank if you have no work experience.</p>
            <div class="table-responsive">
                <table class="table table-bordered align-middle small mb-0">
                    <thead><tr><th>Inclusive Date</th><th>Employer</th><th>Position</th></tr></thead>
                    <tbody>
                        @for ($i = 1; $i <= 5; $i++)
                            <tr>
                                <td><input class="form-control form-control-sm" name="work_experience_{{ $i }}_date" value="{{ $resumeValue("work_experience_{$i}_date") }}" placeholder="e.g. 2024–2025"></td>
                                <td><input class="form-control form-control-sm" name="work_experience_{{ $i }}_employer" value="{{ $resumeValue("work_experience_{$i}_employer") }}"></td>
                                <td><input class="form-control form-control-sm" name="work_experience_{{ $i }}_position" value="{{ $resumeValue("work_experience_{$i}_position") }}"></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Trainings and Seminars Attended</h2>
            <p class="text-secondary small">Leave a row blank if you have no additional training or seminar.</p>
            <div class="table-responsive">
                <table class="table table-bordered align-middle small mb-0">
                    <thead><tr><th>Title of Seminar</th><th style="width:30%;">Inclusive Date</th></tr></thead>
                    <tbody>
                        @for ($i = 1; $i <= 6; $i++)
                            <tr>
                                <td><input class="form-control form-control-sm" name="training_{{ $i }}_title" value="{{ $resumeValue("training_{$i}_title") }}"></td>
                                <td><input class="form-control form-control-sm" name="training_{{ $i }}_date" value="{{ $resumeValue("training_{$i}_date") }}" placeholder="e.g. June 2025"></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Educational Background</h2>
            <div class="table-responsive">
                <table class="table table-bordered align-middle small mb-0">
                    <thead><tr><th>Level</th><th>School</th><th style="width:30%;">Inclusive Date</th></tr></thead>
                    <tbody>
                        @foreach ([
                            'elementary' => 'ELEMENTARY',
                            'high_school' => 'HIGH SCHOOL',
                            'college' => 'COLLEGE',
                        ] as $key => $label)
                            <tr>
                                <td class="fw-semibold">{{ $label }}</td>
                                <td><input class="form-control form-control-sm" name="education_{{ $key }}_school" value="{{ $resumeValue("education_{$key}_school") }}" required></td>
                                <td><input class="form-control form-control-sm" name="education_{{ $key }}_date" value="{{ $resumeValue("education_{$key}_date") }}" placeholder="e.g. 2010–2016" required></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Extracurricular Activities and Awards Received</h2>
            <textarea class="form-control" id="extracurricular_activities" name="extracurricular_activities" rows="4">{{ $resumeValue('extracurricular_activities') }}</textarea>

            <h2 class="h6 fw-bold mt-4 mb-3">Personal Background</h2>
            <div class="row g-3">
                <div class="col-md-6"><label class="il-form-label" for="nickname">Nickname</label><input class="form-control" id="nickname" name="nickname" value="{{ $resumeValue('nickname') }}" required></div>
                <div class="col-md-6"><label class="il-form-label" for="height">Height</label><input class="form-control" id="height" name="height" value="{{ $resumeValue('height') }}" placeholder="e.g. 5'2&quot;" required></div>
                <div class="col-md-6"><label class="il-form-label" for="age">Age</label><input type="number" min="1" max="120" class="form-control" id="age" name="age" value="{{ $resumeValue('age') }}" required></div>
                <div class="col-md-6"><label class="il-form-label" for="weight">Weight</label><input class="form-control" id="weight" name="weight" value="{{ $resumeValue('weight') }}" placeholder="e.g. 50 kg" required></div>
                <div class="col-md-6"><label class="il-form-label" for="gender">Gender</label><input class="form-control" id="gender" name="gender" value="{{ $resumeValue('gender') }}" required></div>
                <div class="col-md-6"><label class="il-form-label" for="civil_status">Civil Status</label><input class="form-control" id="civil_status" name="civil_status" value="{{ $resumeValue('civil_status') }}" required></div>
                <div class="col-md-6"><label class="il-form-label" for="religion">Religion</label><input class="form-control" id="religion" name="religion" value="{{ $resumeValue('religion') }}" required></div>
                <div class="col-12"><label class="il-form-label" for="skills">Skills</label><textarea class="form-control" id="skills" name="skills" rows="3" required>{{ $resumeValue('skills') }}</textarea></div>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">References</h2>
            <p class="text-secondary small">Provide up to three references. Leave unused rows blank.</p>
            <div class="table-responsive">
                <table class="table table-bordered align-middle small mb-0">
                    <thead><tr><th>Name</th><th>Position</th><th>Company</th><th>Contact Number</th></tr></thead>
                    <tbody>
                        @for ($i = 1; $i <= 3; $i++)
                            <tr>
                                <td><input class="form-control form-control-sm" name="reference_{{ $i }}_name" value="{{ $resumeValue("reference_{$i}_name") }}"></td>
                                <td><input class="form-control form-control-sm" name="reference_{{ $i }}_position" value="{{ $resumeValue("reference_{$i}_position") }}"></td>
                                <td><input class="form-control form-control-sm" name="reference_{{ $i }}_company" value="{{ $resumeValue("reference_{$i}_company") }}"></td>
                                <td><input class="form-control form-control-sm" name="reference_{{ $i }}_contact" value="{{ $resumeValue("reference_{$i}_contact") }}"></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        @elseif ($requirement->code === 'ENDORSEMENT_LETTER')
            @php
                $endorsementValue = fn (string $key) => old(
                    $key,
                    $document?->fieldValue($key) ?? ($endorsementDefaults[$key] ?? '')
                );

                $endorsementCompany = $endorsementValue('company_name');
                $endorsementRecipientName = $endorsementValue('recipient_name');
                $endorsementRecipientOffice = $endorsementValue('recipient_office');
                $endorsementRecipientAddress = $endorsementValue('recipient_address');
                $endorsementRecipientSalutation = $endorsementValue('recipient_salutation');
                $endorsementStudentTitle = $endorsementValue('student_title');
                $endorsementCourse = $endorsementValue('course') ?: $courseMajorDefaults['course'];
                $endorsementMajor = $endorsementValue('major') ?: $courseMajorDefaults['major'];
            @endphp

            <div class="alert alert-info small mb-4">
                Fill in the recipient and host-company details below. Your name, campus, course, and major
                are taken from your internship record and will be placed automatically on the official
                Endorsement Letter.
            </div>

            <h2 class="h6 fw-bold mt-2 mb-3">Letter Details</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="il-form-label" for="endorsement_date">Date</label>
                    <input type="date" class="form-control" id="endorsement_date" name="endorsement_date"
                           value="{{ $endorsementValue('endorsement_date') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="company_name">Host Training Establishment / Company</label>
                    @if ($htes->isNotEmpty())
                        <select class="form-select" id="company_name" name="company_name" required>
                            <option value="" disabled {{ ! $endorsementCompany ? 'selected' : '' }}>Select a partner HTE&hellip;</option>
                            @foreach ($htes as $hte)
                                <option value="{{ $hte->name }}" {{ $endorsementCompany === $hte->name ? 'selected' : '' }}>
                                    {{ $hte->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Choose from your program's partner agencies for this term.</div>
                    @else
                        <input type="text" class="form-control" id="company_name" name="company_name"
                               value="{{ $endorsementCompany }}" placeholder="Enter company / agency name" required>
                        <div class="form-text">No partner HTE list is available yet, so you may enter the name manually.</div>
                    @endif
                </div>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Recipient Information</h2>
            <p class="text-secondary small mb-3">
                Enter the person and office to whom the letter will be addressed. The fields below follow the
                recipient block shown in the official PSU Endorsement Letter.
            </p>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="il-form-label" for="recipient_name">Recipient Name</label>
                    <input type="text" class="form-control" id="recipient_name" name="recipient_name"
                           value="{{ $endorsementRecipientName }}" placeholder="e.g. ART BRYAN C. CELESTE" required>
                </div>
                <div class="col-md-4">
                    <label class="il-form-label" for="recipient_salutation">Recipient Salutation</label>
                    <select class="form-select" id="recipient_salutation" name="recipient_salutation" required>
                        <option value="" disabled {{ ! $endorsementRecipientSalutation ? 'selected' : '' }}>Select&hellip;</option>
                        <option value="Sir" {{ $endorsementRecipientSalutation === 'Sir' ? 'selected' : '' }}>Sir</option>
                        <option value="Madam" {{ $endorsementRecipientSalutation === 'Madam' ? 'selected' : '' }}>Madam</option>
                    </select>
                    <div class="form-text">Used automatically as “Dear Sir:” or “Dear Madam:”.</div>
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="recipient_office">Office / Department</label>
                    <input type="text" class="form-control" id="recipient_office" name="recipient_office"
                           value="{{ $endorsementRecipientOffice }}" placeholder="e.g. Schools Division Office" required>
                </div>
                <div class="col-12">
                    <label class="il-form-label" for="recipient_address">Office / Company Address</label>
                    <textarea class="form-control" id="recipient_address" name="recipient_address" rows="2"
                              placeholder="Enter the complete office / company address" required>{{ $endorsementRecipientAddress }}</textarea>
                </div>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Student Information</h2>
            <div class="row g-3">
                <div class="col-12">
                    <label class="il-form-label" for="display_student_name">Student Name</label>
                    <input type="text" class="form-control" id="display_student_name"
                           value="{{ auth()->user()->name }}" readonly>
                </div>
                <div class="col-12">
                    <label class="il-form-label" for="display_campus_name">Campus</label>
                    <input type="text" class="form-control" id="display_campus_name"
                           value="{{ $internship?->campus?->name ?? auth()->user()->student?->campus?->name ?? '' }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="il-form-label" for="student_title">Student Title</label>
                    @if ($endorsementStudentTitle)
                        <input type="text" class="form-control" id="student_title" name="student_title"
                               value="{{ $endorsementStudentTitle }}" readonly>
                        <div class="form-text">Determined from the gender in your OJT Resume.</div>
                    @else
                        <select class="form-select" id="student_title" name="student_title" required>
                            <option value="" disabled {{ ! $endorsementStudentTitle ? 'selected' : '' }}>Select&hellip;</option>
                            <option value="Mr." {{ $endorsementStudentTitle === 'Mr.' ? 'selected' : '' }}>Mr.</option>
                            <option value="Ms." {{ $endorsementStudentTitle === 'Ms.' ? 'selected' : '' }}>Ms.</option>
                        </select>
                        <div class="form-text">Complete your OJT Resume gender first for automatic selection.</div>
                    @endif
                </div>
                <div class="col-md-8">
                    <label class="il-form-label" for="course">Course</label>
                    <input type="text" class="form-control" id="course" name="course"
                           value="{{ $endorsementCourse }}" readonly required>
                </div>
                <div class="col-md-4">
                    <label class="il-form-label" for="major">Major</label>
                    <input type="text" class="form-control" id="major" name="major"
                           value="{{ $endorsementMajor }}" readonly>
                </div>
            </div>

            <div class="card border-0 bg-light mt-4">
                <div class="card-body small">
                    <div class="fw-semibold mb-2"><i class="ti ti-file-text me-1"></i>Letter preview</div>
                    <div class="text-secondary">
                        The generated letter will use your entered recipient details and the official PSU
                        wording. Your Subject Instructor will review the completed form before it can be printed
                        for physical signatures.
                    </div>
                </div>
            </div>

            @if ($htes->isNotEmpty())
                @php
                    $endorsementHteDirectory = $htes->mapWithKeys(fn ($hte) => [
                        $hte->name => [
                            'contact_person' => $hte->contact_person,
                            'address' => $hte->address,
                        ],
                    ]);
                @endphp
                <script>
                (function () {
                    const directory = @json($endorsementHteDirectory);
                    const companyField = document.getElementById('company_name');
                    const recipientField = document.getElementById('recipient_name');
                    const addressField = document.getElementById('recipient_address');

                    if (!companyField || !companyField.matches('select')) return;

                    function applyCompany(name, onlyIfEmpty) {
                        const info = directory[name];
                        if (!info) return;

                        if (recipientField && (!onlyIfEmpty || !recipientField.value.trim())) {
                            recipientField.value = info.contact_person || '';
                        }

                        if (addressField && (!onlyIfEmpty || !addressField.value.trim())) {
                            addressField.value = info.address || '';
                        }
                    }

                    companyField.addEventListener('change', function () {
                        applyCompany(companyField.value, false);
                    });

                    if (companyField.value) {
                        applyCompany(companyField.value, true);
                    }
                })();
                </script>
            @endif
        @elseif ($requirement->code === 'INTERNSHIP_AGREEMENT')
            @php
                $agreementValue = fn (string $key, $default = '') => old(
                    $key,
                    $document?->fieldValue($key) ?? $default
                );
                $storedCompanyName = $agreementValue('company_name');
                $currentDeploymentHte = $internship?->deployment?->hte;
                $selectedAgreementHteId = $htes->firstWhere('name', $storedCompanyName)?->id
                    ?? $currentDeploymentHte?->id;

                $agreementCourse = $courseMajorDefaults['course'];
                $agreementMajor = $courseMajorDefaults['major'];
                $agreementAddress = $agreementValue('address', $agreementStudentAddress ?? '');
            @endphp

            <div class="alert alert-info small mb-4">
                Select the partner Host Training Establishment from your program's partner agencies for this term.
                The HTE address, representative name, representative position, and required internship
                hours are filled automatically from the official HTE and program records.
            </div>

            <h2 class="h6 fw-bold mt-2 mb-3">Student Information</h2>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="il-form-label" for="agreement_student_name">Student Name</label>
                    <input type="text" class="form-control" id="agreement_student_name"
                           value="{{ auth()->user()->name }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="il-form-label" for="agreement_required_hours">Required Hours</label>
                    <input type="text" class="form-control" id="agreement_required_hours"
                           value="{{ $agreementRequiredHours }} hours" readonly>
                </div>
                <div class="col-md-8">
                    <label class="il-form-label" for="agreement_course">Course</label>
                    <input type="text" class="form-control" id="agreement_course"
                           value="{{ $agreementCourse }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="il-form-label" for="agreement_major">Major</label>
                    <input type="text" class="form-control" id="agreement_major"
                           value="{{ $agreementMajor }}" readonly>
                </div>
                <div class="col-12">
                    <label class="il-form-label" for="address">Student Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2" required>{{ $agreementAddress }}</textarea>
                    <div class="form-text">Pre-filled from your application/resume when available.</div>
                </div>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Host Training Establishment</h2>
            <div class="mb-3">
                <label class="il-form-label" for="agreement_hte_id">Host Company Name</label>
                <select class="form-select" id="agreement_hte_id" name="hte_id" required>
                    <option value="" disabled {{ ! $selectedAgreementHteId ? 'selected' : '' }}>Select a partner HTE&hellip;</option>
                    @forelse ($htes as $hte)
                        <option value="{{ $hte->id }}"
                            data-address="{{ $hte->address ?? '' }}"
                            data-representative="{{ $hte->contact_person ?? '' }}"
                            data-position="{{ $hte->representative_position ?? '' }}"
                            {{ (int) $selectedAgreementHteId === (int) $hte->id ? 'selected' : '' }}>
                            {{ $hte->name }}
                        </option>
                    @empty
                        <option value="" disabled>No partner HTEs are registered for your campus.</option>
                    @endforelse
                </select>
                @error('hte_id')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label class="il-form-label" for="company_address">Host Company Address</label>
                    <textarea class="form-control" id="company_address" rows="2" readonly required></textarea>
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="company_representative">Company Representative's Name</label>
                    <input type="text" class="form-control" id="company_representative" readonly required>
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="representative_position">Representative's Position</label>
                    <input type="text" class="form-control" id="representative_position" readonly required>
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="agreement_required_hours_display">Required Internship Hours</label>
                    <input type="text" class="form-control" id="agreement_required_hours_display"
                           value="{{ $agreementRequiredHours }} hours" readonly>
                </div>
            </div>

            <input type="hidden" name="required_hours" id="required_hours" value="{{ $agreementRequiredHours }}">

            @error('address')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror

            <script>
            (function () {
                const directory = {};
                const select = document.getElementById('agreement_hte_id');
                const address = document.getElementById('company_address');
                const representative = document.getElementById('company_representative');
                const position = document.getElementById('representative_position');

                if (!select) return;

                Array.from(select.options).forEach(function (option) {
                    if (!option.value) return;
                    directory[option.value] = {
                        address: option.dataset.address || '',
                        representative: option.dataset.representative || '',
                        position: option.dataset.position || '',
                    };
                });

                function applyHte() {
                    const info = directory[select.value] || {};
                    if (address) address.value = info.address || '';
                    if (representative) representative.value = info.representative || '';
                    if (position) position.value = info.position || '';
                }

                select.addEventListener('change', applyHte);
                applyHte();
            })();
            </script>


        @elseif ($requirement->code === 'PARENT_CONSENT')
            @php
                $consentValue = fn (string $key, $default = '') => old(
                    $key,
                    $document?->fieldValue($key) ?? $default
                );

                $consentStudentName = $student?->user?->name ?? auth()->user()->name ?? '';
                $consentAddress = $consentValue('address', $agreementStudentAddress ?? $student?->address ?? '');
                $consentCourse = $consentValue('course', $courseMajorDefaults['course'] ?? '');
                $consentRequiredHours = (int) ($student?->program?->required_hours
                    ?? $internship?->required_hours
                    ?? 0);

                $storedCompanyName = $consentValue('company_name');
                $selectedConsentHte = $htes->firstWhere('name', $storedCompanyName)
                    ?? $internship?->deployment?->hte;

                $consentSignedDate = $consentValue('signed_date');
                // Always derived from the campus record - never from stored/old input.
                $consentSignedCity = $student?->campus?->address ?: ($student?->campus?->name ?? '');
            @endphp

            <div class="alert alert-info small mb-4">
                Your student information and required internship hours are filled automatically.
                Select your partner agency and the date you will sign the printed Consent Form.
                If this document was returned for correction, you can edit the date and resubmit it.
            </div>

            <h2 class="h6 fw-bold mt-2 mb-3">Student Information</h2>
            <div class="mb-3">
                <label class="il-form-label" for="consent_student_name">Student Name</label>
                <input type="text" class="form-control" id="consent_student_name"
                       value="{{ $consentStudentName }}" readonly>
                <input type="hidden" name="full_name" value="{{ $consentStudentName }}">
            </div>

            <div class="mb-3">
                <label class="il-form-label" for="consent_address">Student Address</label>
                <textarea class="form-control" id="consent_address" name="address" rows="2" required>{{ $consentAddress }}</textarea>
            </div>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="il-form-label" for="consent_course">Course / Program</label>
                    <input type="text" class="form-control" id="consent_course" name="course"
                           value="{{ $consentCourse }}" readonly required>
                </div>
                <div class="col-md-4">
                    <label class="il-form-label" for="consent_required_hours">Required Internship Hours</label>
                    <input type="text" class="form-control" id="consent_required_hours"
                           value="{{ $consentRequiredHours }} hours" readonly>
                    <input type="hidden" name="required_hours" value="{{ $consentRequiredHours }}">
                </div>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Partner Agency</h2>
            <div class="mb-3">
                <label class="il-form-label" for="consent_company_name">Partner Agency</label>
                <select class="form-select" id="consent_company_name" name="company_name" required>
                    <option value="" disabled {{ ! $selectedConsentHte ? 'selected' : '' }}>Select a partner agency&hellip;</option>
                    @forelse ($htes as $hte)
                        <option value="{{ $hte->name }}"
                                {{ $selectedConsentHte && (int) $selectedConsentHte->id === (int) $hte->id ? 'selected' : '' }}>
                            {{ $hte->name }}
                        </option>
                    @empty
                        <option value="" disabled>Your subject instructor hasn't listed partner agencies for this term yet.</option>
                    @endforelse
                </select>
                @error('company_name')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Signing Information</h2>
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="il-form-label" for="consent_signed_city">Signed at</label>
                    <input type="text" class="form-control" id="consent_signed_city" name="signed_city"
                           value="{{ $consentSignedCity }}" readonly required>
                    <div class="form-text">Automatically taken from your assigned campus location.</div>
                </div>

                <div class="col-md-5">
                    <label class="il-form-label" for="consent_signed_date">This day of</label>
                    <input type="date" class="form-control" id="consent_signed_date" name="signed_date"
                           value="{{ $consentSignedDate }}" required>
                    <div class="form-text">
                        Choose the date from the calendar. If the form was returned, you can change this date before resubmitting.
                    </div>
                    @error('signed_date')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

        @elseif ($requirement->code === 'TRAINING_LIABILITY')
            @php
                $liabilityValue = fn (string $key, $default = '') => old(
                    $key,
                    $document?->fieldValue($key) ?? $default
                );
                $studentName = $student?->user?->name ?? '';
                $storedCompanyName = $liabilityValue('company_name');
                $selectedLiabilityHte = $htes->firstWhere('name', $storedCompanyName)
                    ?? $internship?->deployment?->hte;
            @endphp

            <div class="alert alert-info small mb-4">
                Your name is filled automatically from your student account. Select your registered Host Training Establishment (HTE) from the list.
            </div>

            <h2 class="h6 fw-bold mt-2 mb-3">Student Information</h2>
            <div class="mb-3">
                <label class="il-form-label" for="liability_student_name">Student Name</label>
                <input type="text" class="form-control" id="liability_student_name"
                       value="{{ $studentName }}" readonly>
                <input type="hidden" name="student_name" value="{{ $studentName }}">
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Training</h2>
            <div class="mb-3">
                <label class="il-form-label" for="liability_hte_id">Company / HTE</label>
                <select class="form-select" id="liability_hte_id" name="hte_id" required>
                    <option value="" disabled {{ ! $selectedLiabilityHte ? 'selected' : '' }}>Select a partner agency&hellip;</option>
                    @forelse ($htes as $hte)
                        <option value="{{ $hte->id }}"
                                {{ $selectedLiabilityHte && (int) $selectedLiabilityHte->id === (int) $hte->id ? 'selected' : '' }}>
                            {{ $hte->name }}
                        </option>
                    @empty
                        <option value="" disabled>Your subject instructor hasn't listed partner agencies for this term yet.</option>
                    @endforelse
                </select>
                @error('hte_id')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Consent</h2>
            <div class="mb-3">
                <label class="il-form-label" for="parent_guardian_name">Parent / Guardian Name</label>
                <input type="text" class="form-control" id="parent_guardian_name" name="parent_guardian_name"
                       value="{{ $liabilityValue('parent_guardian_name') }}" required>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="overtime_acknowledged" name="overtime_acknowledged" value="1"
                       {{ old('overtime_acknowledged', $document?->fieldValue('overtime_acknowledged')) ? 'checked' : '' }} required>
                <label class="form-check-label" for="overtime_acknowledged">
                    I understand and agree that the training involves overtime hours as indicated by the company.
                </label>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="parent_guardian_consent" name="parent_guardian_consent" value="1"
                       {{ old('parent_guardian_consent', $document?->fieldValue('parent_guardian_consent')) ? 'checked' : '' }} required>
                <label class="form-check-label" for="parent_guardian_consent">
                    Parent/Guardian consent acknowledged
                </label>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="liability_acknowledged" name="liability_acknowledged" value="1"
                       {{ old('liability_acknowledged', $document?->fieldValue('liability_acknowledged')) ? 'checked' : '' }} required>
                <label class="form-check-label" for="liability_acknowledged">
                    I acknowledge the training liability conditions stated in the official form.
                </label>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Notarization</h2>
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="il-form-label" for="signed_city">Signed at</label>
                    <input type="text" class="form-control" id="signed_city" name="signed_city"
                           value="{{ $liabilityValue('signed_city', $student?->campus?->address ?? $student?->campus?->name ?? '') }}" readonly required>
                    <div class="form-text">Automatically taken from your assigned campus location.</div>
                </div>
                <div class="col-md-5">
                    <label class="il-form-label" for="signed_date">This day of</label>
                    <input type="date" class="form-control" id="signed_date" name="signed_date"
                           value="{{ $liabilityValue('signed_date') }}" required>
                    <div class="form-text">Choose the signing date from the calendar. The printable form will automatically show the day, month, and year.</div>
                    @error('signed_date')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

        @elseif ($requirement->code === 'INTERNSHIP_RELEASE_FORM')
            @php
                $releaseValue = fn (string $key, $default = '') => old(
                    $key,
                    $document?->fieldValue($key) ?? $default
                );
                $releaseCompanyName = $releaseValue('company_name');
                $selectedReleaseHte = $htes->firstWhere('name', $releaseCompanyName)
                    ?? $internship?->deployment?->hte;
                $releaseRequiredHours = (int) ($student->program?->required_hours
                    ?? $internship->required_hours
                    ?? 0);
                $releaseStartDate = $releaseValue('start_date', $internship?->deployment?->start_date?->format('Y-m-d') ?? '');
                $releaseEndDate = $releaseValue('end_date', $internship?->deployment?->end_date?->format('Y-m-d') ?? '');
            @endphp

            <div class="alert alert-info small mb-4">
                Select your registered partner agency. Its address is filled automatically.
                Choose the internship start and end dates using the calendar. Required hours are
                taken automatically from your program/internship record.
            </div>

            <h2 class="h6 fw-bold mt-2 mb-3">Student Information</h2>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="il-form-label" for="release_course">Course</label>
                    <input type="text" class="form-control" id="release_course"
                           name="course" value="{{ $releaseValue('course', $courseMajorDefaults['course']) }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="il-form-label" for="release_major">Major</label>
                    <input type="text" class="form-control" id="release_major"
                           name="major" value="{{ $releaseValue('major', $courseMajorDefaults['major']) }}" readonly>
                </div>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Internship</h2>
            <div class="mb-3">
                <label class="il-form-label" for="release_hte_id">Cooperating Company/Agency/Firm</label>
                <select class="form-select" id="release_hte_id" name="hte_id" required>
                    <option value="" disabled {{ ! $selectedReleaseHte ? 'selected' : '' }}>Select a partner agency&hellip;</option>
                    @forelse ($htes as $hte)
                        <option value="{{ $hte->id }}"
                                data-name="{{ $hte->name }}"
                                data-address="{{ $hte->address ?? '' }}"
                                {{ $selectedReleaseHte && (int) $selectedReleaseHte->id === (int) $hte->id ? 'selected' : '' }}>
                            {{ $hte->name }}
                        </option>
                    @empty
                        <option value="" disabled>Your subject instructor hasn't listed partner agencies for this term yet.</option>
                    @endforelse
                </select>
                @error('hte_id')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="il-form-label" for="release_company_address">Company/Agency/Firm Address</label>
                <textarea class="form-control" id="release_company_address" name="company_address" rows="2" readonly required>{{ $releaseValue('company_address', $selectedReleaseHte?->address ?? '') }}</textarea>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Internship Period</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="il-form-label" for="release_start_date">From</label>
                    <input type="date" class="form-control" id="release_start_date" name="start_date"
                           value="{{ $releaseStartDate }}" required>
                    @error('start_date')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="release_end_date">To</label>
                    <input type="date" class="form-control" id="release_end_date" name="end_date"
                           value="{{ $releaseEndDate }}" required>
                    @error('end_date')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="release_required_hours">Required Hours</label>
                    <input type="text" class="form-control" id="release_required_hours"
                           value="{{ $releaseRequiredHours }} hours" readonly>
                    <input type="hidden" name="required_hours" value="{{ $releaseRequiredHours }}">
                    <div class="form-text">Automatically taken from your program/internship record.</div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="small text-secondary pb-2" id="release_period_hint" aria-live="polite"></div>
                </div>
            </div>

            <script>
            (function () {
                const select = document.getElementById('release_hte_id');
                const address = document.getElementById('release_company_address');
                const start = document.getElementById('release_start_date');
                const end = document.getElementById('release_end_date');
                const hint = document.getElementById('release_period_hint');

                function applyHte() {
                    const option = select ? select.options[select.selectedIndex] : null;
                    if (address) address.value = option?.dataset?.address || '';
                }

                function updatePeriod() {
                    if (!start || !end || !hint) return;
                    if (!start.value || !end.value) {
                        hint.textContent = '';
                        return;
                    }

                    const from = new Date(start.value + 'T00:00:00');
                    const to = new Date(end.value + 'T00:00:00');
                    const days = Math.round((to - from) / 86400000) + 1;

                    if (days < 1) {
                        hint.textContent = 'The end date must be on or after the start date.';
                        hint.className = 'small text-danger pb-2';
                        end.setCustomValidity('The end date must be on or after the start date.');
                        return;
                    }

                    end.setCustomValidity('');
                    hint.className = 'small text-secondary pb-2';
                    hint.textContent = days + ' calendar day' + (days === 1 ? '' : 's') + ' selected. Required hours remain based on your program record.';
                }

                if (select) select.addEventListener('change', applyHte);
                if (start) start.addEventListener('change', updatePeriod);
                if (end) end.addEventListener('change', updatePeriod);
                applyHte();
                updatePeriod();
            })();
            </script>

        @elseif ($requirement->code === 'INTERNSHIP_ACADEMIC_PRACTICUM_COURSE')
            @php
                $practicumValue = fn (string $key, $default = '') => old(
                    $key,
                    $document?->fieldValue($key) ?? $default
                );
                $storedCompanyName = $practicumValue('company_name');
                $selectedPracticumHte = $htes->firstWhere('name', $storedCompanyName)
                    ?? $internship?->deployment?->hte;
                $practicumRequiredHours = (int) ($student->program?->required_hours
                    ?? $internship?->required_hours
                    ?? 0);
                $practicumCourse = $courseMajorDefaults['course'];
                $practicumMajor = $courseMajorDefaults['major'];
                $practicumSupervisor = $practicumValue('supervisor');
            @endphp

            <div class="alert alert-info small mb-4">
                Select your registered partner HTE. The company address and required internship hours are filled automatically from the partner agency records.
                Enter your assigned company supervisor manually.
            </div>

            <h2 class="h6 fw-bold mt-2 mb-3">Academic Information</h2>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="il-form-label" for="practicum_course">Course</label>
                    <input type="text" class="form-control" id="practicum_course" name="course"
                           value="{{ $practicumValue('course', $practicumCourse) }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="il-form-label" for="practicum_major">Major</label>
                    <input type="text" class="form-control" id="practicum_major" name="major"
                           value="{{ $practicumValue('major', $practicumMajor) }}" readonly>
                </div>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Training Establishment</h2>
            <div class="mb-3">
                <label class="il-form-label" for="practicum_hte_id">Company Name</label>
                <select class="form-select" id="practicum_hte_id" name="hte_id" required>
                    <option value="" disabled {{ ! $selectedPracticumHte ? 'selected' : '' }}>Select a partner agency&hellip;</option>
                    @forelse ($htes as $hte)
                        <option value="{{ $hte->id }}"
                                data-address="{{ $hte->address ?? '' }}"
                                data-supervisor="{{ $hte->contact_person ?? '' }}"
                                data-position="{{ $hte->representative_position ?? '' }}"
                                {{ $selectedPracticumHte && (int) $selectedPracticumHte->id === (int) $hte->id ? 'selected' : '' }}>
                            {{ $hte->name }}
                        </option>
                    @empty
                        <option value="" disabled>Your subject instructor hasn't listed partner agencies for this term yet.</option>
                    @endforelse
                </select>
                @error('hte_id')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
                <div class="form-text">Only your program's partner agencies for this term are available.</div>
            </div>

            <div class="mb-3">
                <label class="il-form-label" for="practicum_company_address">Company Address</label>
                <textarea class="form-control" id="practicum_company_address" name="company_address" rows=2 readonly required>{{ $practicumValue('company_address', $selectedPracticumHte?->address ?? '') }}</textarea>
                <div class="form-text">Automatically filled from the selected HTE record.</div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="il-form-label" for="practicum_supervisor">Supervisor</label>
                    <input type="text" class="form-control" id="practicum_supervisor" name="supervisor"
                           value="{{ $practicumSupervisor }}" placeholder="Enter your assigned supervisor" required>
                    <div class="form-text">Enter the name of your assigned training supervisor.</div>
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="practicum_department">Department</label>
                    <input type="text" class="form-control" id="practicum_department" name="department"
                           value="{{ $practicumValue('department') }}" placeholder="e.g. IT Department" required>
                </div>
            </div>

            <h2 class="h6 fw-bold mt-4 mb-3">Internship Details</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="il-form-label" for="practicum_workdays">Workdays</label>
                    <input type="text" class="form-control" id="practicum_workdays" name="workdays"
                           value="{{ $practicumValue('workdays') }}" placeholder="e.g. Monday–Friday" required>
                </div>
                <div class="col-md-6">
                    <label class="il-form-label" for="practicum_required_hours">Required Hours</label>
                    <input type="text" class="form-control" id="practicum_required_hours"
                           value="{{ $practicumRequiredHours }} hours" readonly>
                    <input type="hidden" name="required_hours" value="{{ $practicumRequiredHours }}">
                    <div class="form-text">Automatically taken from your program/internship record.</div>
                </div>
            </div>

            <script>
            (function () {
                const select = document.getElementById('practicum_hte_id');
                const address = document.getElementById('practicum_company_address');
                if (!select) return;

                function applyHte() {
                    const option = select.options[select.selectedIndex];
                    if (!option) return;
                    if (address) address.value = option.dataset.address || '';
                }

                select.addEventListener('change', applyHte);
                applyHte();
            })();
            </script>

        @else
            @foreach ($requirement->groupedFieldDefinitions() as $groupLabel => $fields)
                @if ($groupLabel)
                    <h2 class="h6 fw-bold mt-4 mb-2">{{ $groupLabel }}</h2>
                @endif

                @foreach ($fields as $field)
                    <div class="mb-3">
                        @if ($field->field_type === 'rating_scale')
                            <label class="il-form-label">{{ $field->label }}</label>
                            <div class="d-flex gap-3">
                                @foreach (['5', '4', '3', '2', '1'] as $value)
                                    <label class="d-flex flex-column align-items-center small text-secondary" style="gap: 2px;">
                                        <input type="radio" name="{{ $field->field_key }}" value="{{ $value }}" {{ old($field->field_key, $document?->fieldValue($field->field_key)) == $value ? 'checked' : '' }} {{ $field->is_required ? 'required' : '' }}>
                                        {{ $value }}
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($requirement->code === 'STUDENT_HTE_EVALUATION' && in_array($field->field_key, ['rater_name', 'agency_name', 'rater_type']))
                            @php
                                // Known from the student's own records - shown locked so the
                                // printed Form 23c always carries the right name, agency and rater type.
                                $lockedValue = match ($field->field_key) {
                                    'rater_name' => auth()->user()->name,
                                    'agency_name' => auth()->user()->student?->currentInternship?->deployment?->hte?->name
                                        ?: old($field->field_key, $document?->fieldValue($field->field_key)),
                                    default => 'Student',
                                };
                            @endphp
                            <label class="il-form-label" for="{{ $field->field_key }}">{{ $field->label }}</label>
                            <input type="text" class="form-control" id="{{ $field->field_key }}" value="{{ $lockedValue }}" readonly>
                            <input type="hidden" name="{{ $field->field_key }}" value="{{ $lockedValue }}">
                        @else
                            <label class="il-form-label" for="{{ $field->field_key }}">{{ $field->label }}</label>
                            @if ($field->field_key === 'hte_name')
                                @php $selectedHte = old($field->field_key, $document?->fieldValue($field->field_key)); @endphp
                                <select class="form-select" id="{{ $field->field_key }}" name="{{ $field->field_key }}" {{ $field->is_required ? 'required' : '' }}>
                                    <option value="" disabled {{ ! $selectedHte ? 'selected' : '' }}>Select a partner agency&hellip;</option>
                                    @forelse ($htes as $hte)
                                        <option value="{{ $hte->name }}" {{ $selectedHte === $hte->name ? 'selected' : '' }}>{{ $hte->name }}</option>
                                    @empty
                                        <option value="" disabled>Your subject instructor hasn't listed partner agencies for this term yet.</option>
                                    @endforelse
                                </select>
                            @elseif (in_array($field->field_key, ['civil_status', 'sex', 'sex_gender', 'course', 'major']))
                                @php
                                    $selectedValue = old($field->field_key, $document?->fieldValue($field->field_key));
                                    // Civil Status/Sex options mirror this field's own seeded
                                    // "enum:..." validation rule exactly, so what's offered here
                                    // always matches what the backend format check accepts.
                                    // Course is a fixed list of the actual PSU programs; Major
                                    // only has real choices (FM/OM) for Business Administration -
                                    // every other course only offers N/A. The Major <select>'s
                                    // options update live when Course changes (see script below).
                                    $fieldOptions = match ($field->field_key) {
                                        'civil_status' => ['Single', 'Married', 'Widowed', 'Separated', 'Divorced', 'Annulled'],
                                        'sex' => ['Male', 'Female'],
                                        'sex_gender' => ['Male', 'Female'],
                                        'course' => $courseOptions ?? [$courseMajorDefaults['course']],
                                        'major' => $selectedValue && $selectedValue !== 'N/A' ? [$selectedValue] : ['N/A'],
                                        default => [],
                                    };
                                    if ($field->field_key === 'course' && ! $selectedValue) {
                                        $selectedValue = $courseMajorDefaults['course'];
                                    }
                                    if ($field->field_key === 'major' && ! $selectedValue) {
                                        $selectedValue = $courseMajorDefaults['major'];
                                        $fieldOptions = [$selectedValue];
                                    }
                                @endphp
                                <select class="form-select" id="{{ $field->field_key }}" name="{{ $field->field_key }}" {{ $field->is_required ? 'required' : '' }}>
                                    @if (! in_array($field->field_key, ['major', 'course']))
                                        <option value="" disabled {{ ! $selectedValue ? 'selected' : '' }}>Select&hellip;</option>
                                    @endif
                                    @foreach ($fieldOptions as $option)
                                        <option value="{{ $option }}" {{ $selectedValue === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif ($field->field_type === 'textarea')
                                <textarea class="form-control" id="{{ $field->field_key }}" name="{{ $field->field_key }}" rows="3" {{ $field->is_required ? 'required' : '' }}>{{ old($field->field_key, $document?->fieldValue($field->field_key)) }}</textarea>
                            @else
                                <input type="text" class="form-control" id="{{ $field->field_key }}" name="{{ $field->field_key }}" value="{{ old($field->field_key, $document?->fieldValue($field->field_key)) }}" {{ $field->is_required ? 'required' : '' }}>
                            @endif
                        @endif
                    </div>
                @endforeach
            @endforeach
        @endif

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="il-btn-primary">Submit for review</button>
            <a href="{{ route('student.documents') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>

@if ($htes->isNotEmpty())
    @php
        // Built as its own variable, then passed as a single argument to
        // @json below - @json splits its raw expression on every comma to
        // look for an optional $options/$depth argument, with no idea
        // about bracket nesting, so handing it a multi-key array literal
        // directly here would get silently truncated mid-expression.
        $hteDirectory = $htes->mapWithKeys(fn ($hte) => [
            $hte->name => [
                'address' => $hte->address,
                'head' => $hte->contact_person,
                'contact_number' => $hte->contact_phone,
            ],
        ]);
    @endphp
<script>
(function () {
    // Auto-fills Address/Head/Contact Number from the coordinator's saved
    // HTE record whenever the student picks an agency - keyed by name since
    // that's what the select's value already stores (see hte_name option
    // above). Fields stay editable afterward in case the on-file HTE
    // details are outdated for this particular posting.
    const hteDirectory = @json($hteDirectory);

    const nameSelect = document.getElementById('hte_name');
    const addressField = document.getElementById('hte_address');
    const headField = document.getElementById('hte_head');
    const contactField = document.getElementById('hte_contact_number');

    if (!nameSelect) {
        return;
    }

    function applyHte(hteName, { onlyIfEmpty } = { onlyIfEmpty: false }) {
        const info = hteDirectory[hteName];
        if (!info) {
            return;
        }

        if (addressField && (!onlyIfEmpty || !addressField.value.trim())) {
            addressField.value = info.address ?? '';
        }
        if (headField && (!onlyIfEmpty || !headField.value.trim())) {
            headField.value = info.head ?? '';
        }
        if (contactField && (!onlyIfEmpty || !contactField.value.trim())) {
            contactField.value = info.contact_number ?? '';
        }
    }

    nameSelect.addEventListener('change', function () {
        applyHte(nameSelect.value);
    });

    // On first load of a fresh form (nothing entered yet for these fields),
    // pre-fill from whatever agency is already selected.
    if (nameSelect.value) {
        applyHte(nameSelect.value, { onlyIfEmpty: true });
    }
})();
</script>
@endif

<script>
(function () {
    // Keeps the Major <select> in sync with whichever Course is picked -
    // a course with majors offers them; any other course collapses Major
    // down to a single locked "N/A" option.
    const courseSelect = document.getElementById('course');
    const majorSelect = document.getElementById('major');

    if (!courseSelect || !majorSelect) {
        return;
    }

    // Built from the campus's programs (Programs page): course -> majors.
    const majorOptionsByCourse = @json($majorOptionsByCourse ?? []);
    const defaultMajorOptions = ['N/A'];

    function rebuildMajorOptions() {
        const options = majorOptionsByCourse[courseSelect.value] || defaultMajorOptions;
        const previousValue = majorSelect.value;

        majorSelect.innerHTML = '';
        options.forEach(function (value) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            majorSelect.appendChild(option);
        });

        majorSelect.value = options.includes(previousValue) ? previousValue : options[0];
    }

    courseSelect.addEventListener('change', rebuildMajorOptions);
})();
</script>
@endsection
