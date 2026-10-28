<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentFieldValue;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The OJT Coordinator is also a program instructor and uses the same
 * portal modules: campus-wide VIEW, own-program ACTIONS. Interns are added
 * by instructors; deployments come from the accepted Application for OJT.
 */
class SharedPortalTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Campus $otherCampus;

    private Program $bsit;

    private Program $bshm;

    private Hte $hte;

    private DocumentRequirement $application;

    private DocumentRequirement $medical;

    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['coordinator', 'instructor', 'hte_representative', 'student'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }

        $this->campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $this->otherCampus = Campus::forceCreate(['code' => 'BC', 'name' => 'PSU - Bayambang Campus', 'status' => 'active']);
        $this->bsit = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology', 'required_hours' => 486]);
        $this->bshm = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSHM', 'name' => 'Bachelor of Science in Hospitality Management', 'required_hours' => 600]);
        $this->hte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Hundred Islands Resort']);

        $this->application = DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'APPLICATION_FOR_OJT', 'name' => 'Form 1 - Application',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'print_sign',
            'is_active' => true, 'is_required' => true, 'display_order' => 1,
        ]);
        $this->medical = DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'MEDICAL_CERTIFICATE', 'name' => 'Medical Certificate',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'upload',
            'is_active' => true, 'is_required' => true, 'display_order' => 2,
        ]);
    }

    private function user(string $role, string $id, ?Campus $campus = null): User
    {
        return User::forceCreate([
            'campus_id' => ($campus ?? $this->campus)->id,
            'role_id' => $this->roles[$role]->id,
            'id_number' => $id,
            'name' => "{$role} {$id}",
            'email' => strtolower($id).'@t.local',
            'password' => Hash::make('x'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
    }

    private function teacher(string $role, string $id, ?Program $program): User
    {
        $user = $this->user($role, $id);
        Instructor::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $program?->id]);

        return $user->fresh();
    }

    private function student(string $id, Program $program): Student
    {
        $user = $this->user('student', $id);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        Internship::forceCreate([
            'student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id,
            'required_hours' => $program->required_hours, 'status' => 'pre_deployment',
        ]);

        return $student->fresh();
    }

    private function submittedApplication(Student $student, string $hteName): Document
    {
        $document = Document::forceCreate([
            'internship_id' => $student->currentInternship->id,
            'document_requirement_id' => $this->application->id,
            'uploaded_by_user_id' => $student->user_id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        DocumentFieldValue::forceCreate(['document_id' => $document->id, 'field_key' => 'hte_name', 'value' => $hteName]);

        return $document;
    }

    // ---------- one portal, different scope ----------

    public function test_the_coordinators_document_review_lists_only_their_own_program(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $mine = $this->student('23-AC-0001', $this->bsit);
        $other = $this->student('23-AC-0002', $this->bshm);
        $this->submittedApplication($other, 'Hundred Islands Resort');

        $this->actingAs($coordinator)->get(route('instructor.documents'))->assertOk()
            ->assertSee('student 23-AC-0001')->assertDontSee('student 23-AC-0002')
            ->assertDontSee('All programs');

        // The Repository still lists every program; each student's files open from Files.
        // Their forms page (view only) is still reachable by its link.
        $this->actingAs($coordinator)->get(route('coordinator.documents'))->assertOk()
            ->assertSee('student 23-AC-0002')
            ->assertSee(route('coordinator.repository', ['student' => $other->id]), false);
        $html = $this->actingAs($coordinator)->get(route('coordinator.documents.student', $other))->assertOk()
            ->assertSee('student 23-AC-0002')->assertSee('Form 1 - Application')->assertDontSee('Student 1 of')
            ->assertDontSee('Return to student with remarks')
            ->assertSee(e(route('coordinator.documents.student', ['student' => $other->id, 'form' => $this->application->id])), false)
            ->getContent();
        // The sidebar keeps Document Tracking highlighted, not Document Review.
        $this->assertMatchesRegularExpression('#il-nav-link active">\s*<i class="ti ti-folder"></i> Repository#', $html);
        $this->actingAs($coordinator)->get(route('coordinator.documents.student', ['student' => $other->id, 'form' => $this->application->id]))->assertOk();
        // Still reachable the old way (bookmarks), view only.
        $this->actingAs($coordinator)->get(route('instructor.documents', ['student' => $other->id]))->assertOk()->assertSee('<i class="ti ti-arrow-left"></i> Repository', false);
        // Instructors have no Document Tracking.
        $this->actingAs($this->teacher('instructor', 'ACC-INST-009', $this->bshm))->get(route('coordinator.documents.student', $other))->assertForbidden();
        $this->actingAs($coordinator)->get(route('instructor.documents', ['student' => $mine->id]))->assertOk()
            ->assertSee('All students')->assertSee('Student 1 of 1');

        // Not the coordinator: another program's student is not found.
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $this->actingAs($instructor)->get(route('instructor.documents', ['student' => $other->id]))->assertNotFound();
    }

    public function test_document_tracking_opens_a_student_even_when_the_coordinators_program_has_none(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bshm);   // no BSHM students
        $other = $this->student('23-AC-0005', $this->bsit);
        $this->submittedApplication($other, 'Hundred Islands Resort');

        $this->actingAs($coordinator)->get(route('coordinator.documents.student', $other))->assertOk()
            ->assertSee('student 23-AC-0005')->assertSee('Form 1 - Application')->assertSee('View only')
            ->assertDontSee('No students are on your campus yet.');
    }

    public function test_the_coordinators_time_frame_keeps_their_program_apart_from_the_others(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        foreach ([['23-AC-0001', $this->bsit], ['23-AC-0002', $this->bshm]] as [$id, $program]) {
            $student = $this->student($id, $program);
            Deployment::forceCreate(['internship_id' => $student->currentInternship->id, 'hte_id' => $this->hte->id, 'status' => 'active', 'start_date' => now()->subDays(5)]);
        }
        $this->student('23-AC-0003', $this->bshm);   // not deployed

        $this->actingAs($coordinator)->get(route('instructor.monitoring'))->assertOk()
            ->assertSeeInOrder(['BSIT', 'Your program', 'student 23-AC-0001', 'BSHM', '1 deployed · 1 not yet', 'student 23-AC-0002', 'Not deployed yet (1)', 'student 23-AC-0003'])
            ->assertSee('id="tfProgram'.$this->bsit->id.'" class="collapse show"', false)    // own program open
            ->assertSee('id="tfProgram'.$this->bshm->id.'" class="collapse "', false);       // others closed
        // Opening another program's intern keeps that program open.
        $other = Student::whereHas('user', fn ($q) => $q->where('id_number', '23-AC-0002'))->first();
        $this->actingAs($coordinator)->get(route('instructor.monitoring', ['student' => $other->id]))->assertOk()
            ->assertSee('id="tfProgram'.$this->bshm->id.'" class="collapse show"', false);

        // An instructor keeps the single list.
        $instructor = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $this->actingAs($instructor)->get(route('instructor.monitoring'))->assertOk()
            ->assertSee('Deployed interns')->assertDontSee('data-bs-toggle="collapse"', false)->assertDontSee('student 23-AC-0001');
    }

    public function test_opening_an_update_removes_new_and_all_can_be_marked_read(): void
    {
        $student = $this->student('23-AC-0001', $this->bsit);
        $make = fn ($title) => \App\Models\Notification::create(['user_id' => $student->user_id, 'type' => 'document_status', 'title' => $title,
            'message' => 'x', 'url' => route('student.documents', [], false), 'resolve_key' => 'k:'.$title]);
        $one = $make('First update');
        $make('Second update');

        $this->actingAs($student->user)->get(route('student.announcements', ['tab' => 'updates']))->assertOk()
            ->assertSee('id="unreadUpdatesCount">2<', false)->assertSee('Mark all as read')->assertSee('upd-new', false);

        // Clicking one: it is read, "New" is gone for it.
        $this->actingAs($student->user)->get(route('notifications.open', $one))->assertRedirect();
        $this->assertNotNull($one->fresh()->read_at);
        $this->actingAs($student->user)->get(route('student.announcements', ['tab' => 'updates']))->assertOk()
            ->assertSee('id="unreadUpdatesCount">1<', false);

        // Mark all as read.
        $this->actingAs($student->user)->post(route('student.announcements.read-all'))->assertRedirect(route('student.announcements', ['tab' => 'updates']));
        $this->actingAs($student->user)->get(route('student.announcements', ['tab' => 'updates']))->assertOk()
            ->assertDontSee('id="unreadUpdatesCount"', false)->assertDontSee('Mark all as read<', false)->assertDontSee('text-bg-primary upd-new', false);
    }

    public function test_the_coordinators_dashboard_counts_only_their_own_programs_documents_to_review(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $this->submittedApplication($this->student('23-AC-0001', $this->bshm), 'Hundred Islands Resort');   // another program
        $this->submittedApplication($this->student('23-AC-0002', $this->bshm), 'Hundred Islands Resort');

        $this->actingAs($coordinator)->get(route('instructor.dashboard'))->assertOk()
            ->assertSee('You have 0 documents pending review')
            ->assertSee('Docs For Review · BSIT')->assertSee('Nothing waiting on you right now.')
            ->assertDontSee('student 23-AC-0001')
            ->assertSee(e(route('instructor.documents', ['program_id' => $this->bsit->id, 'tab' => 'review'])), false);

        $this->submittedApplication($this->student('23-AC-0003', $this->bsit), 'Hundred Islands Resort');     // their program
        $this->actingAs($coordinator)->get(route('instructor.dashboard'))->assertOk()
            ->assertSee('You have 1 document pending review')->assertSee('student 23-AC-0003');

        // Student counts stay campus-wide.
        $this->actingAs($coordinator)->get(route('instructor.dashboard'))->assertSee('Campus Students');
    }


    public function test_coordinator_lands_on_the_shared_portal_with_campus_admin_menu(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bshm);

        $this->assertSame('instructor.dashboard', $coordinator->dashboardRouteName());

        $this->actingAs($coordinator)->get(route('instructor.dashboard'))->assertOk()
            ->assertSee('Coordinator Portal')->assertSee('Campus Administration')->assertSee('Programs');

        $this->actingAs($instructor)->get(route('instructor.dashboard'))->assertOk()
            ->assertSee('Instructor Portal')->assertDontSee('Campus Administration');

        $this->actingAs($instructor)->get(route('coordinator.programs.index'))->assertForbidden();
    }

    public function test_coordinator_without_an_instructor_record_gets_one_automatically(): void
    {
        $coordinator = $this->user('coordinator', 'ACC-COORD-001');

        $this->actingAs($coordinator)->get(route('instructor.advisees'))->assertOk()
            ->assertSee('set your program in My Profile');

        $this->assertDatabaseHas('instructors', ['user_id' => $coordinator->id, 'program_id' => null]);
    }

    public function test_coordinator_sees_the_whole_campus_instructor_only_their_program(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bshm);
        $itStudent = $this->student('23-AC-0001', $this->bsit);
        $hmStudent = $this->student('23-AC-0002', $this->bshm);

        $this->actingAs($coordinator)->get(route('instructor.advisees'))->assertOk()
            ->assertSee('23-AC-0001')->assertSee('23-AC-0002');

        $this->actingAs($instructor)->get(route('instructor.advisees'))->assertOk()
            ->assertDontSee('23-AC-0001')->assertSee('23-AC-0002');

        // Coordinator's list has a Program column, with other programs marked view only.
        $this->actingAs($coordinator)->get(route('instructor.advisees'))
            ->assertSee('<th>Program</th>', false)
            ->assertSee('Your program')
            ->assertSee('BSHM instructor handles this student');
        $this->actingAs($instructor)->get(route('instructor.advisees'))->assertDontSee('<th>Program</th>', false);

        // Coordinator can filter the campus list by program.
        $this->actingAs($coordinator)->get(route('instructor.advisees', ['program_id' => $this->bshm->id]))
            ->assertDontSee('23-AC-0001')->assertSee('23-AC-0002');

        $this->assertTrue($coordinator->instructor->canView($hmStudent));
        $this->assertFalse($instructor->instructor->canView($itStudent));
    }

    public function test_coordinator_views_other_programs_read_only_and_acts_on_own_program(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $this->teacher('instructor', 'ACC-INST-001', $this->bshm);
        $itStudent = $this->student('23-AC-0001', $this->bsit);
        $hmStudent = $this->student('23-AC-0002', $this->bshm);
        $hmDoc = $this->submittedApplication($hmStudent, $this->hte->name);
        $itDoc = $this->submittedApplication($itStudent, $this->hte->name);

        // Other program: page opens, read-only notice, no action.
        $this->actingAs($coordinator)->get(route('instructor.documents', ['student' => $hmStudent->id]))
            ->assertOk()->assertSee('View only');
        $this->actingAs($coordinator)->post(route('instructor.documents.print', $hmDoc))->assertForbidden();
        $this->actingAs($coordinator)->get(route('instructor.documents.printable', $hmDoc))->assertOk()
            ->assertDontSee('onload="window.print()"', false);

        // Own program: can act like any instructor.
        $this->actingAs($coordinator)->get(route('instructor.documents', ['student' => $itStudent->id]))
            ->assertOk()->assertDontSee('View only');
        $this->actingAs($coordinator)->post(route('instructor.documents.print', $itDoc))->assertRedirect();
        $this->assertSame('printed', $itDoc->fresh()->status);
    }

    public function test_instructor_cannot_see_another_programs_documents(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bshm);
        $itStudent = $this->student('23-AC-0001', $this->bsit);
        $itDoc = $this->submittedApplication($itStudent, $this->hte->name);

        $this->actingAs($instructor)->get(route('instructor.documents.printable', $itDoc))->assertForbidden();
    }

    // ---------- interns are added by instructors ----------

    public function test_instructor_adds_an_intern_into_their_own_program(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bshm);

        $this->actingAs($instructor)->post(route('instructor.interns.store'), [
            'id_number' => '23-AC-0100', 'name' => 'Ana Cruz', 'email' => 'ana@t.local',
            // a program_id in the request is ignored - it is always the instructor's
            'program_id' => $this->bsit->id,
        ])->assertRedirect(route('instructor.advisees'))->assertSessionHas('provisioned_account');

        $student = User::where('id_number', '23-AC-0100')->firstOrFail()->student;
        $this->assertSame($this->bshm->id, $student->program_id);
        $this->assertSame('pre_deployment', $student->currentInternship->status);
        $this->assertNull($student->currentInternship->deployment);
    }

    public function test_coordinator_adds_interns_to_any_program(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $hmInstructor = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);

        $this->actingAs($coordinator)->get(route('instructor.interns.create'))->assertOk()
            ->assertSee('name="program_id"', false)->assertSee('BSHM');

        // Into another program: placed under that program's instructor.
        $this->actingAs($coordinator)->post(route('instructor.interns.store'), [
            'id_number' => '23-AC-0101', 'name' => 'Ben Uy', 'email' => 'ben@t.local', 'program_id' => $this->bshm->id,
        ])->assertRedirect()->assertSessionHas('provisioned_account');
        $student = User::where('id_number', '23-AC-0101')->firstOrFail()->student;
        $this->assertSame($this->bshm->id, $student->program_id);
        $this->assertSame($hmInstructor->instructor->id, $student->instructor_id);
        $this->assertSame($this->bshm->id, $student->currentInternship->program_id);

        // Into their own program: stays with the coordinator.
        $this->actingAs($coordinator)->post(route('instructor.interns.store'), [
            'id_number' => '23-AC-0102', 'name' => 'Ana Uy', 'email' => 'ana@t.local', 'program_id' => $this->bsit->id,
        ])->assertRedirect();
        $this->assertSame($coordinator->instructor->id, User::where('id_number', '23-AC-0102')->firstOrFail()->student->instructor_id);

        // The program has to be chosen.
        $this->actingAs($coordinator)->post(route('instructor.interns.store'), [
            'id_number' => '23-AC-0103', 'name' => 'No Program', 'email' => 'np@t.local',
        ])->assertSessionHasErrors('program_id');

        // An instructor can't add to another program (the request's program is ignored).
        $this->actingAs($hmInstructor)->post(route('instructor.interns.store'), [
            'id_number' => '23-AC-0104', 'name' => 'Cy Uy', 'email' => 'cy@t.local', 'program_id' => $this->bsit->id,
        ])->assertRedirect();
        $this->assertSame($this->bshm->id, User::where('id_number', '23-AC-0104')->firstOrFail()->student->program_id);

        // A coordinator without a program of their own can still add interns.
        $noProgram = $this->teacher('coordinator', 'ACC-COORD-002', null);
        $this->actingAs($noProgram)->get(route('instructor.interns.create'))->assertOk();
    }

    public function test_coordinator_imports_interns_into_any_program(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $import = new \App\Imports\StudentsImport($coordinator->instructor, $this->bshm);
        $import->collection(collect([
            ['id_number' => '23-AC-0201', 'name' => 'A', 'email' => 'a201@t.local', 'program_code' => 'BSIT'],
            ['id_number' => '23-AC-0202', 'name' => 'B', 'email' => 'b202@t.local', 'program_code' => ''],
            ['id_number' => '23-AC-0203', 'name' => 'C', 'email' => 'c203@t.local', 'program_code' => 'NOPE'],
        ]));
        $this->assertCount(2, $import->created);
        $this->assertCount(1, $import->skipped);
        $this->assertSame($this->bsit->id, User::where('id_number', '23-AC-0201')->first()->student->program_id);
        $this->assertSame($this->bshm->id, User::where('id_number', '23-AC-0202')->first()->student->program_id);
    }

    public function test_old_coordinator_intern_and_deployment_pages_are_gone(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);

        $this->actingAs($coordinator)->get('/coordinator/students')->assertRedirect('/instructor/advisees');
        $this->actingAs($coordinator)->get('/coordinator/deployments')->assertRedirect('/instructor/advisees');
        $this->actingAs($coordinator)->get('/coordinator/students/create')->assertNotFound();
        // The old URL only redirects now - posting to it creates nothing.
        $this->actingAs($coordinator)->post('/coordinator/students', [
            'id_number' => '23-AC-0999', 'name' => 'X', 'email' => 'x@t.local', 'program_id' => $this->bsit->id,
        ])->assertRedirect();
        $this->assertDatabaseMissing('users', ['id_number' => '23-AC-0999']);
    }

    public function test_instructor_can_only_reset_their_own_programs_intern_passwords(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bshm);
        $itStudent = $this->student('23-AC-0001', $this->bsit);
        $hmStudent = $this->student('23-AC-0002', $this->bshm);

        $this->actingAs($instructor)->post(route('instructor.interns.reset-password', $itStudent))->assertForbidden();
        $this->actingAs($instructor)->post(route('instructor.interns.reset-password', $hmStudent))
            ->assertSessionHas('provisioned_account');
    }

    // ---------- deployment comes from the application ----------

    public function test_accepting_the_application_creates_a_pending_deployment_at_the_chosen_hte(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $student = $this->student('23-AC-0001', $this->bsit);
        $document = $this->submittedApplication($student, $this->hte->name);

        $this->actingAs($instructor)->post(route('instructor.documents.print', $document))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Pending deployment created'));

        $deployment = $student->fresh()->currentInternship->deployment;
        $this->assertNotNull($deployment);
        $this->assertSame($this->hte->id, $deployment->hte_id);
        $this->assertSame('pending', $deployment->status);
        $this->assertNull($deployment->start_date);
    }

    public function test_a_returned_application_creates_nothing_and_a_corrected_one_moves_the_placement(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $student = $this->student('23-AC-0001', $this->bsit);
        $document = $this->submittedApplication($student, $this->hte->name);

        $this->actingAs($instructor)->post(route('instructor.documents.review', $document), [
            'decision' => 'returned', 'remarks' => 'Wrong year level',
        ]);
        $this->assertNull($student->fresh()->currentInternship->deployment);

        // Accept, then (still pending) accept a corrected one at another HTE.
        $document->refresh()->update(['status' => 'submitted']); // student resubmits
        $this->actingAs($instructor)->post(route('instructor.documents.print', $document))->assertRedirect();
        $this->assertSame(1, Deployment::count());
        $other = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Lingayen Gulf Hotel']);
        $document->fieldValues()->where('field_key', 'hte_name')->update(['value' => $other->name]);
        $document->refresh()->update(['status' => 'submitted']); // corrected and resubmitted
        $this->actingAs($instructor)->post(route('instructor.documents.print', $document))->assertRedirect();

        $this->assertSame(1, Deployment::count());
        $this->assertSame($other->id, Deployment::first()->hte_id);
    }

    public function test_an_hte_not_on_the_partner_list_creates_no_deployment(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $student = $this->student('23-AC-0001', $this->bsit);
        $document = $this->submittedApplication($student, 'Some Unknown Company');

        $this->actingAs($instructor)->post(route('instructor.documents.print', $document))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'not on the campus partner HTE list'));
        $this->assertSame(0, Deployment::count());
    }

    public function test_an_active_deployment_is_never_moved_by_the_application(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $student = $this->student('23-AC-0001', $this->bsit);
        $deployment = Deployment::forceCreate(['internship_id' => $student->currentInternship->id, 'hte_id' => $this->hte->id, 'status' => 'active']);
        $other = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Other HTE']);
        $document = $this->submittedApplication($student, $other->name);

        $this->actingAs($instructor)->post(route('instructor.documents.print', $document));

        $this->assertSame($this->hte->id, $deployment->fresh()->hte_id);
    }

    public function test_instructor_starts_the_deployment_only_after_pre_ojt_requirements_are_approved(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $student = $this->student('23-AC-0001', $this->bsit);
        $internship = $student->currentInternship;
        $deployment = Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $this->hte->id, 'status' => 'pending']);
        $this->actingAs($instructor)->post(route('instructor.advisees.activate', $deployment))
            ->assertSessionHasErrors('activation');
        $this->assertSame('pending', $deployment->fresh()->status);

        foreach ([$this->application, $this->medical] as $requirement) {
            Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $requirement->id, 'status' => 'approved']);
        }

        // No dates to enter: it starts today and runs until the required hours are reached.
        $this->actingAs($instructor)->get(route('instructor.advisees'))->assertDontSee('name="start_date"', false)->assertDontSee('name="end_date"', false);
        $this->actingAs($instructor)->post(route('instructor.advisees.activate', $deployment))
            ->assertRedirect()->assertSessionHasNoErrors();

        $deployment->refresh();
        $this->assertSame('active', $deployment->status);
        $this->assertSame(today()->toDateString(), $deployment->start_date->toDateString());
        $this->assertNull($deployment->end_date);
        $this->assertSame('deployed', $internship->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $student->user_id, 'title' => 'You are now deployed']);
    }

    public function test_coordinator_cannot_start_another_programs_deployment(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $student = $this->student('23-AC-0002', $this->bshm);
        $deployment = Deployment::forceCreate(['internship_id' => $student->currentInternship->id, 'hte_id' => $this->hte->id, 'status' => 'pending']);

        $this->actingAs($coordinator)->post(route('instructor.advisees.activate', $deployment), [
            'start_date' => '2026-11-03', 'end_date' => '2027-01-30',
        ])->assertForbidden();
    }

    // ---------- programs ----------

    public function test_coordinator_adds_a_course_with_a_major(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);

        $this->actingAs($coordinator)->post(route('coordinator.programs.store'), [
            'code' => 'bsba-mm', 'name' => 'Bachelor of Science in Business Administration',
            'major' => 'Marketing Management', 'required_hours' => 600,
        ])->assertRedirect(route('coordinator.programs.index'));

        $program = Program::where('campus_id', $this->campus->id)->where('code', 'BSBA-MM')->firstOrFail();
        $this->assertSame('Marketing Management', $program->majorLabel());
        $this->assertSame('Bachelor of Science in Business Administration', $program->courseTitle());

        // Duplicate code on the same campus is refused.
        $this->actingAs($coordinator)->post(route('coordinator.programs.store'), [
            'code' => 'BSBA-MM', 'name' => 'X', 'required_hours' => 1,
        ])->assertSessionHasErrors('code');

        $this->actingAs($coordinator)->get(route('coordinator.programs.index'))->assertOk()->assertSee('Marketing Management');
    }

    public function test_a_program_in_use_cannot_be_removed(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $this->student('23-AC-0002', $this->bshm);
        $empty = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSTM', 'name' => 'Tourism', 'required_hours' => 486]);

        $this->actingAs($coordinator)->delete(route('coordinator.programs.destroy', $this->bshm))->assertSessionHasErrors('program');
        $this->actingAs($coordinator)->delete(route('coordinator.programs.destroy', $empty))->assertRedirect();
        $this->assertDatabaseMissing('programs', ['id' => $empty->id]);
    }

    public function test_legacy_bsba_programs_still_print_the_same_course_and_major(): void
    {
        $om = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSBA-OM',
            'name' => 'Bachelor of Science in Business Administration - Operations Management', 'required_hours' => 486]);

        $this->assertSame('Bachelor of Science in Business Administration', $om->courseTitle());
        $this->assertSame('OM', $om->majorLabel());
        $this->assertSame('N/A', $this->bsit->majorLabel());
    }

    public function test_coordinator_picks_the_program_they_teach_in_my_profile(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', null);

        $this->actingAs($coordinator)->put(route('instructor.profile.account'), [
            'name' => 'Coord', 'email' => 'coord@t.local', 'program_id' => $this->bshm->id,
        ])->assertRedirect();

        $this->assertSame($this->bshm->id, $coordinator->instructor->fresh()->program_id);

        // An instructor can't change their own program.
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $this->actingAs($instructor)->put(route('instructor.profile.account'), [
            'name' => 'Inst', 'email' => 'inst@t.local', 'program_id' => $this->bshm->id,
        ])->assertSessionHasErrors('program_id');
    }

    public function test_every_shared_page_renders_for_both_roles(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bshm);
        $this->student('23-AC-0001', $this->bsit);
        $this->student('23-AC-0002', $this->bshm);

        $shared = ['instructor.dashboard', 'instructor.advisees', 'instructor.documents', 'instructor.monitoring',
            'instructor.htes', 'instructor.reports', 'instructor.announcements',
            'instructor.profile', 'instructor.interns.create', 'instructor.interns.import', 'instructor.htes.create'];

        foreach ([$coordinator, $instructor] as $user) {
            foreach ($shared as $name) {
                $this->actingAs($user)->get(route($name))->assertOk();
            }
        }
        // Every instructor has the same modules - the coordinator's program too.
        $this->actingAs($instructor)->get(route('instructor.evaluations'))->assertOk();
        $this->actingAs($coordinator)->get(route('instructor.evaluations'))->assertOk();

        foreach (['coordinator.programs.index', 'coordinator.programs.create', 'coordinator.instructors.index',
            'coordinator.hte-accounts.index', 'coordinator.documents',
            'coordinator.campus-settings.edit'] as $name) {
            $this->actingAs($coordinator)->get(route($name))->assertOk();
        }
        // Campus Exports is part of Generate Reports now.
        $this->actingAs($coordinator)->get(route('coordinator.reports'))->assertRedirect(route('instructor.reports'));
    }

    // ---------- Announcements ----------

    public function test_announcements_go_to_the_chosen_program_or_every_program(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $instructor = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $bsitIntern = $this->student('23-AC-0001', $this->bsit);
        $bshmIntern = $this->student('23-AC-0002', $this->bshm);
        $post = ['category' => 'pre_ojt_seminar', 'title' => 'Pre-OJT Seminar', 'event_date' => '2026-11-05', 'event_time' => '09:00', 'venue' => 'Gym'];
        $count = fn ($student) => \App\Models\Notification::where('type', 'announcement')->where('user_id', $student->user_id)->count();

        // The coordinator chooses: all programs, or one program.
        $this->actingAs($coordinator)->get(route('instructor.announcements'))->assertOk()
            ->assertSee('name="audience"', false)->assertSee('All programs')->assertSee('BSHM only');

        $this->actingAs($coordinator)->post(route('instructor.announcements.store'), $post + ['audience' => 'all'])
            ->assertSessionHas('status', 'Announcement sent to 2 interns of every program.');
        $this->assertSame(1, $count($bsitIntern));
        $this->assertSame(1, $count($bshmIntern));

        $this->actingAs($coordinator)->post(route('instructor.announcements.store'), $post + ['audience' => $this->bshm->id])
            ->assertSessionHas('status', 'Announcement sent to 1 BSHM intern.');
        $this->assertSame(1, $count($bsitIntern));
        $this->assertSame(2, $count($bshmIntern));
        $this->actingAs($coordinator)->get(route('instructor.announcements'))->assertSee('All programs');

        // An instructor: only their program, whatever the request says.
        $this->actingAs($instructor)->get(route('instructor.announcements'))->assertDontSee('name="audience"', false);
        $this->actingAs($instructor)->post(route('instructor.announcements.store'), ['title' => 'Reminder', 'audience' => 'all'] + $post)->assertSessionHasNoErrors();
        $this->assertSame(3, $count($bshmIntern));
        $this->assertSame(1, $count($bsitIntern));
    }

    public function test_students_read_announcements_from_their_instructor_and_the_coordinator(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $instructor = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $bshmIntern = $this->student('23-AC-0002', $this->bshm);
        $bsitIntern = $this->student('23-AC-0001', $this->bsit);
        $post = ['category' => 'pre_ojt_seminar', 'event_date' => '2026-11-05', 'event_time' => '09:00', 'venue' => 'Gym'];

        $this->actingAs($instructor)->post(route('instructor.announcements.store'), ['title' => 'BSHM orientation'] + $post);
        $this->actingAs($coordinator)->post(route('instructor.announcements.store'), ['title' => 'Campus-wide seminar', 'audience' => 'all'] + $post);
        $this->actingAs($coordinator)->post(route('instructor.announcements.store'), ['title' => 'BSIT only meeting', 'audience' => $this->bsit->id] + $post);

        $user = $bshmIntern->user;
        $this->actingAs($user)->get(route('student.dashboard'))->assertSee('Announcements')->assertDontSee('ti-bell"></i> Notifications', false);
        $this->actingAs($user)->get(route('student.announcements'))->assertOk()
            ->assertSee('BSHM orientation')->assertSee('Subject Instructor')
            ->assertSee('Campus-wide seminar')->assertSee('OJT Coordinator')->assertSee('For all programs')
            ->assertSee('Gym')->assertDontSee('BSIT only meeting');
        // Opening the page reads them; the old link still works.
        $this->assertSame(0, $user->notifications_()->where('type', 'announcement')->whereNull('read_at')->count());
        $this->actingAs($user)->get(route('student.notifications'))->assertOk()->assertSee('Announcements');
        $this->actingAs($user)->get(route('student.announcements', ['tab' => 'updates']))->assertOk()->assertDontSee('BSHM orientation');

        $this->actingAs($bsitIntern->user)->get(route('student.announcements'))->assertSee('BSIT only meeting')->assertDontSee('BSHM orientation');
    }

    public function test_staff_portals_have_no_notifications_page(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $instructor = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);

        foreach ([$coordinator, $instructor] as $user) {
            $this->actingAs($user)->get(route('instructor.dashboard'))->assertOk()
                ->assertDontSee('ti-bell"></i> Notifications', false)->assertSee('Announcements');
            $this->actingAs($user)->get('/instructor/notifications')->assertRedirect('/instructor/dashboard');
        }
        $this->actingAs($coordinator)->get('/coordinator/notifications')->assertRedirect();
    }

    // ---------- Document Review layout (Teams-style) ----------

    public function test_document_review_opens_on_the_student_list(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $waiting = $this->student('23-AC-0001', $this->bsit);
        $this->student('23-AC-0002', $this->bsit);
        $this->submittedApplication($waiting, $this->hte->name);

        $this->actingAs($instructor)->get(route('instructor.documents'))->assertOk()
            ->assertSee('Pick a student to open their forms')
            ->assertSee('23-AC-0001')->assertSee('23-AC-0002')
            ->assertSee('1 to review')
            ->assertDontSee('Medical Certificate');   // no forms on the list screen

        $this->actingAs($instructor)->get(route('instructor.documents', ['tab' => 'review']))
            ->assertSee('23-AC-0001')->assertDontSee('23-AC-0002');
    }

    public function test_clicking_a_student_shows_only_that_student_with_the_form_open(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $a = $this->student('23-AC-0001', $this->bsit);
        $b = $this->student('23-AC-0002', $this->bsit);
        $doc = $this->submittedApplication($a, $this->hte->name);

        $html = $this->actingAs($instructor)->get(route('instructor.documents', ['student' => $a->id]))->assertOk()
            ->assertSee('All students')
            ->assertSee('Student 1 of 2')
            ->assertSee('Form 1 - Application')     // in the form list
            ->assertSee('Medical Certificate')      // also listed
            ->assertSee('data-bs-target="#approveStepModal"', false)   // the waiting form is open by default, approved like every form
            ->getContent();
        // The page is about this one student.
        $this->assertMatchesRegularExpression('#<h2 class="h5 fw-bold mb-1">\s*student 23-AC-0001#', $html);
        $this->assertStringContainsString(route('instructor.documents', ['student' => $b->id]), $html); // next arrow

        // Opening another form shows that one instead.
        $this->actingAs($instructor)->get(route('instructor.documents', ['student' => $a->id, 'form' => $this->medical->id]))
            ->assertOk()->assertSee('Not yet submitted.')->assertDontSee('data-bs-target="#approveStepModal"', false);

        // After an action you come back to the same student and form.
        $this->actingAs($instructor)->post(route('instructor.documents.print', $doc))
            ->assertRedirect(route('instructor.documents', ['student' => $a->id, 'form' => $this->application->id]));
    }

    public function test_a_student_outside_your_view_is_not_found(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bshm);
        $other = $this->student('23-AC-0001', $this->bsit);

        $this->actingAs($instructor)->get(route('instructor.documents', ['student' => $other->id]))->assertNotFound();
    }

    public function test_remind_selected_students_about_missing_forms(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $mine = $this->student('23-AC-0001', $this->bsit);
        $other = $this->student('23-AC-0002', $this->bshm);

        $this->actingAs($instructor)->post(route('instructor.documents.remind'), ['student_ids' => [$mine->id, $other->id]])
            ->assertSessionHas('status', 'Reminder sent to 1 student.');

        $this->assertDatabaseHas('notifications', ['user_id' => $mine->user_id, 'title' => 'Reminder: forms to submit']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $other->user_id, 'title' => 'Reminder: forms to submit']);
    }

    // ---------- Returning forms (student page + Classroom-style viewer) ----------

    public function test_viewer_shows_the_form_with_a_return_panel(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $student = $this->student('23-AC-0001', $this->bsit);
        $doc = $this->submittedApplication($student, $this->hte->name);

        $this->actingAs($instructor)->get(route('instructor.documents.show', $doc))->assertOk()
            ->assertSee('Remarks for the student')
            ->assertSee('Return to student')
            ->assertSee('Approve for Printing')     // Form 1 is a print-and-sign form
            ->assertSee("student 23-AC-0001's forms", false);

        // Student page also offers the inline return box and the full view.
        $this->actingAs($instructor)->get(route('instructor.documents', ['student' => $student->id]))
            ->assertSee('Return to student with remarks')
            ->assertSee(route('instructor.documents.show', $doc), false);
    }

    public function test_returning_from_the_viewer_saves_remarks_and_stays_on_the_viewer(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $student = $this->student('23-AC-0001', $this->bsit);
        $doc = $this->submittedApplication($student, $this->hte->name);

        $this->actingAs($instructor)->post(route('instructor.documents.review', $doc), [
            'decision' => 'returned', 'remarks' => 'Please fill in your home address.', 'return_to' => 'viewer',
        ])->assertRedirect(route('instructor.documents.show', $doc));

        $this->assertSame('returned', $doc->fresh()->status);
        $this->assertDatabaseHas('document_reviews', ['document_id' => $doc->id, 'decision' => 'returned', 'remarks' => 'Please fill in your home address.']);
        $this->assertDatabaseHas('notifications', ['user_id' => $student->user_id]);

        $this->actingAs($instructor)->get(route('instructor.documents.show', $doc))
            ->assertSee('Previous remarks')->assertSee('Please fill in your home address.')
            ->assertDontSee('Remarks for the student');   // nothing to return any more
    }

    public function test_a_form_out_for_signing_can_still_be_returned_but_not_approved(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $student = $this->student('23-AC-0001', $this->bsit);
        $doc = $this->submittedApplication($student, $this->hte->name);
        $doc->forceFill(['status' => 'printed'])->save();

        $this->actingAs($instructor)->post(route('instructor.documents.review', $doc), ['decision' => 'approved'])->assertStatus(422);
        $this->actingAs($instructor)->post(route('instructor.documents.review', $doc), ['decision' => 'returned', 'remarks' => 'Wrong signatory'])
            ->assertRedirect();
        $this->assertSame('returned', $doc->fresh()->status);
    }

    public function test_viewer_is_view_only_for_other_programs_and_embed_has_no_print_dialog(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bshm);
        $student = $this->student('23-AC-0001', $this->bsit);
        $doc = $this->submittedApplication($student, $this->hte->name);

        $this->actingAs($coordinator)->get(route('instructor.documents.show', $doc))->assertOk()
            ->assertSee('View only')->assertDontSee('Remarks for the student');

        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $this->actingAs($instructor)->get(route('instructor.documents.printable', ['document' => $doc, 'embed' => 1]))
            ->assertOk()->assertDontSee('onload="window.print()"', false);

        $outsider = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $this->actingAs($outsider)->get(route('instructor.documents.show', $doc))->assertForbidden();
    }
}
