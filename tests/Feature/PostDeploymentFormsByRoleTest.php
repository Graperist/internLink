<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\HteRepresentative;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\InternshipEvaluationForm;
use Database\Seeders\OfficialOjtFormsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The post-deployment forms (checklist "After training") and who completes them:
 *   Student    -> Form 17 Time Frame, Form 19 Weekly Report, Form 20 Feedback, Form 23c, Narrative Report
 *   HTE        -> Form 13 Evaluation, Form 21 Supervisor Feedback, Form 23b Self Ratee
 *   Instructor -> Form 23a
 */
class PostDeploymentFormsByRoleTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private Hte $hte;
    private User $studentUser;
    private User $hteUser;
    private User $instructorUser;
    private Internship $internship;
    private Deployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();

        $roles = [];
        foreach (['student', 'hte_representative', 'instructor', 'coordinator'] as $code) {
            $roles[$code] = Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }
        $this->campus = Campus::forceCreate(['code' => 'AAA', 'name' => 'Pangasinan State University - Alaminos City', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'BS Information Technology', 'required_hours' => 486]);
        $this->hte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Fareheaven Boarding House', 'address' => 'Alaminos City']);

        $make = fn (string $role, string $id) => User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $roles[$role]->id, 'id_number' => $id, 'name' => "$role $id",
            'email' => "$id@t.local", 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);

        $this->studentUser = $make('student', 'S-1');
        $student = Student::forceCreate(['user_id' => $this->studentUser->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id]);
        $this->hteUser = $make('hte_representative', 'H-1');
        HteRepresentative::forceCreate(['user_id' => $this->hteUser->id, 'hte_id' => $this->hte->id]);
        $this->instructorUser = $make('instructor', 'I-1');
        Instructor::forceCreate(['user_id' => $this->instructorUser->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id]);

        $this->internship = Internship::forceCreate([
            'student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id,
            'status' => 'completed', 'required_hours' => 486,
        ]);
        $this->deployment = Deployment::forceCreate([
            'internship_id' => $this->internship->id, 'hte_id' => $this->hte->id,
            'status' => 'completed', 'start_date' => '2026-09-01', 'end_date' => '2026-10-03',
        ]);

        $this->seed(OfficialOjtFormsSeeder::class);
    }

    private function req(string $code): DocumentRequirement
    {
        return DocumentRequirement::where('campus_id', $this->campus->id)->where('code', $code)->firstOrFail();
    }

    /** A valid submission for every field of a structured form. */
    private function payload(DocumentRequirement $requirement, int $rating = 5): array
    {
        $payload = [];
        foreach ($requirement->fieldDefinitions as $field) {
            $payload[$field->field_key] = match ($field->field_type) {
                'rating_scale' => $rating,
                'number' => 5,
                'date' => '2026-10-03',
                'email' => 'a@b.co',
                'checkbox' => '0',
                'select' => str_starts_with((string) $field->validation_rule, 'enum:')
                    ? trim(explode(',', substr($field->validation_rule, 5))[0]) : 'x',
                default => 'Sample text',
            };
        }

        return $payload;
    }

    // ------------------------------------------------------------------ student

    public function test_student_only_sees_their_own_post_deployment_forms(): void
    {
        $page = $this->actingAs($this->studentUser)->get('/student/documents')->assertOk();

        // their own forms are listed, and each is actionable when opened
        // (the page shows one form at a time, like Document Review)
        // (Forms 20 / 23c are on My HTE, not My Documents.)
        foreach (['WEEKLY_REPORT' => 'log'] as $code => $action) {
            $page->assertSee(route('student.documents', ['form' => $this->req($code)->id]), false);
            $this->actingAs($this->studentUser)->get(route('student.documents', ['form' => $this->req($code)->id]))
                ->assertSee(route('student.documents.'.$action, $this->req($code)), false);
        }
        $page->assertSee('Narrative Report');
        $this->actingAs($this->studentUser)->get(route('student.hte'))->assertOk()
            ->assertSee(route('student.documents.fill', $this->req('STUDENT_FEEDBACK_FORM')), false)
            ->assertSee(route('student.documents.fill', $this->req('STUDENT_HTE_EVALUATION')), false);

        // the HTE's and the instructor's forms are not on the student's list at all
        foreach (['Form 13 ', 'Form 21 ', 'Form 23a', 'Form 23b'] as $label) {
            $page->assertDontSee($label);
        }
    }

    public function test_student_cannot_open_or_submit_forms_that_belong_to_the_hte_or_instructor(): void
    {
        foreach (['EVALUATION_FORM', 'TRAINING_SUPERVISOR_FEEDBACK', 'PARTNER_AGENCY_EVAL_SELF', 'PARTNER_AGENCY_EVAL_INSTRUCTOR'] as $code) {
            $requirement = $this->req($code);

            $this->actingAs($this->studentUser)->get(route('student.documents.fill', $requirement))->assertForbidden();
            $this->actingAs($this->studentUser)->post(route('student.documents.fill.store', $requirement), $this->payload($requirement))->assertForbidden();
        }

        $this->assertSame(0, Document::count(), 'no document may be created by the student for another role\'s form');
    }

    public function test_student_can_submit_form_20_and_form_23c(): void
    {
        foreach (['STUDENT_FEEDBACK_FORM', 'STUDENT_HTE_EVALUATION'] as $code) {
            $requirement = $this->req($code);

            $this->actingAs($this->studentUser)->get(route('student.documents.fill', $requirement))->assertOk();
            $this->actingAs($this->studentUser)->post(route('student.documents.fill.store', $requirement), $this->payload($requirement))
                ->assertRedirect(route('student.hte').'#evaluate')->assertSessionHasNoErrors();

            $this->assertSame('submitted', Document::where('document_requirement_id', $requirement->id)->firstOrFail()->status, $code);
        }
    }

    public function test_student_can_log_and_submit_form_17_and_it_prints_in_the_official_layout(): void
    {
        $requirement = $this->req('INTERNSHIP_TIME_FRAME');

        foreach ([['2026-09-01', '8'], ['2026-09-02', '8'], ['2026-09-03', '4.5']] as [$date, $hours]) {
            $this->actingAs($this->studentUser)->post(route('student.documents.log.entry.store', $requirement), ['entry_date' => $date, 'hours' => $hours])
                ->assertSessionHasNoErrors();
        }
        $this->actingAs($this->studentUser)->post(route('student.documents.log.submit', $requirement))->assertSessionHasNoErrors();

        $document = Document::where('document_requirement_id', $requirement->id)->firstOrFail();
        $this->assertSame('submitted', $document->status);

        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user');
        $html = view('instructor.documents.printable-internship-time-frame', ['document' => $document])->render();

        // ...and the instructor and coordinator routes pick that same template.
        $coordinator = User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => Role::where('code', 'coordinator')->value('id'), 'id_number' => 'C-1',
            'name' => 'coordinator', 'email' => 'c@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $this->actingAs($this->instructorUser)->get(route('instructor.documents.printable', $document))->assertOk()->assertSee('FM-AA-INT-17');
        $this->actingAs($coordinator)->get(route('coordinator.documents.printable', $document))->assertOk()->assertSee('FM-AA-INT-17');

        $this->assertStringContainsString('FM-AA-INT-17', $html);
        $this->assertStringContainsString('INTERNSHIP TIME FRAME', $html);
        $this->assertStringContainsString('Fareheaven Boarding House', $html);
        $this->assertStringContainsString('Tuesday', $html);              // Sep 1, 2026 is a Tuesday
        $this->assertStringContainsString('20.5', $html);                 // 8 + 8 + 4.5
        $this->assertStringContainsString("Company's Authorized Representative", $html);
    }

    // ---------------------------------------------------------------------- HTE

    public function test_hte_gets_forms_13_21_and_23b_but_not_the_students(): void
    {
        $page = $this->actingAs($this->hteUser)->get('/hte/evaluations')->assertOk();
        foreach (['EVALUATION_FORM', 'TRAINING_SUPERVISOR_FEEDBACK'] as $code) {
            $page->assertSee(route('hte.forms.create', [$this->req($code), $this->deployment]), false);
        }
        $page->assertSee(route('hte.self-evaluation.create'), false);   // Form 23b: once for the agency

        foreach (['STUDENT_FEEDBACK_FORM', 'STUDENT_HTE_EVALUATION', 'PARTNER_AGENCY_EVAL_INSTRUCTOR', 'PARTNER_AGENCY_EVAL_SELF'] as $code) {
            $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$this->req($code), $this->deployment]))->assertNotFound();
        }
    }

    public function test_hte_sees_the_official_form_13_descriptors_and_likert_wording_for_form_21(): void
    {
        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$this->req('EVALUATION_FORM'), $this->deployment]))
            ->assertOk()
            ->assertSee('Curiosity goes beyond immediate job procedures; eager to learn more; works hard.')   // D / 5
            ->assertSee('Generally not presentable: clothes dirty and not appropriate for the job')           // H / 1
            ->assertSee('OJT GRADE');

        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$this->req('TRAINING_SUPERVISOR_FEEDBACK'), $this->deployment]))
            ->assertOk()->assertSee('Strongly Agree')->assertSee('Strongly Disagree');
    }

    public function test_hte_submits_forms_13_21_and_23b(): void
    {
        foreach (['EVALUATION_FORM', 'TRAINING_SUPERVISOR_FEEDBACK', 'PARTNER_AGENCY_EVAL_SELF'] as $code) {
            $requirement = $this->req($code);
            $payload = $this->payload($requirement);
            if ($code === 'PARTNER_AGENCY_EVAL_SELF') {
                $payload['rater_type'] = 'Self Ratee';
            }

            $url = $code === 'PARTNER_AGENCY_EVAL_SELF' ? route('hte.self-evaluation.store') : route('hte.forms.store', [$requirement, $this->deployment]);
            $this->actingAs($this->hteUser)->post($url, $payload)->assertRedirect(route('hte.evaluations'))->assertSessionHasNoErrors();
            $this->assertSame('submitted', Document::where('document_requirement_id', $requirement->id)->firstOrFail()->status, $code);
        }
    }

    public function test_hte_cannot_use_a_deployment_that_is_still_running(): void
    {
        $this->deployment->update(['status' => 'active']);

        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$this->req('EVALUATION_FORM'), $this->deployment]))->assertStatus(422);
    }

    // --------------------------------------------------------------- instructor

    public function test_instructor_gets_form_23a_only(): void
    {
        $this->actingAs($this->instructorUser)->get('/instructor/evaluations')->assertOk()
            ->assertSee(route('instructor.agency-evaluations.create', $this->deployment->hte_id), false);

        $requirement = $this->req('PARTNER_AGENCY_EVAL_INSTRUCTOR');
        $this->actingAs($this->instructorUser)->post(route('instructor.agency-evaluations.store', $this->deployment->hte_id), $this->payload($requirement))
            ->assertRedirect(route('instructor.evaluations'))->assertSessionHasNoErrors();
        $this->assertSame('submitted', Document::where('document_requirement_id', $requirement->id)->firstOrFail()->status);
    }

    // ------------------------------------------------------- Form 13 grade/print

    public function test_ojt_grade_formula_matches_the_official_form(): void
    {
        $all = fn (int $n) => array_fill_keys(array_keys(InternshipEvaluationForm::COMPETENCIES), $n);

        $this->assertSame(100.0, InternshipEvaluationForm::grade($all(5)));
        $this->assertSame(80.0, InternshipEvaluationForm::grade($all(3)));
        $this->assertSame(60.0, InternshipEvaluationForm::grade($all(1)));
        $this->assertSame(50.0, InternshipEvaluationForm::totalPoints($all(5)));
        $this->assertNull(InternshipEvaluationForm::grade(['conduct' => 5]), 'not all competencies rated');

        // weights from the official form add up to 100 and E (quality and accuracy) is the heaviest at 20
        $this->assertSame(100, array_sum(array_column(InternshipEvaluationForm::COMPETENCIES, 'weight')));
        $this->assertSame(20, InternshipEvaluationForm::COMPETENCIES['quality_accuracy_work']['weight']);

        // uneven ratings: A5 B4 C3 D2 E1 F5 G4 H3 -> sum(r*w)/100 = 5*.10+4*.15+3*.10+2*.10+1*.20+5*.10+4*.15+3*.10 = 3.2 -> 32 points -> 82
        $this->assertSame(82.0, InternshipEvaluationForm::grade([
            'ability_to_learn' => 5, 'work_attitude' => 4, 'conduct' => 3, 'motivation_initiative' => 2,
            'quality_accuracy_work' => 1, 'quantity_of_work' => 5, 'safety_practices' => 4, 'appearance_hygiene' => 3,
        ]));
    }

    public function test_form_13_printout_shows_control_number_descriptor_and_the_real_grade(): void
    {
        $requirement = $this->req('EVALUATION_FORM');
        $this->actingAs($this->hteUser)->post(route('hte.forms.store', [$requirement, $this->deployment]), $this->payload($requirement, 3))
            ->assertSessionHasNoErrors();

        $document = Document::where('document_requirement_id', $requirement->id)->firstOrFail();
        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user');
        $html = view('instructor.documents.printable-ojt-form', ['document' => $document])->render();

        $this->assertStringContainsString('FM-AA-INT-13', $html);
        $this->assertStringContainsString('01-Feb-2017', $html);
        $this->assertStringContainsString('Asks questions; has adequate degree of knowledge', $html); // D / 3, verbatim
        $this->assertStringContainsString('80.00', $html);       // all 3s -> OJT grade 80
        $this->assertStringContainsString('30.00 / 50', $html);  // total points
    }

    public function test_form_21_printout_uses_the_likert_scale(): void
    {
        $requirement = $this->req('TRAINING_SUPERVISOR_FEEDBACK');
        $this->actingAs($this->hteUser)->post(route('hte.forms.store', [$requirement, $this->deployment]), $this->payload($requirement, 4))
            ->assertSessionHasNoErrors();

        $document = Document::where('document_requirement_id', $requirement->id)->firstOrFail();
        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user');
        $html = view('instructor.documents.printable-ojt-form', ['document' => $document])->render();

        $this->assertStringContainsString('FM-AA-INT-21', $html);
        $this->assertStringContainsString('Agree', $html);
    }
}
