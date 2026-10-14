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
use App\Models\Notification;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\OfficialOjtFormsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Where each of the six evaluation forms is delivered:
 *   Form 13  HTE        -> Instructor   (Evaluations)
 *   Form 21  HTE        -> Student      (My HTE Info)
 *   Form 20  Student    -> HTE          (Evaluations Received)
 *   Form 23c Student    -> HTE          (Evaluations Received)
 *   Form 23a Instructor -> HTE          (Evaluations Received)
 *   Form 23b HTE        -> Coordinator  (Documents)
 */
class EvaluationRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private Program $program;
    private Hte $hte;
    private Hte $otherHte;
    private Deployment $deployment;
    private User $studentUser;
    private User $otherStudentUser;
    private User $hteUser;
    private User $otherHteUser;
    private User $instructorUser;
    private User $otherProgramInstructorUser;
    private User $coordinatorUser;
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['student', 'hte_representative', 'instructor', 'coordinator'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }
        $this->campus = Campus::forceCreate(['code' => 'AAA', 'name' => 'PSU Alaminos City', 'status' => 'active']);
        $this->program = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'BS Information Technology', 'required_hours' => 486]);
        $otherProgram = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSHM', 'name' => 'BS Hospitality Management', 'required_hours' => 486]);
        $this->hte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Fareheaven Boarding House', 'address' => 'Alaminos City']);
        $this->otherHte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Other Company', 'address' => 'Dagupan City']);

        $this->studentUser = $this->makeUser('student', 'S-1');
        $student = Student::forceCreate(['user_id' => $this->studentUser->id, 'campus_id' => $this->campus->id, 'program_id' => $this->program->id]);
        $this->otherStudentUser = $this->makeUser('student', 'S-2');
        $otherStudent = Student::forceCreate(['user_id' => $this->otherStudentUser->id, 'campus_id' => $this->campus->id, 'program_id' => $this->program->id]);

        $this->hteUser = $this->makeUser('hte_representative', 'H-1');
        HteRepresentative::forceCreate(['user_id' => $this->hteUser->id, 'hte_id' => $this->hte->id]);
        $this->otherHteUser = $this->makeUser('hte_representative', 'H-2');
        HteRepresentative::forceCreate(['user_id' => $this->otherHteUser->id, 'hte_id' => $this->otherHte->id]);

        $this->instructorUser = $this->makeUser('instructor', 'I-1');
        Instructor::forceCreate(['user_id' => $this->instructorUser->id, 'campus_id' => $this->campus->id, 'program_id' => $this->program->id]);
        $this->otherProgramInstructorUser = $this->makeUser('instructor', 'I-2');
        Instructor::forceCreate(['user_id' => $this->otherProgramInstructorUser->id, 'campus_id' => $this->campus->id, 'program_id' => $otherProgram->id]);

        $this->coordinatorUser = $this->makeUser('coordinator', 'C-1');

        foreach ([[$student, $this->hte], [$otherStudent, $this->otherHte]] as $i => [$s, $hte]) {
            $internship = Internship::forceCreate([
                'student_id' => $s->id, 'campus_id' => $this->campus->id, 'program_id' => $this->program->id,
                'status' => 'completed', 'required_hours' => 486,
            ]);
            $deployment = Deployment::forceCreate([
                'internship_id' => $internship->id, 'hte_id' => $hte->id,
                'status' => 'completed', 'start_date' => '2026-09-01', 'end_date' => '2026-10-03',
            ]);
            if ($i === 0) {
                $this->deployment = $deployment;
            }
        }

        $this->seed(OfficialOjtFormsSeeder::class);
    }

    private function makeUser(string $role, string $id): User
    {
        return User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $this->roles[$role]->id, 'id_number' => $id, 'name' => "$role $id",
            'email' => "$id@t.local", 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
    }

    private function req(string $code): DocumentRequirement
    {
        return DocumentRequirement::where('campus_id', $this->campus->id)->where('code', $code)->firstOrFail();
    }

    private function payload(DocumentRequirement $requirement, int $rating = 4): array
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

    private function hteSubmits(string $code): Document
    {
        $requirement = $this->req($code);
        $this->actingAs($this->hteUser)->post(route('hte.forms.store', [$requirement, $this->deployment]), $this->payload($requirement))
            ->assertSessionHasNoErrors();

        return Document::where('document_requirement_id', $requirement->id)->firstOrFail();
    }

    private function studentSubmits(string $code): Document
    {
        $requirement = $this->req($code);
        $this->actingAs($this->studentUser)->post(route('student.documents.fill.store', $requirement), $this->payload($requirement))
            ->assertSessionHasNoErrors();

        return Document::where('document_requirement_id', $requirement->id)->firstOrFail();
    }

    private function instructorSubmits(): Document
    {
        $requirement = $this->req('PARTNER_AGENCY_EVAL_INSTRUCTOR');
        $this->actingAs($this->instructorUser)->post(route('instructor.forms.store', [$requirement, $this->deployment]), $this->payload($requirement))
            ->assertSessionHasNoErrors();

        return Document::where('document_requirement_id', $requirement->id)->firstOrFail();
    }

    // ------------------------------------------------ Form 13: HTE -> Instructor

    public function test_form_13_lands_in_the_instructors_evaluations_sidebar(): void
    {
        $document = $this->hteSubmits('EVALUATION_FORM');

        $this->assertSame(1, Notification::where('user_id', $this->instructorUser->id)->where('title', 'like', '%Form 13%')->count());
        $this->assertSame(0, Notification::where('user_id', $this->otherProgramInstructorUser->id)->count());

        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations'))
            ->assertOk()->assertSee('Received')
            ->assertSee(route('instructor.evaluations.show', $document), false);

        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations.show', $document))
            ->assertOk()->assertSee('Internship Evaluation Form');
    }

    public function test_form_13_is_not_visible_to_an_instructor_of_another_program_or_to_a_student(): void
    {
        $document = $this->hteSubmits('EVALUATION_FORM');

        $this->actingAs($this->otherProgramInstructorUser)->get(route('instructor.evaluations.show', $document))->assertForbidden();
        $this->actingAs($this->studentUser)->get(route('instructor.evaluations.show', $document))->assertForbidden();
        $this->actingAs($this->hteUser)->get(route('instructor.evaluations.show', $document))->assertForbidden();
    }

    public function test_instructor_evaluations_route_only_opens_form_13(): void
    {
        $other = $this->hteSubmits('TRAINING_SUPERVISOR_FEEDBACK');

        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations.show', $other))->assertNotFound();
    }

    // ------------------------------------------------ Form 21: HTE -> Student

    public function test_form_21_lands_in_the_students_my_hte_info_sidebar(): void
    {
        $this->actingAs($this->studentUser)->get(route('student.hte'))->assertOk()->assertSee('Not yet received');

        $document = $this->hteSubmits('TRAINING_SUPERVISOR_FEEDBACK');

        $this->assertSame(1, Notification::where('user_id', $this->studentUser->id)->where('title', 'like', '%Form 21%')->count());
        $this->assertSame(0, Notification::where('user_id', $this->otherStudentUser->id)->count());

        $this->actingAs($this->studentUser)->get(route('student.hte'))
            ->assertOk()->assertSee('Received')->assertSee('Agree')
            ->assertSee(route('student.hte.feedback', $document), false);

        $this->actingAs($this->studentUser)->get(route('student.hte.feedback', $document))
            ->assertOk()->assertSee('FM-AA-INT-21');
    }

    public function test_a_student_cannot_open_another_students_form_21(): void
    {
        $document = $this->hteSubmits('TRAINING_SUPERVISOR_FEEDBACK');

        $this->actingAs($this->otherStudentUser)->get(route('student.hte.feedback', $document))->assertForbidden();
        $this->actingAs($this->otherStudentUser)->get(route('student.hte'))->assertOk()->assertDontSee('Strongly Agree');
    }

    public function test_student_my_hte_info_only_opens_form_21(): void
    {
        $document = $this->hteSubmits('EVALUATION_FORM');   // Form 13 is for the instructor, not the student

        $this->actingAs($this->studentUser)->get(route('student.hte.feedback', $document))->assertNotFound();
    }

    // ----------------------------------- Forms 20 / 23c / 23a: -> HTE received

    public function test_student_forms_20_and_23c_land_in_the_htes_evaluations_received(): void
    {
        $form20 = $this->studentSubmits('STUDENT_FEEDBACK_FORM');
        $form23c = $this->studentSubmits('STUDENT_HTE_EVALUATION');

        $this->assertSame(2, Notification::where('user_id', $this->hteUser->id)->count());
        $this->assertSame(0, Notification::where('user_id', $this->otherHteUser->id)->count());
        $this->assertSame(0, Notification::where('user_id', $this->instructorUser->id)->count(), 'instructor is not the recipient');

        $page = $this->actingAs($this->hteUser)->get(route('hte.evaluations.received'))->assertOk();
        $page->assertSee(route('hte.evaluations.received.show', $form20), false);
        $page->assertSee(route('hte.evaluations.received.show', $form23c), false);

        $this->actingAs($this->hteUser)->get(route('hte.evaluations.received.show', $form20))->assertOk()->assertSee('FEEDBACK FORM');
        $this->actingAs($this->hteUser)->get(route('hte.evaluations.received.show', $form23c))->assertOk()->assertSee('EVALUATION INSTRUMENT OF PSU PARTNER AGENCIES');
    }

    public function test_form_23a_from_the_instructor_still_lands_in_the_htes_evaluations_received(): void
    {
        $document = $this->instructorSubmits();

        $this->actingAs($this->hteUser)->get(route('hte.evaluations.received'))
            ->assertOk()->assertSee(route('hte.evaluations.received.show', $document), false);
        $this->actingAs($this->hteUser)->get(route('hte.evaluations.received.show', $document))->assertOk();
    }

    public function test_evaluations_received_filters_by_form(): void
    {
        $form20 = $this->studentSubmits('STUDENT_FEEDBACK_FORM');
        $form23a = $this->instructorSubmits();

        $only20 = $this->actingAs($this->hteUser)->get(route('hte.evaluations.received', ['form' => 'STUDENT_FEEDBACK_FORM']))->assertOk();
        $only20->assertSee(route('hte.evaluations.received.show', $form20), false);
        $only20->assertDontSee(route('hte.evaluations.received.show', $form23a), false);
    }

    public function test_another_htes_evaluations_are_never_listed_or_opened(): void
    {
        $form20 = $this->studentSubmits('STUDENT_FEEDBACK_FORM');

        $this->actingAs($this->otherHteUser)->get(route('hte.evaluations.received'))
            ->assertOk()->assertDontSee(route('hte.evaluations.received.show', $form20), false);
        $this->actingAs($this->otherHteUser)->get(route('hte.evaluations.received.show', $form20))->assertForbidden();
    }

    public function test_hte_cannot_open_forms_it_sent_through_the_received_inbox(): void
    {
        $form13 = $this->hteSubmits('EVALUATION_FORM');
        $form23b = $this->hteSubmits('PARTNER_AGENCY_EVAL_SELF');

        $this->actingAs($this->hteUser)->get(route('hte.evaluations.received.show', $form13))->assertNotFound();
        $this->actingAs($this->hteUser)->get(route('hte.evaluations.received.show', $form23b))->assertNotFound();
    }

    public function test_student_forms_20_and_23c_stay_out_of_instructor_document_review(): void
    {
        $form20 = $this->studentSubmits('STUDENT_FEEDBACK_FORM');
        $this->studentSubmits('STUDENT_HTE_EVALUATION');

        $this->flushSession();   // drop the student's "sent to your HTE" flash message
        $this->actingAs($this->instructorUser)->get(route('instructor.documents'))->assertOk()
            ->assertDontSee($form20->requirement->name)
            ->assertDontSee($this->req('STUDENT_HTE_EVALUATION')->name);

        $this->actingAs($this->instructorUser)->post(route('instructor.documents.review', $form20), ['decision' => 'approved'])->assertForbidden();
    }

    public function test_student_sees_forms_20_and_23c_as_sent_and_cannot_edit_them_afterwards(): void
    {
        $requirement = $this->req('STUDENT_FEEDBACK_FORM');
        $this->studentSubmits('STUDENT_FEEDBACK_FORM');

        $this->actingAs($this->studentUser)->get(route('student.documents'))->assertOk()->assertSee('Sent to HTE');
        $this->actingAs($this->studentUser)->get(route('student.documents.fill', $requirement))->assertForbidden();

        $before = Document::where('document_requirement_id', $requirement->id)->first()->fieldValue('problems_met');
        $this->actingAs($this->studentUser)
            ->post(route('student.documents.fill.store', $requirement), $this->payload($requirement) + ['problems_met' => 'changed'])
            ->assertForbidden();
        $this->assertSame($before, Document::where('document_requirement_id', $requirement->id)->first()->fieldValue('problems_met'));
    }

    // ------------------------------------------------ Form 23b: HTE -> Coordinator

    public function test_form_23b_lands_in_the_coordinators_document_tracking_panel(): void
    {
        $this->actingAs($this->coordinatorUser)->get(route('coordinator.documents'))
            ->assertOk()->assertSee('HTE Evaluation Forms')->assertSee('Pending')->assertSee('Awaiting HTE');

        $document = $this->hteSubmits('PARTNER_AGENCY_EVAL_SELF');

        $this->assertSame(1, Notification::where('user_id', $this->coordinatorUser->id)->where('title', 'like', '%Form 23b%')->count());

        $this->actingAs($this->coordinatorUser)->get(route('coordinator.documents'))
            ->assertOk()->assertSee('Fareheaven Boarding House')
            ->assertSee(route('coordinator.documents.printable', $document), false);

        $this->actingAs($this->coordinatorUser)->get(route('coordinator.documents.printable', $document))
            ->assertOk()->assertDontSee('window.print()', false);
    }

    public function test_coordinator_panel_filters_and_searches(): void
    {
        $this->hteSubmits('PARTNER_AGENCY_EVAL_SELF');   // Fareheaven submitted, Other Company pending

        $submitted = $this->actingAs($this->coordinatorUser)->get(route('coordinator.documents', ['hte_eval' => 'submitted']))->assertOk();
        $submitted->assertSee('Fareheaven Boarding House');
        $submitted->assertDontSee('Awaiting HTE');

        $pending = $this->actingAs($this->coordinatorUser)->get(route('coordinator.documents', ['hte_eval' => 'pending']))->assertOk();
        $pending->assertSee('Other Company');
        $pending->assertDontSee('View Form 23b');

        $this->actingAs($this->coordinatorUser)->get(route('coordinator.documents', ['hte_search' => 'zzzz']))
            ->assertOk()->assertSee('No HTE evaluation forms match this filter.');
    }

    public function test_form_23b_does_not_go_to_the_instructor_or_student(): void
    {
        $this->hteSubmits('PARTNER_AGENCY_EVAL_SELF');

        $this->assertSame(0, Notification::where('user_id', $this->instructorUser->id)->count());
        $this->assertSame(0, Notification::where('user_id', $this->studentUser->id)->count());
    }

    // ------------------------------------------------ notifications open the right sidebar

    public function test_the_form_13_notification_opens_the_evaluations_sidebar_at_the_students_row(): void
    {
        $this->hteSubmits('EVALUATION_FORM');

        $notification = Notification::where('user_id', $this->instructorUser->id)->firstOrFail();
        $studentId = $this->studentUser->student->id;
        $this->assertSame('/instructor/evaluations#student-'.$studentId, $notification->url);

        $this->actingAs($this->instructorUser)->get(route('notifications.open', $notification))
            ->assertRedirect('/instructor/evaluations#student-'.$studentId);

        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations'))
            ->assertOk()->assertSee('id="student-'.$studentId.'"', false);
    }

    public function test_legacy_instructor_notifications_are_rerouted_by_the_migration(): void
    {
        $row = fn (int $userId, string $title) => [
            'user_id' => $userId, 'type' => 'document_status', 'title' => $title,
            'message' => 'The form is available in Document Review.', 'url' => null, 'resolve_key' => 'document:9',
            'created_at' => now(), 'updated_at' => now(),
        ];
        \DB::table('notifications')->insert([
            $row($this->instructorUser->id, 'HTE submitted Form 13 – Internship Evaluation Form'),
            $row($this->instructorUser->id, 'HTE submitted Form 21 – Training Supervisor’s Feedback Form'),
            $row($this->instructorUser->id, 'HTE submitted Form 23b – Evaluation Instrument of PSU Partner Agencies (Self Ratee)'),
            // Somebody else's notice with the same wording must be left alone.
            $row($this->coordinatorUser->id, 'HTE submitted Form 13 – Internship Evaluation Form'),
        ]);

        (require database_path('migrations/2026_10_13_000001_reroute_legacy_hte_evaluation_notifications.php'))->up();

        $mine = \DB::table('notifications')->where('user_id', $this->instructorUser->id)->get();
        $this->assertCount(1, $mine, 'Forms 21 and 23b are no longer the instructor\'s');
        $this->assertStringContainsString('Form 13', $mine[0]->title);
        $this->assertSame('/instructor/evaluations', $mine[0]->url);
        $this->assertNull($mine[0]->resolve_key);

        $other = \DB::table('notifications')->where('user_id', $this->coordinatorUser->id)->first();
        $this->assertNull($other->url);
        $this->assertSame('document:9', $other->resolve_key);
    }

    // ------------------------------------------------ printable layouts (match the official 2024 forms)

    public function test_form_13_printout_follows_the_official_layout(): void
    {
        $requirement = $this->req('EVALUATION_FORM');
        $payload = $this->payload($requirement) + [];
        $payload['rating_a_ability_to_learn'] = 5;
        $payload['termination_company_rules'] = '1';
        $payload['future_employment'] = '1';
        $payload['ojt_grade'] = 90;
        $this->actingAs($this->hteUser)->post(route('hte.forms.store', [$requirement, $this->deployment]), $payload)->assertSessionHasNoErrors();
        $document = Document::where('document_requirement_id', $requirement->id)->firstOrFail();

        $html = $this->actingAs($this->instructorUser)->get(route('instructor.evaluations.show', $document))->assertOk()->getContent();

        foreach (['FM-AA-INT-11', 'Rev. 1', '14-Oct-2024', 'INTERNSHIP EVALUATION FORM', 'PANGASINAN STATE UNIVERSITY',
                  'COMPETENCY', 'CRITERIA', 'RATING', 'REMARKS', 'A. Ability To Learn (10%)', 'H. Appearance/Hygiene (10%)',
                  'OJT GRADE', 'Total Points (A + B + C + D + E + F + G+H x 10) / 50 x 50 + 50',
                  'The Internship Practicum was terminated:', 'We would be pleased to employ this Student-Trainee in the future',
                  'Printed Name and Signature of Person Completing this Form', 'Signature of Student-Intern', 'APPROVED:', 'Training Supervisor'] as $needle) {
            $this->assertStringContainsString($needle, $html, "Form 13 printout is missing: $needle");
        }
        $this->assertStringContainsString('circle on', $html, 'the chosen rating is circled');
        $this->assertSame(8, substr_count($html, 'circle on'), 'exactly one circled rating per competency');
        $this->assertMatchesRegularExpression('/<span class="blank">\x{2713}<\/span>violation of Company Rules/u', $html, 'a ticked termination reason shows a check on its blank');
    }

    public function test_form_23b_printout_uses_the_official_19b_layout(): void
    {
        $document = $this->hteSubmits('PARTNER_AGENCY_EVAL_SELF');

        $html = $this->actingAs($this->coordinatorUser)->get(route('coordinator.documents.printable', $document))->assertOk()->getContent();

        foreach (['FM-AA-INT-19b', 'Rev. 1', '14-Nov-2024', 'EVALUATION INSTRUMENT OF PSU PARTNER AGENCIES', 'PANGASINAN STATE UNIVERSITY',
                  'Agency / Institution', 'Unit/Division', 'Sex - Gender', 'Type of Rater', 'Self Ratee (Representative of HTE)',
                  'Communication', 'Ethical Dealings', 'Student Satisfaction - PSU', 'Student Satisfaction', 'Quality Delivery',
                  '5 - Extremely Satisfied', 'Evaluated by:'] as $needle) {
            // Section titles and field labels are upper-cased by the print CSS, so compare case-insensitively.
            $this->assertStringContainsStringIgnoringCase($needle, $html, "Form 23b printout is missing: $needle");
        }
        $this->assertStringNotContainsString('Interpretation', $html, 'the old generic table is gone');
        $this->assertStringNotContainsString('01-Feb-2017', $html);
        // Self Ratee is the ticked rater type; Faculty / Student are not.
        $this->assertMatchesRegularExpression('/<span class="box">\x{2713}<\/span>Self Ratee/u', $html);
        $this->assertMatchesRegularExpression('/<span class="box"><\/span>Faculty/', $html);
        $this->assertMatchesRegularExpression('/<span class="box"><\/span>Student/', $html);
        // 15 criteria, each with exactly one check under its chosen level.
        $this->assertSame(15, preg_match_all('/<td class="mark">\x{2713}<\/td>/u', $html));
    }

    // ------------------------------------------------ regression

    public function test_form_13_ojt_grade_is_validated_without_a_server_error(): void
    {
        $requirement = $this->req('EVALUATION_FORM');
        $payload = $this->payload($requirement);

        $payload['ojt_grade'] = 150;   // above the 100 maximum -> validation error, not a 500
        $this->actingAs($this->hteUser)->post(route('hte.forms.store', [$requirement, $this->deployment]), $payload)
            ->assertSessionHasErrors('ojt_grade');

        $payload['ojt_grade'] = 90;
        $this->actingAs($this->hteUser)->post(route('hte.forms.store', [$requirement, $this->deployment]), $payload)
            ->assertSessionHasNoErrors();
    }

    public function test_hte_cannot_submit_the_same_form_twice(): void
    {
        $this->hteSubmits('TRAINING_SUPERVISOR_FEEDBACK');
        $requirement = $this->req('TRAINING_SUPERVISOR_FEEDBACK');

        $this->actingAs($this->hteUser)->post(route('hte.forms.store', [$requirement, $this->deployment]), $this->payload($requirement))->assertForbidden();
        $this->assertSame(1, Notification::where('user_id', $this->studentUser->id)->count());
    }
}
