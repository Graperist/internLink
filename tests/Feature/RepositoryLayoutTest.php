<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentRequirement;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Digital Repository uses the Document Review layout: a student list,
 * then one student's page with their passed files and the open file.
 */
class RepositoryLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $coordinator;

    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        foreach (['coordinator', 'student'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $this->campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $this->coordinator = $this->user('coordinator', 'ACC-COORD-001', 'Mark Bristol', $this->campus);
    }

    private function user(string $role, string $id, string $name, Campus $campus): User
    {
        return User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $this->roles[$role]->id, 'id_number' => $id, 'name' => $name,
            'email' => strtolower($id).'@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
    }

    private function requirement(string $code, string $name, string $number, string $stage = 'pre_deployment', ?Campus $campus = null): DocumentRequirement
    {
        return DocumentRequirement::forceCreate([
            'campus_id' => ($campus ?? $this->campus)->id, 'code' => $code, 'name' => $name, 'form_number' => $number,
            'stage' => $stage, 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => (int) $number,
        ]);
    }

    private function student(string $id, string $name, ?Campus $campus = null): Student
    {
        $campus ??= $this->campus;
        $program = Program::firstOrCreate(['campus_id' => $campus->id, 'code' => 'BSIT'], ['name' => 'BSIT', 'required_hours' => 486]);
        $student = Student::forceCreate(['user_id' => $this->user('student', $id, $name, $campus)->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);

        return $student->fresh();
    }

    private function passed(Student $student, DocumentRequirement $requirement, string $file): Document
    {
        Storage::put("files/{$file}", 'pdf');

        return Document::forceCreate([
            'internship_id' => $student->currentInternship->id, 'document_requirement_id' => $requirement->id, 'status' => 'approved',
            'file_path' => "files/{$file}", 'original_filename' => $file, 'submitted_at' => now(),
        ]);
    }

    public function test_the_list_shows_students_with_an_open_link(): void
    {
        $form1 = $this->requirement('APPLICATION_FOR_OJT', 'Form 1 – Application for Internship', '1');
        $jasmine = $this->student('23-AC-0093', 'Jasmine Bejerano');
        $this->student('23-AC-0094', 'Lucy Ro');
        $this->passed($jasmine, $form1, 'form1.pdf');

        $this->actingAs($this->coordinator)->get(route('coordinator.repository'))->assertOk()
            ->assertSee('Jasmine Bejerano')->assertSee('Lucy Ro')
            ->assertSee('1 file')->assertSee('No passed files yet')
            ->assertSee(route('coordinator.repository', ['student' => $jasmine->id]), false)
            // the per-file dropdown table is gone from the list
            ->assertDontSee('form1.pdf');
    }

    public function test_the_student_page_lists_passed_files_and_opens_one(): void
    {
        $form1 = $this->requirement('APPLICATION_FOR_OJT', 'Form 1 – Application for Internship', '1');
        $form11 = $this->requirement('OJT_RESUME', 'Form 11 – Internship Resume', '11');
        $form2 = $this->requirement('CERTIFICATE_OF_UNITS_EARNED', 'Form 2 – Certification of Units Earned', '2');
        $notPassed = $this->requirement('PARENT_CONSENT', 'Form 3 – Consent and Waiver', '3');
        $student = $this->student('23-AC-0093', 'Jasmine Bejerano');

        $doc1 = $this->passed($student, $form1, 'form1.pdf');
        Storage::put('files/med.pdf', 'pdf');
        $attachment = DocumentAttachment::forceCreate(['document_id' => $doc1->id, 'kind' => 'medical_certificate', 'file_path' => 'files/med.pdf', 'original_filename' => 'med.pdf']);
        $doc11 = $this->passed($student, $form11, 'resume.pdf');
        $this->passed($student, $form2, 'units.pdf');
        Document::forceCreate(['internship_id' => $student->currentInternship->id, 'document_requirement_id' => $notPassed->id, 'status' => 'submitted']);

        $response = $this->actingAs($this->coordinator)->get(route('coordinator.repository', ['student' => $student->id]))->assertOk();
        $response->assertSeeInOrder(['Form 1 – Application', 'Medical Certificate', 'Form 2 – Certification', 'Form 11 – Internship Resume'])
            ->assertDontSee('Form 3 – Consent')
            ->assertSee('Download all (ZIP)')
            // the first file is open by default
            ->assertSee('<iframe src="'.route('coordinator.repository.view', $doc1).'"', false);

        $this->actingAs($this->coordinator)->get(route('coordinator.repository', ['student' => $student->id, 'file' => 'd'.$doc11->id]))
            ->assertSee('<iframe src="'.route('coordinator.repository.view', $doc11).'"', false);
        $this->actingAs($this->coordinator)->get(route('coordinator.repository', ['student' => $student->id, 'file' => 'a'.$attachment->id]))
            ->assertSee('Attached to Form 1 – Application for Internship')
            ->assertSee('<iframe src="'.route('coordinator.repository.attachment', $attachment).'"', false);

        $this->actingAs($this->coordinator)->get(route('coordinator.repository.view', $doc1))->assertOk();
        $this->actingAs($this->coordinator)->get(route('coordinator.repository.attachment', $attachment))->assertOk();
        $this->actingAs($this->coordinator)->get(route('coordinator.repository.attachment', ['attachment' => $attachment, 'download' => 1]))
            ->assertDownload('med.pdf');
    }

    public function test_previous_and_next_move_through_students(): void
    {
        $a = $this->student('23-AC-0001', 'Ana Alpha');
        $b = $this->student('23-AC-0002', 'Ben Beta');
        $c = $this->student('23-AC-0003', 'Cara Gamma');

        $this->actingAs($this->coordinator)->get(route('coordinator.repository', ['student' => $b->id]))->assertOk()
            ->assertSee('Student 2 of 3')
            ->assertSee(route('coordinator.repository', ['student' => $a->id]), false)
            ->assertSee(route('coordinator.repository', ['student' => $c->id]), false)
            ->assertSee('has no passed files yet');
    }

    public function test_other_campuses_are_not_reachable(): void
    {
        $other = Campus::forceCreate(['code' => 'BC', 'name' => 'PSU - Bayambang', 'status' => 'active']);
        $outsider = $this->student('23-BC-0001', 'Outsider', $other);
        $doc = $this->passed($outsider, $this->requirement('APPLICATION_FOR_OJT', 'Form 1', '1', 'pre_deployment', $other), 'x.pdf');

        $this->actingAs($this->coordinator)->get(route('coordinator.repository', ['student' => $outsider->id]))->assertNotFound();
        $this->actingAs($this->coordinator)->get(route('coordinator.repository.view', $doc))->assertForbidden();
    }
}
