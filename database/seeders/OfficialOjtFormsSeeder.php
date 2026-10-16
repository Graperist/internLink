<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
use Illuminate\Database\Seeder;

class OfficialOjtFormsSeeder extends Seeder
{
    public function run(): void
    {
        $requirements = [
            // Pre-OJT / Before deployment — source checklist order.
            ['code' => 'APPLICATION_FOR_OJT', 'name' => 'Form 1 – Application for Internship', 'stage' => 'pre_deployment', 'form_number' => '1', 'display_order' => 10, 'audience' => 'student', 'workflow_mode' => 'print_sign'],
            // Medical Certificate and Psychological Test are now attached to
            // Form 1 itself (document_attachments), not separate requirements.
            ['code' => 'MEDICAL_CERTIFICATE', 'name' => 'Medical Certificate from Government Physician', 'stage' => 'pre_deployment', 'form_number' => null, 'display_order' => 20, 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => false, 'is_required' => false],
            ['code' => 'PSYCHOLOGICAL_TEST', 'name' => 'Psychological Test Result', 'stage' => 'pre_deployment', 'form_number' => null, 'display_order' => 30, 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => false, 'is_required' => false],
            ['code' => 'CERTIFICATE_OF_UNITS_EARNED', 'name' => 'Form 2 – Certification of Units Earned for Practicum/Internship', 'stage' => 'pre_deployment', 'form_number' => '2', 'display_order' => 40, 'audience' => 'student', 'workflow_mode' => 'print_sign'],
            ['code' => 'PARENT_CONSENT', 'name' => 'Form 3 – Consent and Waiver', 'stage' => 'pre_deployment', 'form_number' => '3', 'display_order' => 50, 'audience' => 'student', 'workflow_mode' => 'print_sign'],
            ['code' => 'PARENT_GUARDIAN_ID', 'name' => 'Photocopy of Parent/Guardian Valid ID', 'stage' => 'pre_deployment', 'form_number' => null, 'display_order' => 60, 'audience' => 'student', 'workflow_mode' => 'upload'],
            ['code' => 'ENDORSEMENT_LETTER', 'name' => 'Form 6 – Endorsement Letter (Rev. 2, 2024)', 'stage' => 'pre_deployment', 'form_number' => '6', 'display_order' => 70, 'audience' => 'student', 'workflow_mode' => 'print_sign'],
            ['code' => 'MOA', 'name' => 'Form 10 – Memorandum of Agreement (Outside InternLink Scope)', 'stage' => 'pre_deployment', 'form_number' => '10', 'display_order' => 80, 'audience' => 'coordinator', 'workflow_mode' => 'upload', 'is_active' => false, 'is_required' => false],
            ['code' => 'OJT_RESUME', 'name' => 'Form 11 – Internship Resume', 'stage' => 'pre_deployment', 'form_number' => '11', 'display_order' => 90, 'audience' => 'student', 'workflow_mode' => 'print_sign'],
            ['code' => 'INTERNSHIP_AGREEMENT', 'name' => 'Form 12 – Internship Agreement', 'stage' => 'pre_deployment', 'form_number' => '12', 'display_order' => 100, 'audience' => 'student', 'workflow_mode' => 'print_sign'],
            ['code' => 'INTERNSHIP_RELEASE_FORM', 'name' => 'Form 15 – Internship Release Form', 'stage' => 'pre_deployment', 'form_number' => '15', 'display_order' => 110, 'audience' => 'student', 'workflow_mode' => 'print_sign'],
            ['code' => 'TRAINING_LIABILITY', 'name' => 'Form 18 – Training Agreement and Liability Waiver', 'stage' => 'pre_deployment', 'form_number' => '18', 'display_order' => 120, 'audience' => 'student', 'workflow_mode' => 'print_sign'],

            // Post-OJT / After training — source checklist order.
            ['code' => 'NARRATIVE_REPORT', 'name' => 'Narrative Report', 'stage' => 'post_deployment', 'form_number' => null, 'display_order' => 220, 'audience' => 'student', 'workflow_mode' => 'upload'],
            // Legacy Form 4 Weekly Journal is retired. Form 19 is now the live
            // weekly work-reporting feature used during the internship.
            ['code' => 'WEEKLY_JOURNAL', 'name' => 'Form 4 – Weekly Journal (Legacy)', 'stage' => 'post_deployment', 'form_number' => '4', 'display_order' => 230, 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => false, 'is_required' => false],
            ['code' => 'EVALUATION_FORM', 'name' => 'Form 13 – Internship Evaluation Form', 'stage' => 'post_deployment', 'form_number' => '13', 'display_order' => 240, 'audience' => 'hte', 'workflow_mode' => 'review_only'],
            ['code' => 'INTERNSHIP_TIME_FRAME', 'name' => 'Form 17 – Internship Time Frame', 'stage' => 'active', 'form_number' => '17', 'display_order' => 250, 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_repeating' => true, 'entry_cadence' => 'daily'],
            ['code' => 'WEEKLY_REPORT', 'name' => 'Form 19 – Internship/Practicum Weekly Report', 'stage' => 'active', 'form_number' => '19', 'display_order' => 260, 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_repeating' => true, 'entry_cadence' => 'daily'],
            ['code' => 'STUDENT_FEEDBACK_FORM', 'name' => 'Form 20 – Student-Intern’s Feedback Form', 'stage' => 'post_deployment', 'form_number' => '20', 'display_order' => 270, 'audience' => 'student', 'workflow_mode' => 'review_only'],
            ['code' => 'TRAINING_SUPERVISOR_FEEDBACK', 'name' => 'Form 21 – Training Supervisor’s Feedback Form', 'stage' => 'post_deployment', 'form_number' => '21', 'display_order' => 280, 'audience' => 'hte', 'workflow_mode' => 'review_only'],
            ['code' => 'PARTNER_AGENCY_EVAL_INSTRUCTOR', 'name' => 'Form 23a – Evaluation Instrument of PSU Partner Agencies (Subject Instructor)', 'stage' => 'post_deployment', 'form_number' => '23a', 'display_order' => 290, 'audience' => 'instructor', 'workflow_mode' => 'review_only'],
            ['code' => 'PARTNER_AGENCY_EVAL_SELF', 'name' => 'Form 23b – Evaluation Instrument of PSU Partner Agencies (Self Ratee)', 'stage' => 'post_deployment', 'form_number' => '23b', 'display_order' => 300, 'audience' => 'hte', 'workflow_mode' => 'review_only'],
            ['code' => 'STUDENT_HTE_EVALUATION', 'name' => 'Form 23c – Evaluation Instrument of PSU Partner Agencies (Student)', 'stage' => 'post_deployment', 'form_number' => '23c', 'display_order' => 310, 'audience' => 'student', 'workflow_mode' => 'review_only'],

            // Seminars are announced by the Subject Instructor (Announcements),
            // not submitted as documents - kept inactive/not required.
            ['code' => 'PRE_OJT_SEMINAR', 'name' => 'Pre-OJT Seminar (Announcement only)', 'stage' => 'pre_deployment', 'form_number' => null, 'display_order' => 9030, 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => false, 'is_required' => false],
            ['code' => 'POST_OJT_SEMINAR', 'name' => 'Post-OJT Seminar (Announcement only)', 'stage' => 'post_deployment', 'form_number' => null, 'display_order' => 9040, 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => false, 'is_required' => false],
            // Deprecated/legacy requirements still kept in the database so old
            // document rows remain readable, but they no longer appear in the live checklist.
            ['code' => 'DAILY_JOURNAL', 'name' => 'Daily Journal (Legacy)', 'stage' => 'active', 'form_number' => null, 'display_order' => 9000, 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => false],
            ['code' => 'INTERNSHIP_ACADEMIC_PRACTICUM_COURSE', 'name' => 'Internship-Academic Practicum Course (Legacy)', 'stage' => 'pre_deployment', 'form_number' => null, 'display_order' => 9010, 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => false],
            ['code' => 'CERTIFICATE_OF_COMPLETION', 'name' => 'Certificate of Completion (Legacy)', 'stage' => 'post_deployment', 'form_number' => null, 'display_order' => 9020, 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => false],
        ];

        // Reviewer is distinct from audience: audience identifies who fills/submits
        // the form; reviewer_role records the in-system role that checks the returned
        // record. For the six evaluation forms it is the RECIPIENT of the form
        // (see App\Support\EvaluationRouting). The official source documents commonly require physical signatories,
        // which remain an instructor-managed offline process.
        $reviewers = [
            'APPLICATION_FOR_OJT' => 'instructor',
            'MEDICAL_CERTIFICATE' => 'instructor',
            'PSYCHOLOGICAL_TEST' => 'instructor',
            'CERTIFICATE_OF_UNITS_EARNED' => 'instructor',
            'PARENT_CONSENT' => 'instructor',
            'PARENT_GUARDIAN_ID' => 'instructor',
            'ENDORSEMENT_LETTER' => 'instructor',
            'OJT_RESUME' => 'instructor',
            'INTERNSHIP_AGREEMENT' => 'instructor',
            'INTERNSHIP_RELEASE_FORM' => 'instructor',
            'TRAINING_LIABILITY' => 'instructor',
            'NARRATIVE_REPORT' => 'instructor',
            'EVALUATION_FORM' => 'instructor',
            'INTERNSHIP_TIME_FRAME' => 'instructor',
            'WEEKLY_REPORT' => 'instructor',
            'STUDENT_FEEDBACK_FORM' => 'hte',            // Form 20 -> HTE (Evaluations Received)
            'TRAINING_SUPERVISOR_FEEDBACK' => 'student', // Form 21 -> student (My HTE Info)
            'PARTNER_AGENCY_EVAL_INSTRUCTOR' => 'hte',  // Form 23a -> HTE (Evaluations Received)
            'PARTNER_AGENCY_EVAL_SELF' => 'coordinator',  // Form 23b -> Coordinator (Documents)
            'STUDENT_HTE_EVALUATION' => 'hte',          // Form 23c -> HTE (Evaluations Received)
        ];

        foreach ($requirements as &$definition) {
            $definition['reviewer_role'] = $reviewers[$definition['code']] ?? 'instructor';
            $definition['source_reference'] = match ($definition['code']) {
                'APPLICATION_FOR_OJT', 'CERTIFICATE_OF_UNITS_EARNED', 'PARENT_CONSENT',
                'ENDORSEMENT_LETTER', 'OJT_RESUME', 'INTERNSHIP_AGREEMENT',
                'EVALUATION_FORM', 'INTERNSHIP_RELEASE_FORM', 'INTERNSHIP_TIME_FRAME',
                'TRAINING_LIABILITY', 'WEEKLY_REPORT', 'STUDENT_FEEDBACK_FORM',
                'TRAINING_SUPERVISOR_FEEDBACK', 'PARTNER_AGENCY_EVAL_INSTRUCTOR',
                'PARTNER_AGENCY_EVAL_SELF', 'STUDENT_HTE_EVALUATION'
                    => 'PSU OJT-24-25 source bundle (2024 form set)',
                default => 'OJT requirements checklist / supporting requirement',
            };
        }
        unset($definition);

        // Update/create the catalog for every campus. This intentionally uses
        // updateOrCreate so repeated `php artisan db:seed` calls synchronize
        // the live checklist instead of preserving stale field names/order.
        foreach (Campus::query()->get() as $campus) {
            foreach ($requirements as $definition) {
                $requirement = DocumentRequirement::updateOrCreate(
                    ['campus_id' => $campus->id, 'code' => $definition['code']],
                    [
                        'form_number' => $definition['form_number'] ?? null,
                        'name' => $definition['name'],
                        'display_order' => $definition['display_order'],
                        'audience' => $definition['audience'],
                        'workflow_mode' => $definition['workflow_mode'],
                        'stage' => $definition['stage'],
                        'is_active' => $definition['is_active'] ?? true,
                        'is_required' => ($definition['is_active'] ?? true) ? ($definition['is_required'] ?? true) : false,
                        'is_repeating' => $definition['is_repeating'] ?? false,
                        'entry_cadence' => $definition['entry_cadence'] ?? null,
                        'reviewer_role' => $definition['reviewer_role'] ?? 'instructor',
                        'source_reference' => $definition['source_reference'] ?? null,
                    ]
                );

                $this->syncFields($requirement, $this->fieldsFor($definition['code']));
            }
        }
    }

    private function syncFields(DocumentRequirement $requirement, array $fields): void
    {
        if (! $fields) {
            DocumentFieldDefinition::where('document_requirement_id', $requirement->id)->delete();
            return;
        }

        $keep = [];

        foreach ($fields as $field) {
            $keep[] = $field['field_key'];

            DocumentFieldDefinition::updateOrCreate(
                [
                    'document_requirement_id' => $requirement->id,
                    'field_key' => $field['field_key'],
                ],
                [
                    'label' => $field['label'],
                    'group_label' => $field['group_label'] ?? null,
                    'field_type' => $field['field_type'] ?? 'text',
                    'validation_rule' => $field['validation_rule'] ?? null,
                    'is_required' => $field['is_required'] ?? true,
                    'sort_order' => $field['sort_order'] ?? 0,
                ]
            );
        }

        // Remove stale field definitions from the live forms, e.g. the old
        // endorsement recipient_position field. Existing DocumentFieldValue
        // rows are intentionally left untouched for historical records.
        DocumentFieldDefinition::where('document_requirement_id', $requirement->id)
            ->when($keep, fn ($query) => $query->whereNotIn('field_key', $keep))
            ->delete();
    }

    private function fieldsFor(string $code): array
    {
        $fields = [];
        $add = function (string $key, string $label, string $type = 'text', ?string $group = null, ?string $rule = null, bool $required = true, int $order = 0) use (&$fields): void {
            $fields[] = [
                'field_key' => $key,
                'label' => $label,
                'field_type' => $type,
                'group_label' => $group,
                'validation_rule' => $rule,
                'is_required' => $required,
                'sort_order' => $order,
            ];
        };

        switch ($code) {
            case 'APPLICATION_FOR_OJT':
                $order = 1;
                foreach ([
                    ['surname', 'Surname'], ['given_name', 'Given Name'], ['middle_name', 'Middle Name'],
                    ['age', 'Age', 'number', null, 'age'],
                    ['civil_status', 'Civil Status', 'select', null, 'enum:Single,Married,Widowed,Separated,Divorced,Annulled'],
                    ['sex', 'Sex - Gender', 'select', null, 'enum:Male,Female'],
                    ['contact_number', 'Contact Number', 'text', null, 'phone_ph'],
                    ['course', 'Course'], ['year_section', 'Year & Section'], ['major', 'Major', 'text', null, null, false],
                    ['parent_guardian_name', 'Parent/Guardian'], ['current_address', 'Current Address', 'textarea'], ['home_address', 'Home Address', 'textarea'],
                ] as $f) {
                    $add($f[0], $f[1], $f[2] ?? 'text', 'Personal Profile', $f[4] ?? null, $f[5] ?? true, $order++);
                }
                $add('passed_units', 'Total Passed Units', 'number', 'Academic Credentials/Qualifications', 'numeric', false, 20);
                foreach (range(1, 5) as $row) {
                    foreach ([
                        ['sem', 'Semester'], ['sy', 'School Year'], ['subject', 'Subject'], ['passing', 'Passing'],
                        ['failing', 'Failing'], ['grade', 'Grade'], ['signature', 'Signature'], ['date', 'Date'],
                    ] as $col => $meta) {
                        $add("academic_{$row}_{$meta[0]}", "Row {$row} – {$meta[1]}", 'text', 'Academic Credentials/Qualifications', null, false, 20 + (($row - 1) * 8) + $col);
                    }
                }
                foreach ([
                    ['hte_name', 'Name of the Firm/Agency/Company', 'text', null, 'hte'],
                    ['hte_address', 'Address', 'textarea'],
                    ['hte_head', 'Head', 'text', null, null, false],
                    ['hte_contact_number', 'Contact Number', 'text', null, 'phone_ph', false],
                    ['hte_number_of_hours', 'Number of Hours', 'number', null, 'numeric'],
                ] as $i => $f) {
                    $add($f[0], $f[1], $f[2], 'Firm/Agency/Company for Internship', $f[4] ?? null, $f[5] ?? true, 70 + $i);
                }
                break;

            case 'CERTIFICATE_OF_UNITS_EARNED':
                $add('surname', 'Last Name', 'text', 'Student Information', null, true, 1);
                $add('given_name', 'First Name', 'text', 'Student Information', null, true, 2);
                $add('middle_name', 'Middle Name', 'text', 'Student Information', null, false, 3);
                $add('course', 'Course', 'text', 'Student Information', null, true, 4);
                $add('major', 'Major', 'text', 'Student Information', null, false, 5);
                // Preserve the stable JSON keys consumed by the dedicated Form 2
                // fill/print templates. Each year-level value contains rows with
                // semester 1 + units, semester 2 + units, summer + units, and total.
                foreach (['first' => 'First Year', 'second' => 'Second Year', 'third' => 'Third Year', 'fourth' => 'Fourth Year'] as $year => $label) {
                    $add("{$year}_year_courses", "{$label} – Courses, Semester Units and Total", 'textarea', 'Courses Earned', null, true, ['first' => 10, 'second' => 20, 'third' => 30, 'fourth' => 40][$year]);
                }
                $add('record_total', 'Overall Total', 'text', 'Total', null, false, 50);
                $add('failed_courses', 'Courses Failed to Take as of the Present', 'textarea', 'Courses Failed to Take as of the Present', null, false, 51);
                break;

            case 'PARENT_CONSENT':
                $add('full_name', 'Student Name', 'text', 'Student Information', null, true, 1);
                $add('address', 'Student Address', 'textarea', 'Student Information', null, true, 2);
                $add('course', 'Course', 'text', 'Student Information', null, true, 3);
                $add('required_hours', 'Required Hours', 'number', 'Internship', 'numeric', true, 4);
                $add('company_name', 'Company/HTE Name', 'text', 'Internship', 'hte', true, 5);
                $add('signed_city', 'Signed at', 'text', 'Signing', null, true, 6);
                $add('signed_date', 'Date', 'date', 'Signing', null, true, 7);
                break;

            case 'ENDORSEMENT_LETTER':
                $add('endorsement_date', 'Date', 'date', 'Letter Details', null, true, 1);
                $add('company_name', 'Host Training Establishment / Company', 'text', 'Letter Details', 'hte', true, 2);
                $add('recipient_name', 'Recipient Name', 'text', 'Recipient Information', null, true, 3);
                $add('recipient_salutation', 'Recipient Salutation', 'select', 'Recipient Information', 'enum:Sir,Madam', true, 4);
                $add('recipient_office', 'Office / Department', 'text', 'Recipient Information', null, true, 5);
                $add('recipient_address', 'Office / Company Address', 'textarea', 'Recipient Information', null, true, 6);
                $add('student_title', 'Student Title', 'select', 'Student Information', 'enum:Mr.,Ms.', true, 7);
                $add('course', 'Course', 'text', 'Student Information', null, true, 8);
                $add('major', 'Major', 'text', 'Student Information', null, false, 9);
                break;

            case 'OJT_RESUME':
                $add('address', 'Address', 'textarea', 'Personal Information', null, true, 1);
                $add('contact_number', 'Telephone/Mobile Number', 'text', 'Personal Information', 'phone_ph', true, 2);
                $add('email', 'Email Address', 'email', 'Personal Information', 'email', true, 3);
                $add('career_objective', 'Career Objective', 'textarea', 'Personal Information', null, true, 4);
                for ($i = 1; $i <= 5; $i++) {
                    $add("work_experience_{$i}_date", "Work Experience {$i} – Inclusive Date", 'text', 'Work Experience', null, false, 10 + ($i * 3));
                    $add("work_experience_{$i}_employer", "Work Experience {$i} – Employer", 'text', 'Work Experience', null, false, 11 + ($i * 3));
                    $add("work_experience_{$i}_position", "Work Experience {$i} – Position", 'text', 'Work Experience', null, false, 12 + ($i * 3));
                }
                for ($i = 1; $i <= 6; $i++) {
                    $add("training_{$i}_title", "Training {$i} – Title of Seminar", 'text', 'Trainings and Seminars Attended', null, false, 30 + ($i * 2));
                    $add("training_{$i}_date", "Training {$i} – Inclusive Date", 'text', 'Trainings and Seminars Attended', null, false, 31 + ($i * 2));
                }
                foreach (['elementary', 'high_school', 'college'] as $i => $level) {
                    $label = strtoupper(str_replace('_', ' ', $level));
                    $add("education_{$level}_school", "{$label} – School", 'text', 'Educational Background', null, true, 50 + ($i * 2));
                    $add("education_{$level}_date", "{$label} – Inclusive Date", 'text', 'Educational Background', null, true, 51 + ($i * 2));
                }
                $add('extracurricular_activities', 'Extracurricular Activities and Awards Received', 'textarea', 'Extracurricular Activities and Awards Received', null, false, 60);
                foreach ([['nickname', 'Nickname'], ['height', 'Height'], ['age', 'Age', 'number', null, 'age'], ['weight', 'Weight'], ['gender', 'Gender', 'select', null, 'enum:Male,Female'], ['civil_status', 'Civil Status'], ['religion', 'Religion'], ['skills', 'Skills', 'textarea']] as $i => $f) {
                    $add($f[0], $f[1], $f[2] ?? 'text', 'Personal Background', $f[4] ?? null, true, 70 + $i);
                }
                for ($i = 1; $i <= 3; $i++) {
                    $add("reference_{$i}_name", "Reference {$i} – Name", 'text', 'References', null, false, 80 + (($i - 1) * 4));
                    $add("reference_{$i}_position", "Reference {$i} – Position", 'text', 'References', null, false, 81 + (($i - 1) * 4));
                    $add("reference_{$i}_company", "Reference {$i} – Company", 'text', 'References', null, false, 82 + (($i - 1) * 4));
                    $add("reference_{$i}_contact", "Reference {$i} – Contact Number", 'text', 'References', 'phone_ph', false, 83 + (($i - 1) * 4));
                }
                break;

            case 'INTERNSHIP_AGREEMENT':
                foreach ([
                    ['course', 'Course', 'text', true], ['major', 'Major', 'text', false], ['address', 'Student Address', 'textarea', true],
                    ['company_name', 'Host Company Name', 'text', true], ['company_address', 'Host Company Address', 'textarea', true],
                    ['company_representative', "Company Representative's Name", 'text', true], ['representative_position', "Representative's Position", 'text', true],
                    ['required_hours', 'Required Hours', 'number', true, 'numeric'], ['agreement_date', 'Agreement Date', 'date', true],
                ] as $i => $f) {
                    $add($f[0], $f[1], $f[2], 'Internship Agreement', $f[4] ?? null, $f[3], $i + 1);
                }
                break;

            case 'INTERNSHIP_RELEASE_FORM':
                $add('course', 'Course', 'text', 'Student Information', null, true, 1);
                $add('major', 'Major', 'text', 'Student Information', null, false, 2);
                $add('company_name', 'Cooperating Company/Agency/Firm', 'text', 'Internship', 'hte', true, 3);
                $add('company_address', 'Company/Agency/Firm Address', 'textarea', 'Internship', null, true, 4);
                $add('start_date', 'From', 'date', 'Internship Period', null, true, 5);
                $add('end_date', 'To', 'date', 'Internship Period', null, true, 6);
                $add('required_hours', 'Required Hours', 'number', 'Internship Period', 'numeric', true, 7);
                break;

            case 'TRAINING_LIABILITY':
                $add('student_name', 'Student Name', 'text', 'Student Information', null, true, 1);
                $add('company_name', 'Company / HTE', 'text', 'Training', 'hte', true, 2);
                $add('parent_guardian_name', 'Parent / Guardian Name', 'text', 'Consent', null, true, 3);
                $add('overtime_acknowledged', 'I understand and agree that the training involves overtime hours as indicated by the company.', 'checkbox', 'Consent', null, true, 4);
                $add('parent_guardian_consent', 'Parent/Guardian consent acknowledged', 'checkbox', 'Consent', null, true, 5);
                $add('liability_acknowledged', 'I acknowledge the training liability conditions stated in the official form.', 'checkbox', 'Consent', null, true, 6);
                $add('signed_city', 'Signed at', 'text', 'Notarization', null, true, 7);
                $add('signed_date', 'Date', 'date', 'Notarization', null, true, 8);
                break;

            case 'INTERNSHIP_TIME_FRAME':
                $add('hours', 'Number of Hours for This Date', 'number', 'Daily Time Frame', 'numeric|min:0|max:24', true, 1);
                break;

            case 'WEEKLY_REPORT':
                $add('tasks_accomplished', 'Tasks Accomplished', 'textarea', null, null, true, 1);
                $add('knowledge_skills_learned', 'Knowledge, Skills, Values Learned', 'textarea', null, null, true, 2);
                break;

            case 'STUDENT_FEEDBACK_FORM':
                $criteria = [
                    ['training_aligned', 'My training is aligned with my field of specialization.'],
                    ['training_challenging', 'My training is challenging.'],
                    ['learning_opportunities', 'I have opportunities for learning.'],
                    ['aware_of_policies', 'I am aware of the policies of the company.'],
                    ['positive_relationship', 'I have a positive working relationship with my supervisor and other employees of the company.'],
                    ['aware_of_risks', 'I am aware of the risks and hazards of my working environment.'],
                    ['department_safety', 'My department is committed to ensuring the health and safety of its student-interns.'],
                ];
                foreach ($criteria as $i => [$key, $label]) {
                    $add($key, $label, 'rating_scale', 'Evaluation Criteria', null, true, $i + 1);
                }
                $add('problems_met', 'Problems Met', 'textarea', 'Comments', null, false, 10);
                $add('other_concerns', 'Other Concerns', 'textarea', 'Comments', null, false, 11);
                break;

            case 'EVALUATION_FORM':
                $add('student_name', 'Name of Student-Intern', 'text', 'Student Information', null, true, 1);
                $add('company_name', 'Name of Company / Firm / Agency', 'text', 'Company Information', null, true, 2);
                $add('company_address', 'Company Address', 'textarea', 'Company Information', null, true, 3);
                $add('supervisor_name', 'Host Training Supervisor', 'text', 'Company Information', null, true, 4);
                $add('date_started', 'Date Started', 'date', 'Company Information', null, true, 5);
                $add('date_ended', 'Date Ended', 'date', 'Company Information', null, true, 6);
                $criteria = [
                    ['ability_to_learn', 'A. Ability to Learn (10%)'],
                    ['work_attitude', 'B. Work Attitude (15%)'],
                    ['conduct', 'C. Conduct (10%)'],
                    ['motivation_initiative', 'D. Motivation/Initiative (10%)'],
                    ['quality_accuracy_work', 'E. Quality and Accuracy of Work (20%)'],
                    ['quantity_of_work', 'F. Quantity of Work (10%)'],
                    ['safety_practices', 'G. Safety Practices (15%)'],
                    ['appearance_hygiene', 'H. Appearance/Hygiene (10%)'],
                ];
                foreach ($criteria as $i => [$key, $label]) {
                    $add("rating_{$key}", $label, 'rating_scale', 'Competency Ratings', null, true, 10 + ($i * 2));
                    $add("remarks_{$key}", 'Remarks', 'textarea', 'Competency Remarks', null, false, 11 + ($i * 2));
                }
                foreach ([
                    'termination_lack_of_work' => 'Lack of work',
                    'termination_company_rules' => 'Violation of Company Rules',
                    'termination_unfavorable_habits' => 'Unfavorable work habits/practices',
                    'termination_altercation' => 'Altercation on job',
                    'termination_absences_tardiness' => 'Too much absences/tardiness',
                    'termination_disrespectful' => 'Disrespectful to co-trainee/personnel',
                    'termination_no_interest' => 'Does not demonstrate interest/desire to learn',
                    'termination_other' => 'Other(s)',
                    'future_employment' => 'Recommended for future employment',
                    'needs_improvement' => 'Needs improvement',
                ] as $key => $label) {
                    $add($key, $label, 'checkbox', 'Disposition', null, false, 40 + count($fields));
                }
                $add('other_termination_reason', 'Other termination reason / remarks', 'textarea', 'Disposition', null, false, 60);
                $add('strengths_weaknesses', 'Strengths and Weaknesses (discuss with student-trainee)', 'textarea', 'Performance Discussion', null, false, 61);
                $add('ojt_grade', 'OJT Grade', 'number', 'Final Rating', 'numeric|min:0|max:100', true, 62);
                $add('evaluation_date', 'Evaluation Date', 'date', 'Signature', null, true, 63);
                $add('supervisor_signature_name', 'Host Training Supervisor / Designated Evaluator', 'text', 'Signature', null, true, 64);
                break;

            case 'TRAINING_SUPERVISOR_FEEDBACK':
                foreach ([
                    ['supervisor_name', 'Supervisor Name'], ['department', 'Department'], ['company_name', 'Company'], ['student_name', 'Student Intern Name'],
                ] as $i => $f) {
                    $add($f[0], $f[1], 'text', 'Information', null, true, $i + 1);
                }
                $criteria = [
                    ['punctual', 'The student-trainee is punctual in attending works and assignments.'],
                    ['sufficient_knowledge', 'The student-trainee has sufficient knowledge to contribute in the organization.'],
                    ['teamwork', 'The student-trainee knows how to work with the group.'],
                    ['training_plan_tasks', 'The student-trainee performs tasks as prescribed in the Internship Training Plan.'],
                    ['company_policies', 'The student-trainee follows and abides with the policies of the company.'],
                    ['upright_conduct', 'The student-trainee maintains an upright conduct while in the company.'],
                    ['desirable_traits', 'The student-trainee shows desirable traits, virtues, and work habits.'],
                ];
                foreach ($criteria as $i => [$key, $label]) {
                    $add($key, $label, 'rating_scale', 'Evaluation Criteria', null, true, 10 + $i);
                }
                $add('other_comments', 'Other Comments / Suggestions', 'textarea', 'Comments', null, false, 20);
                break;

            case 'PARTNER_AGENCY_EVAL_INSTRUCTOR':
            case 'PARTNER_AGENCY_EVAL_SELF':
            case 'STUDENT_HTE_EVALUATION':
                $add('rater_name', 'Name', 'text', 'Rater Information', null, true, 1);
                $add('agency_name', 'Agency / Institution', 'text', 'Rater Information', null, true, 2);
                $add('unit_division', 'Unit / Division', 'text', 'Rater Information', null, true, 3);
                $add('sex_gender', 'Sex - Gender', 'select', 'Rater Information', 'enum:Male,Female', true, 4);
                $add('rater_type', 'Type of Rater', 'select', 'Rater Information', 'enum:Subject Instructor,Self Ratee,Student', true, 5);
                $groups = [
                    'Communication' => [
                        ['comm_connectivity', 'Is there high connectivity through electronic communication of the host-training agency?'],
                        ['comm_dialogue', 'Does the management frequently accept request for dialogue and interaction as needed?'],
                        ['comm_participation', 'Is the management willing to participate in the university activities and programs when they are invited?'],
                    ],
                    'Ethical Dealings' => [
                        ['ethical_reputation', 'High reputations and stature of the industry.'],
                        ['ethical_csr', 'Established Corporate Social Responsibility of the industry.'],
                        ['ethical_support', 'Manifested support for the mandate and program of the educational institution.'],
                    ],
                    'Student Satisfaction - PSU' => [
                        ['psu_supervisor_quality', 'The assigned Internship or Practicum Supervisor are qualified and competent.'],
                        ['psu_support', 'The University provides support to various activities concerning internship.'],
                        ['psu_facilities', 'Availability of facilities for student-interns to use.'],
                    ],
                    'Student Satisfaction - Host Training Establishment' => [
                        ['hte_supervision', 'The partner-agencies provides the required supervision and conduct monitoring of trainees.'],
                        ['hte_supervisor_quality', 'The assigned Host Training Supervisors are qualified and competent to handle student-interns.'],
                        ['hte_feedback', 'Provide feedback and coaching or monitoring activities to further improve performance of student-interns.'],
                    ],
                    'Quality Delivery' => [
                        ['quality_timeliness', 'Timeliness are strictly observed.'],
                        ['quality_objectives', 'Objectives specified in Internship Plan are met.'],
                        ['quality_resources', 'Adequate resources needed for the Internship purpose are made accessible and provided.'],
                    ],
                ];
                $order = 10;
                foreach ($groups as $group => $criteria) {
                    foreach ($criteria as [$key, $label]) {
                        $add($key, $label, 'rating_scale', $group, null, true, $order++);
                    }
                }
                break;
        }

        return $fields;
    }
}
