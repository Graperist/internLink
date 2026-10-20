<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\DocumentRequirement;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Roles ---
        $roles = collect([
            ['code' => 'coordinator', 'name' => 'OJT Coordinator'],
            ['code' => 'instructor', 'name' => 'Subject Instructor'],
            ['code' => 'hte_representative', 'name' => 'HTE Representative'],
            ['code' => 'student', 'name' => 'Student Intern'],
        ])->map(fn ($r) => Role::firstOrCreate(['code' => $r['code']], $r));

        // --- Campuses ---
        $campusData = [
            ['code' => 'ACC', 'name' => 'Pangasinan State University - Alaminos City Campus', 'address' => 'Alaminos City, Pangasinan'],
            ['code' => 'BC', 'name' => 'Pangasinan State University - Bayambang Campus', 'address' => 'Bayambang, Pangasinan'],
            ['code' => 'LC', 'name' => 'Pangasinan State University - Lingayen Campus', 'address' => 'Lingayen, Pangasinan'],
            ['code' => 'UC', 'name' => 'Pangasinan State University - Urdaneta City Campus', 'address' => 'Urdaneta City, Pangasinan'],
        ];

        $campuses = collect($campusData)->map(fn ($c) => Campus::firstOrCreate(
            ['code' => $c['code']],
            ['name' => $c['name'], 'address' => $c['address'], 'status' => 'active']
        ));

        $pilotCampus = $campuses->firstWhere('code', 'ACC');

        // --- Programs (per your Scope and Delimitations) ---
        // Seeded for every campus so each campus can independently offer the same five programs.
        $programs = [
            ['code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology'],
            ['code' => 'BSBA-OM', 'name' => 'Bachelor of Science in Business Administration - Operations Management'],
            ['code' => 'BSBA-FM', 'name' => 'Bachelor of Science in Business Administration - Financial Management'],
            ['code' => 'BSHM', 'name' => 'Bachelor of Science in Hospitality Management'],
            ['code' => 'BSTM', 'name' => 'Bachelor of Science in Tourism Management'],
        ];

        foreach ($campuses as $campus) {
            foreach ($programs as $program) {
                Program::firstOrCreate(
                    ['campus_id' => $campus->id, 'code' => $program['code']],
                    ['name' => $program['name'], 'required_hours' => null]
                );
            }
        }

        // --- Document requirements (pre/active/post-deployment) ---
        // Placeholder set - your paper cites "no fewer than twelve pre-OJT forms";
        // update/expand this list to match your actual PSU-ACC checklist.
        // Seeded per campus since document_requirements.campus_id is nullable-but-scoped;
        // adjust here if requirements turn out to be identical university-wide.
        $requirements = [
            ['code' => 'APPLICATION_FOR_OJT', 'name' => 'Application for On the Job Training', 'stage' => 'pre_deployment'],
            ['code' => 'CERTIFICATE_OF_UNITS_EARNED', 'name' => 'Certificate of Units Earned for Practicum', 'stage' => 'pre_deployment'],
            ['code' => 'ENDORSEMENT_LETTER', 'name' => 'Endorsement Letter', 'stage' => 'pre_deployment'],
            ['code' => 'MOA', 'name' => 'Memorandum of Agreement', 'stage' => 'pre_deployment'],
            ['code' => 'TRAINING_LIABILITY', 'name' => 'Training Liability Form', 'stage' => 'pre_deployment'],
            ['code' => 'MEDICAL_CERTIFICATE', 'name' => 'Medical Certificate', 'stage' => 'pre_deployment'],
            ['code' => 'PARENT_CONSENT', 'name' => 'Consent and Waiver', 'stage' => 'pre_deployment'],
            ['code' => 'OJT_RESUME', 'name' => 'OJT Resume', 'stage' => 'pre_deployment'],
            ['code' => 'INTERNSHIP_AGREEMENT', 'name' => 'Internship Agreement', 'stage' => 'pre_deployment'],
            ['code' => 'INTERNSHIP_ACADEMIC_PRACTICUM_COURSE', 'name' => 'Internship-Academic Practicum Course', 'stage' => 'pre_deployment'],
            ['code' => 'INTERNSHIP_TIME_FRAME', 'name' => 'Internship Time Frame', 'stage' => 'active', 'is_repeating' => true, 'entry_cadence' => 'daily'],
            ['code' => 'DAILY_JOURNAL', 'name' => 'Daily Journal', 'stage' => 'active', 'is_repeating' => true, 'entry_cadence' => 'daily'],
            ['code' => 'WEEKLY_REPORT', 'name' => 'Form 19 – Practicum/Internship Weekly Report', 'stage' => 'active', 'is_repeating' => true, 'entry_cadence' => 'daily'],
            ['code' => 'NARRATIVE_REPORT', 'name' => 'Narrative Report', 'stage' => 'post_deployment'],
            ['code' => 'EVALUATION_FORM', 'name' => 'HTE Evaluation Form', 'stage' => 'post_deployment'],
            ['code' => 'STUDENT_FEEDBACK_FORM', 'name' => "Student-Intern's Feedback Form", 'stage' => 'post_deployment'],
            ['code' => 'STUDENT_HTE_EVALUATION', 'name' => 'Evaluation Instrument of PSU Partner Agencies (Student)', 'stage' => 'post_deployment'],
            ['code' => 'CERTIFICATE_OF_COMPLETION', 'name' => 'Certificate of Completion', 'stage' => 'post_deployment'],
        ];

        foreach ($campuses as $campus) {
            foreach ($requirements as $req) {
                DocumentRequirement::firstOrCreate(
                    ['campus_id' => $campus->id, 'code' => $req['code']],
                    [
                        'name' => $req['name'],
                        'stage' => $req['stage'],
                        'is_required' => true,
                        'is_repeating' => $req['is_repeating'] ?? false,
                        'entry_cadence' => $req['entry_cadence'] ?? null,
                    ]
                );
            }
        }
        $structuredForms = [
            'APPLICATION_FOR_OJT' => [
                // Personal Profile - matches the real form's exact sections;
                // Academic Credentials (units passed/failed) is deliberately
                // excluded, since that section is certified by the Registrar,
                // not filled by the student.
                ['field_key' => 'surname', 'label' => 'Surname', 'field_type' => 'text', 'group_label' => 'Personal Profile', 'sort_order' => 1],
                ['field_key' => 'given_name', 'label' => 'Given Name', 'field_type' => 'text', 'group_label' => 'Personal Profile', 'sort_order' => 2],
                ['field_key' => 'middle_name', 'label' => 'Middle Name', 'field_type' => 'text', 'group_label' => 'Personal Profile', 'sort_order' => 3, 'is_required' => false],
                ['field_key' => 'age', 'label' => 'Age', 'field_type' => 'text', 'validation_rule' => 'age', 'group_label' => 'Personal Profile', 'sort_order' => 4],
                ['field_key' => 'civil_status', 'label' => 'Civil Status', 'field_type' => 'text', 'validation_rule' => 'enum:Single,Married,Widowed,Separated,Divorced,Annulled', 'group_label' => 'Personal Profile', 'sort_order' => 5],
                ['field_key' => 'sex', 'label' => 'Sex', 'field_type' => 'text', 'validation_rule' => 'enum:Male,Female', 'group_label' => 'Personal Profile', 'sort_order' => 6],
                ['field_key' => 'contact_number', 'label' => 'Contact Number', 'field_type' => 'text', 'validation_rule' => 'phone_ph', 'group_label' => 'Personal Profile', 'sort_order' => 7],
                ['field_key' => 'course', 'label' => 'Course', 'field_type' => 'text', 'group_label' => 'Personal Profile', 'sort_order' => 8],
                ['field_key' => 'year_section', 'label' => 'Year & Section', 'field_type' => 'text', 'group_label' => 'Personal Profile', 'sort_order' => 9],
                ['field_key' => 'major', 'label' => 'Major', 'field_type' => 'text', 'group_label' => 'Personal Profile', 'sort_order' => 10, 'is_required' => false],
                ['field_key' => 'parent_guardian_name', 'label' => 'Parent/Guardian', 'field_type' => 'text', 'group_label' => 'Personal Profile', 'sort_order' => 11],
                ['field_key' => 'current_address', 'label' => 'Current Address', 'field_type' => 'textarea', 'group_label' => 'Personal Profile', 'sort_order' => 12],
                ['field_key' => 'home_address', 'label' => 'Home Address', 'field_type' => 'textarea', 'group_label' => 'Personal Profile', 'sort_order' => 13],

                // Firm/Agency/Company for OJT
                ['field_key' => 'hte_name', 'label' => 'Name of the Firm/Agency/Company', 'field_type' => 'text', 'group_label' => 'Firm/Agency/Company for OJT', 'sort_order' => 20],
                ['field_key' => 'hte_address', 'label' => 'Address', 'field_type' => 'textarea', 'group_label' => 'Firm/Agency/Company for OJT', 'sort_order' => 21],
                ['field_key' => 'hte_head', 'label' => 'Head', 'field_type' => 'text', 'group_label' => 'Firm/Agency/Company for OJT', 'sort_order' => 22, 'is_required' => false],
                ['field_key' => 'hte_contact_number', 'label' => 'Contact Number', 'field_type' => 'text', 'validation_rule' => 'phone_ph', 'group_label' => 'Firm/Agency/Company for OJT', 'sort_order' => 23, 'is_required' => false],
                ['field_key' => 'hte_number_of_hours', 'label' => 'Number of Hours', 'field_type' => 'text', 'validation_rule' => 'numeric', 'group_label' => 'Firm/Agency/Company for OJT', 'sort_order' => 24],
            ],
            'CERTIFICATE_OF_UNITS_EARNED' => [
                ['field_key' => 'surname', 'label' => 'Last Name', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 1],
                ['field_key' => 'given_name', 'label' => 'First Name', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 2],
                ['field_key' => 'middle_name', 'label' => 'Middle Name', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 3, 'is_required' => false],
                ['field_key' => 'course', 'label' => 'Course', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 4],
                ['field_key' => 'major', 'label' => 'Major', 'field_type' => 'text', 'group_label' => 'Student Information', 'sort_order' => 5, 'is_required' => false],
                ['field_key' => 'first_year_courses', 'label' => 'First Year Courses Earned', 'field_type' => 'textarea', 'group_label' => 'Courses Earned', 'sort_order' => 10],
                ['field_key' => 'second_year_courses', 'label' => 'Second Year Courses Earned', 'field_type' => 'textarea', 'group_label' => 'Courses Earned', 'sort_order' => 11],
                ['field_key' => 'third_year_courses', 'label' => 'Third Year Courses Earned', 'field_type' => 'textarea', 'group_label' => 'Courses Earned', 'sort_order' => 12],
                ['field_key' => 'fourth_year_courses', 'label' => 'Fourth Year Courses Earned', 'field_type' => 'textarea', 'group_label' => 'Courses Earned', 'sort_order' => 13],
                ['field_key' => 'failed_courses', 'label' => 'Courses Failed to Take as of the Present', 'field_type' => 'textarea', 'group_label' => 'Courses Failed to Take as of the Present', 'sort_order' => 20, 'is_required' => false],
            ],
            'PARENT_CONSENT' => [
                ['field_key' => 'full_name', 'label' => 'Full Name', 'field_type' => 'text', 'sort_order' => 1],
                ['field_key' => 'address', 'label' => 'Address', 'field_type' => 'textarea', 'sort_order' => 2],
                ['field_key' => 'course', 'label' => 'Course', 'field_type' => 'text', 'sort_order' => 3],
                ['field_key' => 'required_hours', 'label' => 'Required Hours', 'field_type' => 'text', 'validation_rule' => 'numeric', 'sort_order' => 4],
                ['field_key' => 'company_name', 'label' => 'Company/HTE Name', 'field_type' => 'text', 'sort_order' => 5],
            ],
            'OJT_RESUME' => [
                ['field_key' => 'address', 'label' => 'Address', 'field_type' => 'textarea', 'sort_order' => 1],
                ['field_key' => 'contact_number', 'label' => 'Telephone/Mobile Number', 'field_type' => 'text', 'validation_rule' => 'phone_ph', 'sort_order' => 2],
                ['field_key' => 'email', 'label' => 'Email Address', 'field_type' => 'text', 'validation_rule' => 'email', 'sort_order' => 3],
                ['field_key' => 'career_objective', 'label' => 'Career Objective', 'field_type' => 'textarea', 'sort_order' => 4],
                // Simplified to one free-text block each, rather than the
                // real form's repeating table rows (inclusive date /
                // employer / position, etc.) - true repeating entries are
                // part of the same follow-up as the Daily Journal-style forms.
                ['field_key' => 'work_experience', 'label' => 'Work Experience', 'field_type' => 'textarea', 'sort_order' => 5],
                ['field_key' => 'trainings_seminars', 'label' => 'Trainings and Seminars Attended', 'field_type' => 'textarea', 'sort_order' => 6],
                ['field_key' => 'educational_background', 'label' => 'Educational Background', 'field_type' => 'textarea', 'sort_order' => 7],
                ['field_key' => 'extracurricular_activities', 'label' => 'Extracurricular Activities and Awards Received', 'field_type' => 'textarea', 'sort_order' => 8, 'is_required' => false],
                ['field_key' => 'skills', 'label' => 'Skills', 'field_type' => 'textarea', 'sort_order' => 9],
                ['field_key' => 'references', 'label' => 'References', 'field_type' => 'textarea', 'sort_order' => 10],
            ],
            'INTERNSHIP_AGREEMENT' => [
                ['field_key' => 'course', 'label' => 'Course', 'field_type' => 'text', 'sort_order' => 1],
                ['field_key' => 'major', 'label' => 'Major', 'field_type' => 'text', 'sort_order' => 2],
                ['field_key' => 'address', 'label' => 'Student Address', 'field_type' => 'textarea', 'sort_order' => 3],
                ['field_key' => 'company_name', 'label' => 'Host Company Name', 'field_type' => 'text', 'sort_order' => 4],
                ['field_key' => 'company_address', 'label' => 'Host Company Address', 'field_type' => 'textarea', 'sort_order' => 5],
                ['field_key' => 'company_representative', 'label' => "Company Representative's Name", 'field_type' => 'text', 'sort_order' => 6],
                ['field_key' => 'representative_position', 'label' => "Representative's Position", 'field_type' => 'text', 'sort_order' => 7],
                ['field_key' => 'required_hours', 'label' => 'Required Hours', 'field_type' => 'text', 'validation_rule' => 'numeric', 'sort_order' => 8],
            ],
            'INTERNSHIP_ACADEMIC_PRACTICUM_COURSE' => [
                ['field_key' => 'course', 'label' => 'Course', 'field_type' => 'text', 'sort_order' => 1],
                ['field_key' => 'major', 'label' => 'Major', 'field_type' => 'text', 'sort_order' => 2, 'is_required' => false],
                ['field_key' => 'company_name', 'label' => 'Company Name', 'field_type' => 'text', 'sort_order' => 3],
                ['field_key' => 'company_address', 'label' => 'Company Address', 'field_type' => 'textarea', 'sort_order' => 4],
                ['field_key' => 'supervisor', 'label' => 'Supervisor', 'field_type' => 'text', 'sort_order' => 5],
                ['field_key' => 'department', 'label' => 'Department', 'field_type' => 'text', 'sort_order' => 6],
                ['field_key' => 'workdays', 'label' => 'Workdays', 'field_type' => 'text', 'sort_order' => 7],
                ['field_key' => 'required_hours', 'label' => 'Required Hours', 'field_type' => 'text', 'validation_rule' => 'numeric', 'sort_order' => 8],
            ],

            // --- Repeating forms: one field set per log entry (per day or per week) ---
            'INTERNSHIP_TIME_FRAME' => [
                ['field_key' => 'hours', 'label' => 'Number of Hours', 'field_type' => 'text', 'sort_order' => 1],
            ],
            'DAILY_JOURNAL' => [
                ['field_key' => 'hours', 'label' => 'Number of Hours', 'field_type' => 'text', 'sort_order' => 0],
                ['field_key' => 'objectives', 'label' => 'Objectives (identify objectives for the day)', 'field_type' => 'textarea', 'sort_order' => 1],
                ['field_key' => 'reflection', 'label' => 'Reflection From the Objectives', 'field_type' => 'textarea', 'sort_order' => 2],
                ['field_key' => 'accomplishment', 'label' => 'Accomplishment for the Day', 'field_type' => 'textarea', 'sort_order' => 3],
                ['field_key' => 'realization', 'label' => 'Realization About the Task/s', 'field_type' => 'textarea', 'sort_order' => 4],
            ],
            'WEEKLY_REPORT' => [
                ['field_key' => 'tasks_accomplished', 'label' => 'Tasks Accomplished', 'field_type' => 'textarea', 'sort_order' => 1],
                ['field_key' => 'knowledge_skills_learned', 'label' => 'Knowledge, Skills, Values Learned', 'field_type' => 'textarea', 'sort_order' => 2],
            ],

            // --- Rating-scale forms: flat, one-time, filled post-deployment ---
            'STUDENT_FEEDBACK_FORM' => [
                ['field_key' => 'training_aligned', 'label' => 'My training is aligned with my field of specialization.', 'field_type' => 'rating_scale', 'sort_order' => 1],
                ['field_key' => 'training_challenging', 'label' => 'My training is challenging.', 'field_type' => 'rating_scale', 'sort_order' => 2],
                ['field_key' => 'learning_opportunities', 'label' => 'I have opportunities for learning.', 'field_type' => 'rating_scale', 'sort_order' => 3],
                ['field_key' => 'aware_of_policies', 'label' => 'I am aware of the policies of the company.', 'field_type' => 'rating_scale', 'sort_order' => 4],
                ['field_key' => 'positive_relationship', 'label' => 'I have a positive working relationship with my supervisor and other employees of the company.', 'field_type' => 'rating_scale', 'sort_order' => 5],
                ['field_key' => 'aware_of_risks', 'label' => 'I am aware of the risks and hazards of my working environment.', 'field_type' => 'rating_scale', 'sort_order' => 6],
                ['field_key' => 'department_safety', 'label' => 'My department is committed to ensuring the health and safety of its student-interns.', 'field_type' => 'rating_scale', 'sort_order' => 7],
                ['field_key' => 'problems_met', 'label' => 'Problems Met', 'field_type' => 'textarea', 'sort_order' => 8],
                ['field_key' => 'other_concerns', 'label' => 'Other Concerns', 'field_type' => 'textarea', 'sort_order' => 9],
            ],
            'STUDENT_HTE_EVALUATION' => [
                ['field_key' => 'agency_name', 'label' => 'Agency/Institution', 'field_type' => 'text', 'sort_order' => 1],
                ['field_key' => 'unit_division', 'label' => 'Unit/Division', 'field_type' => 'text', 'sort_order' => 2],

                ['field_key' => 'comm_connectivity', 'group_label' => 'Communication', 'label' => 'Is there high connectivity through electronic communication of the host-training agency?', 'field_type' => 'rating_scale', 'sort_order' => 10],
                ['field_key' => 'comm_dialogue', 'group_label' => 'Communication', 'label' => 'Does the management frequently accept requests for dialogue and interaction as needed?', 'field_type' => 'rating_scale', 'sort_order' => 11],
                ['field_key' => 'comm_participation', 'group_label' => 'Communication', 'label' => 'Is the management willing to participate in university activities and programs when invited?', 'field_type' => 'rating_scale', 'sort_order' => 12],

                ['field_key' => 'ethical_reputation', 'group_label' => 'Ethical Dealings', 'label' => 'High reputation and stature of the industry.', 'field_type' => 'rating_scale', 'sort_order' => 20],
                ['field_key' => 'ethical_csr', 'group_label' => 'Ethical Dealings', 'label' => 'Established Corporate Social Responsibility of the industry.', 'field_type' => 'rating_scale', 'sort_order' => 21],
                ['field_key' => 'ethical_support', 'group_label' => 'Ethical Dealings', 'label' => 'Manifested support for the mandate and program of the educational institution.', 'field_type' => 'rating_scale', 'sort_order' => 22],

                ['field_key' => 'psu_supervisor_quality', 'group_label' => 'Student Satisfaction - PSU', 'label' => 'The assigned Internship or Practicum Supervisor is qualified and competent.', 'field_type' => 'rating_scale', 'sort_order' => 30],
                ['field_key' => 'psu_support', 'group_label' => 'Student Satisfaction - PSU', 'label' => 'The University provides support to various activities concerning internship.', 'field_type' => 'rating_scale', 'sort_order' => 31],
                ['field_key' => 'psu_facilities', 'group_label' => 'Student Satisfaction - PSU', 'label' => 'Availability of facilities for student-interns to use.', 'field_type' => 'rating_scale', 'sort_order' => 32],

                ['field_key' => 'hte_supervision', 'group_label' => 'Student Satisfaction - Host Training Establishment', 'label' => 'The partner agency provides the required supervision and conducts monitoring of trainees.', 'field_type' => 'rating_scale', 'sort_order' => 40],
                ['field_key' => 'hte_supervisor_quality', 'group_label' => 'Student Satisfaction - Host Training Establishment', 'label' => 'The assigned Host Training Supervisors are qualified and competent to handle student-interns.', 'field_type' => 'rating_scale', 'sort_order' => 41],
                ['field_key' => 'hte_feedback', 'group_label' => 'Student Satisfaction - Host Training Establishment', 'label' => 'Provides feedback, coaching, or monitoring activities to further improve performance of student-interns.', 'field_type' => 'rating_scale', 'sort_order' => 42],

                ['field_key' => 'quality_timeliness', 'group_label' => 'Quality Delivery', 'label' => 'Timeliness is strictly observed.', 'field_type' => 'rating_scale', 'sort_order' => 50],
                ['field_key' => 'quality_objectives', 'group_label' => 'Quality Delivery', 'label' => 'Objectives specified in the Internship Plan are met.', 'field_type' => 'rating_scale', 'sort_order' => 51],
                ['field_key' => 'quality_resources', 'group_label' => 'Quality Delivery', 'label' => 'Adequate resources needed for the internship purpose are made accessible and provided.', 'field_type' => 'rating_scale', 'sort_order' => 52],
            ],
        ];

        foreach ($campuses as $campus) {
            foreach ($structuredForms as $code => $fields) {
                $requirement = DocumentRequirement::where('campus_id', $campus->id)
                    ->where('code', $code)
                    ->first();

                if (! $requirement) {
                    continue;
                }

                foreach ($fields as $field) {
                    \App\Models\DocumentFieldDefinition::firstOrCreate(
                        ['document_requirement_id' => $requirement->id, 'field_key' => $field['field_key']],
                        [
                            'label' => $field['label'],
                            'group_label' => $field['group_label'] ?? null,
                            'field_type' => $field['field_type'],
                            'validation_rule' => $field['validation_rule'] ?? null,
                            'is_required' => $field['is_required'] ?? true,
                            'sort_order' => $field['sort_order'],
                        ]
                    );
                }
            }
        }

        // --- Partner HTEs (pilot campus only, matching your prototype's sample data) ---
        $hteData = [
            ['name' => 'Alaminos City LGU - ICT Office', 'address' => 'Alaminos City, Pangasinan', 'moa_status' => 'active'],
            ['name' => 'BDO Unibank - Alaminos Branch', 'address' => 'Alaminos City, Pangasinan', 'moa_status' => 'active'],
            ['name' => 'Hundred Islands National Park', 'address' => 'Alaminos City, Pangasinan', 'moa_status' => 'active'],
            ['name' => 'Land Bank of the Philippines - Alaminos', 'address' => 'Alaminos City, Pangasinan', 'moa_status' => 'active'],
            ['name' => 'PLDT - Alaminos Branch', 'address' => 'Alaminos City, Pangasinan', 'moa_status' => 'active'],
            ['name' => 'Bolinao Tourism Office', 'address' => 'Bolinao, Pangasinan', 'moa_status' => 'pending'],
        ];

        foreach ($hteData as $hte) {
            \App\Models\Hte::firstOrCreate(
                ['campus_id' => $pilotCampus->id, 'name' => $hte['name']],
                ['address' => $hte['address'], 'moa_status' => $hte['moa_status']]
            );
        }

        // --- Test coordinator accounts (one per campus) ---
        // Each campus needs at least one coordinator seeded in directly, since
        // there is no self-registration - the coordinator is the account that
        // bootstraps everyone else (instructors, HTE reps, students) for their campus.
        $coordinatorRole = $roles->firstWhere('code', 'coordinator');

        $coordinatorData = [
            'ACC' => ['id_number' => 'ACC-COORD-001', 'name' => 'Test OJT Coordinator - ACC', 'email' => 'coordinator.acc@internlink.test'],
            'BC'  => ['id_number' => 'BC-COORD-001',  'name' => 'Test OJT Coordinator - BC',  'email' => 'coordinator.bc@internlink.test'],
            'LC'  => ['id_number' => 'LC-COORD-001',  'name' => 'Test OJT Coordinator - LC',  'email' => 'coordinator.lc@internlink.test'],
            'UC'  => ['id_number' => 'UC-COORD-001',  'name' => 'Test OJT Coordinator - UC',  'email' => 'coordinator.uc@internlink.test'],
        ];

        foreach ($campuses as $campus) {
            $data = $coordinatorData[$campus->code] ?? null;

            if (! $data) {
                continue;
            }

            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'campus_id' => $campus->id,
                    'role_id' => $coordinatorRole->id,
                    'id_number' => $data['id_number'],
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                ]
            );
        }

        // --- Test instructor account (pilot campus only) ---
        // Needed to log into and test the Instructor Portal - no self-registration
        // exists, and there's no UI yet for a coordinator to provision instructors.
        $instructorRole = $roles->firstWhere('code', 'instructor');

        $instructorUser = User::firstOrCreate(
            ['email' => 'instructor.test@internlink.test'],
            [
                'campus_id' => $pilotCampus->id,
                'role_id' => $instructorRole->id,
                'id_number' => 'ACC-INST-001',
                'name' => 'Test Subject Instructor',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        $testInstructor = \App\Models\Instructor::firstOrCreate(
            ['user_id' => $instructorUser->id],
            ['campus_id' => $pilotCampus->id]
        );

        // --- Assign the seeded test student(s) to the test instructor ---
        // Lets you log into the Instructor Portal and immediately see an
        // advisee, instead of an empty "My Advisees" list. Only touches
        // students who don't already have an instructor assigned, so this
        // won't clobber a real assignment made through the app.
        Student::whereNull('instructor_id')
            ->where('campus_id', $pilotCampus->id)
            ->update(['instructor_id' => $testInstructor->id]);
    }
}
