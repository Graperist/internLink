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
 * Evaluations work like the PSU faculty evaluation: a list of names, click
 * one and rate it; whoever is rated never sees it. Everything goes to the
 * Subject Instructor (Evaluations):
 *   Form 13, 21  HTE about the intern
 *   Form 23c, 20 the intern about the agency
 *   Form 23b     the HTE about itself (once per agency)
 *   Form 23a     the instructor about the agency (once per agency, kept by the instructor)
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
            ->assertRedirect(route('hte.evaluations'))->assertSessionHasNoErrors();

        return Document::where('document_requirement_id', $requirement->id)->firstOrFail();
    }

    private function studentSubmits(string $code): Document
    {
        $requirement = $this->req($code);
        $this->actingAs($this->studentUser)->post(route('student.documents.fill.store', $requirement), $this->payload($requirement))
            ->assertRedirect(route('student.hte').'#evaluate')->assertSessionHasNoErrors();

        return Document::where('document_requirement_id', $requirement->id)->firstOrFail();
    }

    private function instructorSubmits(?Hte $hte = null): Document
    {
        $requirement = $this->req('PARTNER_AGENCY_EVAL_INSTRUCTOR');
        $this->actingAs($this->instructorUser)->post(route('instructor.agency-evaluations.store', $hte ?? $this->hte), $this->payload($requirement))
            ->assertRedirect(route('instructor.evaluations'))->assertSessionHasNoErrors();

        return Document::where('document_requirement_id', $requirement->id)->latest('id')->firstOrFail();
    }

    private function hteRatesItself(): Document
    {
        $requirement = $this->req('PARTNER_AGENCY_EVAL_SELF');
        $this->actingAs($this->hteUser)->post(route('hte.self-evaluation.store'), $this->payload($requirement))
            ->assertRedirect(route('hte.evaluations'))->assertSessionHasNoErrors();

        return Document::where('document_requirement_id', $requirement->id)->firstOrFail();
    }

    // ------------------------------------------------ the HTE rates its interns -> instructor only

    public function test_the_hte_lists_its_interns_and_rates_them_by_name(): void
    {
        $this->actingAs($this->hteUser)->get(route('hte.evaluations'))->assertOk()
            ->assertSee('Evaluations')->assertSee($this->studentUser->name)->assertDontSee($this->otherStudentUser->name)
            ->assertSee(route('hte.forms.create', [$this->req('EVALUATION_FORM'), $this->deployment]));

        $this->hteSubmits('EVALUATION_FORM');
        $this->hteSubmits('TRAINING_SUPERVISOR_FEEDBACK');
        $this->actingAs($this->hteUser)->get(route('hte.evaluations'))->assertOk()->assertSee('EVALUATED');
    }

    public function test_forms_13_and_21_go_to_the_instructor_and_the_student_never_sees_them(): void
    {
        $form13 = $this->hteSubmits('EVALUATION_FORM');
        $form21 = $this->hteSubmits('TRAINING_SUPERVISOR_FEEDBACK');

        $this->assertSame(2, Notification::where('user_id', $this->instructorUser->id)->count());
        $this->assertSame(0, Notification::where('user_id', $this->studentUser->id)->count());

        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations'))->assertOk()
            ->assertSee(route('instructor.evaluations.show', $form13))->assertSee(route('instructor.evaluations.show', $form21));
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations.show', $form21))->assertOk();
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations.show', ['document' => $form13, 'download' => 1]))->assertOk()
            ->assertSee('pdfStatus', false);

        // The student: nothing on My HTE, no way to open it.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('student.hte.feedback'));
        $this->actingAs($this->studentUser)->get(route('student.hte'))->assertOk()->assertDontSee('Training Supervisor');
        $this->actingAs($this->studentUser)->get(route('instructor.evaluations.show', $form21))->assertForbidden();
    }

    public function test_another_program_instructor_cannot_open_them(): void
    {
        $form13 = $this->hteSubmits('EVALUATION_FORM');
        $this->actingAs($this->otherProgramInstructorUser)->get(route('instructor.evaluations.show', $form13))->assertForbidden();
        $this->actingAs($this->otherProgramInstructorUser)->get(route('instructor.evaluations'))->assertOk()->assertDontSee($this->studentUser->name);
    }

    public function test_the_hte_cannot_rate_another_agencys_intern_or_one_still_training(): void
    {
        $requirement = $this->req('EVALUATION_FORM');
        $otherDeployment = Deployment::where('hte_id', $this->otherHte->id)->firstOrFail();
        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$requirement, $otherDeployment]))->assertForbidden();

        $this->deployment->update(['status' => 'active']);
        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$requirement, $this->deployment]))->assertStatus(422);
    }

    // ------------------------------------------------ the HTE rates itself, once -> instructor only

    public function test_form_23b_is_once_per_agency_and_goes_to_the_instructor(): void
    {
        $this->deployment->update(['end_date' => now()->toDateString()]);   // ended today: still in the editing window
        $this->actingAs($this->hteUser)->get(route('hte.evaluations'))->assertOk()->assertSee('Rate Your Agency')
            ->assertSee(route('hte.self-evaluation.create'));
        $this->actingAs($this->hteUser)->get(route('hte.self-evaluation.create'))->assertOk()->assertSee('Your agency: '.$this->hte->name);
        $document = $this->hteRatesItself();
        $this->assertSame($this->hte->id, $document->hte_id);

        // It can be edited afterwards: same one document, the instructor is told it was updated.
        $requirement = $this->req('PARTNER_AGENCY_EVAL_SELF');
        $this->actingAs($this->hteUser)->get(route('hte.evaluations'))->assertOk()->assertSee('EVALUATED')->assertSee('Edit');
        $this->actingAs($this->hteUser)->get(route('hte.self-evaluation.create'))->assertOk()
            ->assertSee('Editing your submitted evaluation')->assertSee('Save changes');
        $this->actingAs($this->hteUser)->post(route('hte.self-evaluation.store'), $this->payload($requirement, 2))
            ->assertRedirect(route('hte.evaluations'))->assertSessionHas('status', fn ($m) => str_contains($m, 'updated'));
        $this->assertSame(1, Document::where('document_requirement_id', $requirement->id)->count());
        $this->assertTrue(Notification::where('user_id', $this->instructorUser->id)->where('title', 'like', 'Form 23b updated by%')->exists());

        $this->assertTrue(Notification::where('user_id', $this->instructorUser->id)->where('title', 'like', 'Form 23b%')->exists());
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations'))->assertOk()
            ->assertSee(route('instructor.evaluations.show', $document));
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations.show', $document))->assertOk();
        $this->actingAs($this->otherProgramInstructorUser)->get(route('instructor.evaluations.show', $document))->assertForbidden();
    }

    // ------------------------------------------------ the student rates the agency -> instructor only

    public function test_the_student_sees_only_their_agency_and_rates_it(): void
    {
        $this->deployment->update(['end_date' => now()->toDateString()]);   // ended today: still in the editing window
        $this->actingAs($this->studentUser)->get(route('student.hte'))->assertOk()
            ->assertSee('My HTE')->assertSee('Evaluate Your Agency')->assertSee($this->hte->name)->assertDontSee($this->otherHte->name)
            ->assertSee(route('student.documents.fill', $this->req('STUDENT_HTE_EVALUATION')));
        // Not in My Documents any more.
        $this->actingAs($this->studentUser)->get(route('student.documents'))->assertOk()
            ->assertDontSee('Form 23c')->assertDontSee('Student-Intern’s Feedback');

        $this->studentSubmits('STUDENT_HTE_EVALUATION');
        $this->studentSubmits('STUDENT_FEEDBACK_FORM');
        $this->actingAs($this->studentUser)->get(route('student.hte'))->assertOk()->assertSee('EVALUATED');

        // It can be edited afterwards: the form opens with Save changes, and saving updates the same document.
        $requirement = $this->req('STUDENT_HTE_EVALUATION');
        $this->actingAs($this->studentUser)->get(route('student.hte'))->assertOk()->assertSee('Edit');
        $this->actingAs($this->studentUser)->get(route('student.documents.fill', $requirement))->assertOk()
            ->assertSee('Editing your submitted evaluation')->assertSee('Save changes');
        $ratingKey = $requirement->fieldDefinitions->firstWhere('field_type', 'rating_scale')->field_key;
        $this->actingAs($this->studentUser)->post(route('student.documents.fill.store', $requirement), $this->payload($requirement, 2))
            ->assertRedirect()->assertSessionHas('status', fn ($m) => str_contains($m, 'updated'));
        $documents = Document::where('document_requirement_id', $requirement->id)->get();
        $this->assertCount(1, $documents);
        $this->assertSame('2', (string) $documents->first()->fieldValue($ratingKey));
        $this->assertTrue(Notification::where('user_id', $this->instructorUser->id)->where('title', 'like', '%updated their evaluation%')->exists());
    }

    public function test_the_students_evaluations_go_to_the_instructor_and_the_hte_never_sees_them(): void
    {
        $form23c = $this->studentSubmits('STUDENT_HTE_EVALUATION');
        $form20 = $this->studentSubmits('STUDENT_FEEDBACK_FORM');

        $this->assertSame(0, Notification::where('user_id', $this->hteUser->id)->count());
        $this->assertSame(2, Notification::where('user_id', $this->instructorUser->id)->count());
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations'))->assertOk()
            ->assertSee(route('instructor.evaluations.show', $form23c))->assertSee(route('instructor.evaluations.show', $form20));
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations.show', $form23c))->assertOk();

        // The HTE: no Evaluations Received page, and cannot open them.
        $this->actingAs($this->hteUser)->get('/hte/evaluations/received')->assertRedirect('/hte/evaluations');
        $this->actingAs($this->hteUser)->get(route('hte.evaluations'))->assertOk()->assertDontSee('Evaluations Received');
        $this->actingAs($this->hteUser)->get(route('instructor.evaluations.show', $form23c))->assertForbidden();
    }

    public function test_the_students_evaluations_stay_out_of_document_review(): void
    {
        $this->studentSubmits('STUDENT_HTE_EVALUATION');
        $studentId = $this->studentUser->student->id;
        $this->actingAs($this->instructorUser)->get(route('instructor.documents', ['student' => $studentId]))->assertOk()
            ->assertDontSee('Evaluation Instrument of PSU Partner Agencies (Student)');
    }

    public function test_the_old_evaluate_agency_link_opens_my_hte(): void
    {
        $this->actingAs($this->studentUser)->get(route('student.evaluations'))->assertRedirect(route('student.hte').'#evaluate');
        $this->actingAs($this->studentUser)->get(route('student.hte'))->assertOk()
            ->assertSee('id="evaluate"', false)->assertDontSee('Evaluate Agency</a>', false)->assertDontSee('My HTE Info');
    }

    public function test_the_student_cannot_evaluate_before_the_internship_is_completed(): void
    {
        $this->deployment->update(['status' => 'active']);
        $this->deployment->internship->update(['status' => 'deployed']);
        $this->actingAs($this->studentUser)->get(route('student.hte'))->assertOk()
            ->assertSee('once your internship is completed')->assertDontSee('Evaluate</a>', false);
    }

    // ------------------------------------------------ the instructor rates each agency, once -> kept by the instructor

    public function test_the_instructor_lists_the_agencies_and_rates_each_once(): void
    {
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations'))->assertOk()
            ->assertSee('Evaluate Partner Agencies')->assertSee($this->hte->name)->assertSee($this->otherHte->name)
            ->assertSee(route('instructor.agency-evaluations.create', $this->hte));
        $this->actingAs($this->instructorUser)->get(route('instructor.agency-evaluations.create', $this->hte))->assertOk()
            ->assertSee('Partner agency: '.$this->hte->name);

        $document = $this->instructorSubmits();
        $this->assertSame($this->hte->id, $document->hte_id);
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations'))->assertOk()->assertSee('EVALUATED');

        // Once per agency.
        $requirement = $this->req('PARTNER_AGENCY_EVAL_INSTRUCTOR');
        $this->actingAs($this->instructorUser)->get(route('instructor.agency-evaluations.create', $this->hte))->assertForbidden();
        $this->actingAs($this->instructorUser)->post(route('instructor.agency-evaluations.store', $this->hte), $this->payload($requirement))->assertForbidden();
        // The other agency is still to do.
        $this->instructorSubmits($this->otherHte);
        $this->assertSame(2, Document::where('document_requirement_id', $requirement->id)->count());
    }

    public function test_form_23a_stays_with_the_instructor(): void
    {
        $document = $this->instructorSubmits();
        $this->assertSame(0, Notification::where('user_id', $this->hteUser->id)->count());
        $this->assertSame(0, Notification::where('user_id', $this->studentUser->id)->count());
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations.show', $document))->assertOk()
            ->assertSee($this->hte->name);
        $this->actingAs($this->hteUser)->get(route('instructor.evaluations.show', $document))->assertForbidden();
        $this->actingAs($this->otherProgramInstructorUser)->get(route('instructor.evaluations.show', $document))->assertForbidden();
    }

    public function test_an_agency_without_a_completed_intern_of_the_program_cannot_be_rated(): void
    {
        $this->actingAs($this->otherProgramInstructorUser)->get(route('instructor.agency-evaluations.create', $this->hte))->assertForbidden();
    }

    // ------------------------------------------------ the coordinator, as instructor of their own program

    public function test_the_coordinator_evaluates_like_any_instructor_but_only_their_own_program(): void
    {
        // The coordinator handles this program: same Evaluations module as the instructor.
        Instructor::firstOrCreate(['user_id' => $this->coordinatorUser->id], ['campus_id' => $this->campus->id, 'program_id' => $this->program->id]);
        $form13 = $this->hteSubmits('EVALUATION_FORM');
        $form23c = $this->studentSubmits('STUDENT_HTE_EVALUATION');

        $this->actingAs($this->coordinatorUser)->get(route('instructor.dashboard'))->assertOk()->assertSee(route('instructor.evaluations'));
        $this->actingAs($this->coordinatorUser)->get(route('instructor.evaluations'))->assertOk()->assertSee($this->studentUser->name);
        $this->actingAs($this->coordinatorUser)->get(route('instructor.evaluations.show', $form13))->assertOk();
        $this->actingAs($this->coordinatorUser)->get(route('instructor.evaluations.show', $form23c))->assertOk();
        $this->actingAs($this->coordinatorUser)->get(route('instructor.agency-evaluations.create', $this->hte))->assertOk();
        $this->assertTrue(Notification::where('user_id', $this->coordinatorUser->id)->exists(), 'notified like the instructor');

        // Campus pages still show no evaluations.
        $this->actingAs($this->coordinatorUser)->get(route('coordinator.documents'))->assertOk()
            ->assertDontSee('HTE Evaluation Forms')->assertDontSee('Form 23b');
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('coordinator.reports.export-evaluations'));
    }

    public function test_the_coordinator_sees_every_programs_evaluations(): void
    {
        $otherProgram = \App\Models\Program::where('campus_id', $this->campus->id)->where('code', 'BSHM')->firstOrFail();
        Instructor::firstOrCreate(['user_id' => $this->coordinatorUser->id], ['campus_id' => $this->campus->id, 'program_id' => $otherProgram->id]);
        $form13 = $this->hteSubmits('EVALUATION_FORM');
        $ownProgram = $this->studentUser->student->program_id;

        $this->actingAs($this->coordinatorUser)->get(route('instructor.dashboard'));   // clears the HTE's "submitted" message
        $this->actingAs($this->coordinatorUser)->get(route('instructor.evaluations'))->assertOk()
            ->assertSee('name="program_id"', false)->assertSee($this->studentUser->name)->assertSee($this->hte->name);
        $this->actingAs($this->coordinatorUser)->get(route('instructor.evaluations.show', $form13))->assertOk();

        // ... but evaluates (Form 23a) only the agencies of the program they handle (BSHM here).
        $this->actingAs($this->coordinatorUser)->get(route('instructor.evaluations'))
            ->assertSee('View only &middot; not your program', false)
            ->assertDontSee(route('instructor.agency-evaluations.create', $this->hte), false);
        $this->actingAs($this->coordinatorUser)->get(route('instructor.agency-evaluations.create', $this->hte))->assertForbidden();

        // The program filter narrows it.
        $this->actingAs($this->coordinatorUser)->get(route('instructor.evaluations', ['program_id' => $ownProgram]))->assertSee($this->studentUser->name);
        $this->actingAs($this->coordinatorUser)->get(route('instructor.evaluations', ['program_id' => $otherProgram->id]))->assertDontSee($this->studentUser->name);

        // The notification still goes to the intern's own instructor only.
        $this->assertSame(0, Notification::where('user_id', $this->coordinatorUser->id)->count());

        // A subject instructor of another program still sees none of it.
        $role = \App\Models\Role::where('code', 'instructor')->firstOrFail();
        $hmUser = \App\Models\User::forceCreate(['campus_id' => $this->campus->id, 'role_id' => $role->id, 'id_number' => 'ACC-INST-099', 'name' => 'HM Instructor',
            'email' => 'hm99@t.local', 'password' => \Illuminate\Support\Facades\Hash::make('x'), 'status' => 'active', 'must_change_password' => false]);
        Instructor::forceCreate(['user_id' => $hmUser->id, 'campus_id' => $this->campus->id, 'program_id' => $otherProgram->id]);
        $this->actingAs($hmUser)->get(route('instructor.evaluations'))->assertOk()->assertDontSee($this->studentUser->name)->assertDontSee('name="program_id"', false);
        $this->actingAs($hmUser)->get(route('instructor.evaluations.show', $form13))->assertForbidden();
        $this->actingAs($hmUser)->get(route('instructor.agency-evaluations.create', $this->hte))->assertForbidden();
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

    // ------------------------------------------------ printable layouts (match the official 2024 forms)

    public function test_form_13_printout_follows_the_official_layout(): void
    {
        $requirement = $this->req('EVALUATION_FORM');
        $payload = $this->payload($requirement) + [];
        $payload['rating_a_ability_to_learn'] = 5;
        $payload['termination_company_rules'] = '1';
        $payload['future_employment'] = '1';
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
        $document = $this->hteRatesItself();

        $html = $this->actingAs($this->instructorUser)->get(route('instructor.evaluations.show', $document))->assertOk()->getContent();

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

    public function test_the_hte_enters_no_ojt_grade_on_form_13(): void
    {
        $requirement = $this->req('EVALUATION_FORM');
        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$requirement, $this->deployment]))->assertOk()
            ->assertDontSee('name="ojt_grade"', false)->assertDontSee('Calculated OJT grade')
            // Each competency's remarks box sits in its rating card, named after the competency.
            ->assertSee('Remarks on Ability To Learn', false)->assertSee('name="remarks_ability_to_learn"', false)
            ->assertDontSee('Competency Remarks');
        $this->actingAs($this->hteUser)->post(route('hte.forms.store', [$requirement, $this->deployment]),
            ['remarks_ability_to_learn' => 'Learns fast'] + $this->payload($requirement))
            ->assertSessionHasNoErrors();
        $document = \App\Models\Document::where('document_requirement_id', $requirement->id)->latest('id')->firstOrFail();
        $this->assertSame('Learns fast', $document->fieldValue('remarks_ability_to_learn'));
    }

    public function test_hte_can_edit_a_submitted_form_without_a_second_copy(): void
    {
        $this->deployment->update(['end_date' => now()->toDateString()]);   // ended today: still in the editing window
        $this->hteSubmits('TRAINING_SUPERVISOR_FEEDBACK');
        $requirement = $this->req('TRAINING_SUPERVISOR_FEEDBACK');

        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$requirement, $this->deployment]))->assertOk()->assertSee('Save changes');
        $this->actingAs($this->hteUser)->post(route('hte.forms.store', [$requirement, $this->deployment]), $this->payload($requirement, 2))
            ->assertRedirect(route('hte.evaluations'))->assertSessionHas('status', fn ($m) => str_contains($m, 'updated'));
        $this->assertSame(1, Document::where('document_requirement_id', $requirement->id)->where('internship_id', $this->deployment->internship_id)->count());
        $this->assertSame(2, Notification::where('user_id', $this->instructorUser->id)->count());
        $this->assertTrue(Notification::where('user_id', $this->instructorUser->id)->where('title', 'like', 'Form 21 updated by%')->exists());
    }

    public function test_hte_interns_page_shows_time_frame_hours_and_form_13_21_status(): void
    {
        \App\Models\Dtr::forceCreate(['deployment_id' => $this->deployment->id, 'log_date' => '2026-09-02', 'hours_logged' => 8, 'status' => 'approved']);
        \App\Models\Dtr::forceCreate(['deployment_id' => $this->deployment->id, 'log_date' => '2026-09-03', 'hours_logged' => 6.5, 'status' => 'approved']);

        $this->actingAs($this->hteUser)->get(route('hte.students'))->assertOk()
            ->assertSee('14.5h')->assertSee('Not Yet Submitted')->assertSee('Interns assigned');

        $this->hteSubmits('EVALUATION_FORM');
        $this->actingAs($this->hteUser)->get(route('hte.students'))->assertOk()->assertSee('1 of 2 submitted');

        $this->hteSubmits('TRAINING_SUPERVISOR_FEEDBACK');
        $this->actingAs($this->hteUser)->get(route('hte.students'))->assertOk()
            ->assertSee('Submitted')->assertDontSee('Not Yet Submitted')->assertDontSee('of 2 submitted');
    }

    public function test_editing_closes_at_the_deadline_or_when_the_instructor_locks_it(): void
    {
        $this->deployment->update(['end_date' => now()->toDateString()]);
        $requirement = $this->req('TRAINING_SUPERVISOR_FEEDBACK');
        $form21 = $this->hteSubmits('TRAINING_SUPERVISOR_FEEDBACK');
        $until = now()->addDays(7)->format('M j, Y');

        // Open: the HTE sees until when it can edit.
        $this->actingAs($this->hteUser)->get(route('hte.evaluations'))->assertOk()->assertSee('You can edit until '.$until);
        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$requirement, $this->deployment]))->assertOk();

        // The instructor locks it: the HTE can no longer edit.
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations'))->assertOk()
            ->assertSee(route('instructor.evaluations.lock', $form21), false);
        $this->actingAs($this->instructorUser)->post(route('instructor.evaluations.lock', $form21))->assertRedirect();
        $this->assertNotNull($form21->fresh()->locked_at);
        $this->actingAs($this->hteUser)->get(route('hte.evaluations'))->assertOk()->assertSee('Locked by the Subject Instructor');
        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$requirement, $this->deployment]))->assertForbidden();
        $this->actingAs($this->hteUser)->post(route('hte.forms.store', [$requirement, $this->deployment]), $this->payload($requirement, 2))->assertForbidden();

        // Another program's instructor cannot lock or unlock it.
        $this->actingAs($this->otherProgramInstructorUser)->post(route('instructor.evaluations.unlock', $form21))->assertForbidden();

        // Unlocked for a correction: editable again.
        $this->actingAs($this->instructorUser)->post(route('instructor.evaluations.unlock', $form21))->assertRedirect();
        $this->assertNull($form21->fresh()->locked_at);
        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$requirement, $this->deployment]))->assertOk();

        // The deadline: 7 days after the internship ended (the campus can change the number of days).
        $this->travel(9)->days();
        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$requirement, $this->deployment]))->assertForbidden();
        $this->actingAs($this->hteUser)->get(route('hte.evaluations'))->assertOk()->assertSee('Editing closed');
        // The program's Subject Instructor sets the deadline (here: 30 days).
        $this->actingAs($this->instructorUser)->get(route('instructor.evaluations'))->assertOk()->assertSee('Editing deadline');
        $this->actingAs($this->instructorUser)->put(route('instructor.evaluations.edit-days'), ['evaluation_edit_days' => 30])->assertRedirect();
        $this->assertSame(30, (int) $this->program->fresh()->evaluation_edit_days);
        $this->actingAs($this->hteUser)->get(route('hte.forms.create', [$requirement, $this->deployment]))->assertOk();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('campuses', 'evaluation_edit_days'));

        // The coordinator sets it for their own program only; another program's stays as its instructor set it.
        $coordProgram = \App\Models\Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSTM', 'name' => 'BS Tourism Management', 'required_hours' => 486]);
        \App\Models\Instructor::forceCreate(['user_id' => $this->coordinatorUser->id, 'campus_id' => $this->campus->id, 'program_id' => $coordProgram->id]);
        $this->actingAs($this->coordinatorUser)->get(route('instructor.evaluations'))->assertOk()
            ->assertSee('Editing deadline &middot; BSTM', false)->assertSee('Your program');
        $this->actingAs($this->coordinatorUser)->put(route('instructor.evaluations.edit-days'), ['evaluation_edit_days' => 3, 'program_id' => $this->program->id])->assertRedirect();
        $this->assertSame(3, (int) $coordProgram->fresh()->evaluation_edit_days);
        $this->assertSame(30, (int) $this->program->fresh()->evaluation_edit_days);
    }

    public function test_the_students_evaluation_closes_the_same_way(): void
    {
        $this->deployment->update(['end_date' => now()->subDays(10)->toDateString()]);   // past the 7 days
        $this->studentSubmits('STUDENT_HTE_EVALUATION');
        $requirement = $this->req('STUDENT_HTE_EVALUATION');

        $this->actingAs($this->studentUser)->get(route('student.hte'))->assertOk()->assertSee('Locked')->assertSee('Editing closed');
        $this->actingAs($this->studentUser)->get(route('student.documents.fill', $requirement))->assertForbidden();
        $this->actingAs($this->studentUser)->post(route('student.documents.fill.store', $requirement), $this->payload($requirement, 2))->assertForbidden();
    }

    // ------------------------------------------------ viewing your own submitted evaluation

    public function test_the_hte_can_view_the_evaluations_it_filled(): void
    {
        $form13 = $this->hteSubmits('EVALUATION_FORM');
        $form23b = $this->hteRatesItself();

        $this->actingAs($this->hteUser)->get(route('hte.evaluations'))->assertOk()
            ->assertSee(route('hte.evaluations.view', $form13), false)
            ->assertSee(route('hte.evaluations.view', $form23b), false);
        $this->actingAs($this->hteUser)->get(route('hte.evaluations.view', $form13))->assertOk()->assertSee($this->studentUser->name);
        $this->actingAs($this->hteUser)->get(route('hte.evaluations.view', $form23b))->assertOk();

        // another agency can't, and the HTE can't open the student's evaluations
        $this->actingAs($this->otherHteUser)->get(route('hte.evaluations.view', $form13))->assertNotFound();
        $form23c = $this->studentSubmits('STUDENT_HTE_EVALUATION');
        $this->actingAs($this->hteUser)->get(route('hte.evaluations.view', $form23c))->assertNotFound();
    }

    public function test_the_student_can_view_the_evaluations_they_filled(): void
    {
        $form23c = $this->studentSubmits('STUDENT_HTE_EVALUATION');

        $this->actingAs($this->studentUser)->get(route('student.hte'))->assertOk()
            ->assertSee(route('student.evaluations.view', $form23c), false);
        $this->actingAs($this->studentUser)->get(route('student.evaluations.view', $form23c))->assertOk();

        // another student can't, and the student can't open the HTE's evaluation of them
        $this->actingAs($this->otherStudentUser)->get(route('student.evaluations.view', $form23c))->assertNotFound();
        $form13 = $this->hteSubmits('EVALUATION_FORM');
        $this->actingAs($this->studentUser)->get(route('student.evaluations.view', $form13))->assertNotFound();
    }
}
