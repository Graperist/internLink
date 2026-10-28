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
use App\Support\Signatories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Signatory names are filled in automatically on the student forms from
 * the records: subject instructor, coordinator, department chair (program),
 * college dean / CED / registrar (Campus Settings), HTE supervisor.
 */
class FormSignatoriesTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Program $bsit;

    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['coordinator', 'instructor', 'student'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $this->campus = Campus::forceCreate([
            'code' => 'ACC', 'name' => 'Pangasinan State University - Alaminos City Campus', 'status' => 'active',
            'ced_name' => 'Dr. Liza L. Quimson', 'college_dean_name' => 'Dr. Ramon Dean', 'registrar_name' => 'Ms. Rosa Registrar',
        ]);
        $this->bsit = Program::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology',
            'required_hours' => 486, 'department_chair_name' => 'Engr. Dana Chair',
        ]);
    }

    private function user(string $role, string $id, string $name): User
    {
        return User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $this->roles[$role]->id, 'id_number' => $id, 'name' => $name,
            'email' => strtolower($id).'@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
    }

    private function internship(): Internship
    {
        $student = Student::forceCreate(['user_id' => $this->user('student', '23-AC-0099', 'Sieg Freed Tabucol')->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id, 'hours_rendered' => 0]);

        return Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id, 'required_hours' => 486, 'status' => 'pre_deployment']);
    }

    public function test_names_come_from_the_records(): void
    {
        $coordinator = $this->user('coordinator', 'ACC-COORD-001', 'Mark Harley V. Bristol');
        Instructor::forceCreate(['user_id' => $coordinator->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id]);
        $instructor = $this->user('instructor', 'ACC-INST-001', 'Prof. Ian Instructor');
        Instructor::forceCreate(['user_id' => $instructor->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id]);

        $internship = $this->internship();
        $hte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'BDO Unibank', 'contact_person' => 'Alfred Manager']);
        Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'status' => 'pending']);

        $this->assertSame([
            'instructor' => 'Prof. Ian Instructor',   // a regular instructor is preferred over the coordinator
            'coordinator' => 'Mark Harley V. Bristol',
            'department_chair' => 'Engr. Dana Chair',
            'college_dean' => 'Dr. Ramon Dean',
            'ced' => 'Dr. Liza L. Quimson',
            'registrar' => 'Ms. Rosa Registrar',
            'supervisor' => 'Alfred Manager',
        ], Signatories::for($internship->fresh()));
    }

    public function test_application_form_prints_every_signatory_name(): void
    {
        $coordinator = $this->user('coordinator', 'ACC-COORD-001', 'Mark Harley V. Bristol');
        $instructor = $this->user('instructor', 'ACC-INST-001', 'Prof. Ian Instructor');
        Instructor::forceCreate(['user_id' => $instructor->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id]);

        $internship = $this->internship();
        $requirement = DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'APPLICATION_FOR_OJT', 'name' => 'Form 1', 'stage' => 'pre_deployment',
            'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => 1,
        ]);
        $document = Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $requirement->id, 'status' => 'submitted', 'submitted_at' => now()]);
        DocumentFieldValue::forceCreate(['document_id' => $document->id, 'field_key' => 'surname', 'value' => 'TABUCOL']);

        $this->actingAs($instructor)->get(route('instructor.documents.printable', $document))->assertOk()
            ->assertSee('Prof. Ian Instructor')
            ->assertSee('Mark Harley V. Bristol')
            ->assertSee('Engr. Dana Chair')
            ->assertSee('Dr. Ramon Dean')
            ->assertSee('Dr. Liza L. Quimson')
            ->assertSee('Ms. Rosa Registrar');
    }

    public function test_coordinator_sets_the_registrar_and_department_chair(): void
    {
        $coordinator = $this->user('coordinator', 'ACC-COORD-001', 'Coord');

        $this->actingAs($coordinator)->put(route('coordinator.campus-settings.update'), [
            'ced_name' => 'Dr. CED', 'college_dean_name' => 'Dr. Dean', 'registrar_name' => 'New Registrar',
        ])->assertRedirect();
        $this->assertSame('New Registrar', $this->campus->fresh()->registrar_name);

        $this->actingAs($coordinator)->put(route('coordinator.programs.update', $this->bsit), [
            'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology', 'required_hours' => 486,
            'department_chair_name' => 'New Chair',
        ])->assertRedirect();
        $this->assertSame('New Chair', $this->bsit->fresh()->department_chair_name);
    }

    public function test_unset_names_leave_the_blank_line(): void
    {
        $this->campus->update(['registrar_name' => null, 'college_dean_name' => null]);
        $this->bsit->update(['department_chair_name' => null]);

        $sig = Signatories::for($this->internship()->fresh());

        $this->assertSame('', $sig['registrar']);
        $this->assertSame('', $sig['department_chair']);
        $this->assertSame('', $sig['instructor']);
    }

    public function test_consent_form_prints_its_signatories(): void
    {
        $coordinator = $this->user('coordinator', 'ACC-COORD-001', 'Mark Harley V. Bristol');
        $instructor = $this->user('instructor', 'ACC-INST-001', 'Prof. Ian Instructor');
        Instructor::forceCreate(['user_id' => $instructor->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id]);
        $this->campus->update(['address' => 'Alaminos City, Pangasinan']);

        $internship = $this->internship();
        $make = fn ($code, $name) => DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => $code, 'name' => $name, 'stage' => 'pre_deployment',
            'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => 1,
        ]);
        $form1 = Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $make('APPLICATION_FOR_OJT', 'Form 1')->id, 'status' => 'approved']);
        DocumentFieldValue::forceCreate(['document_id' => $form1->id, 'field_key' => 'parent_guardian_name', 'value' => 'Maria Tabucol']);
        $consent = Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $make('PARENT_CONSENT', 'Form 3')->id, 'status' => 'submitted']);
        foreach (['company_name' => 'BDO Unibank', 'signed_date' => '2026-10-03', 'address' => 'Sual, Pangasinan'] as $key => $value) {
            DocumentFieldValue::forceCreate(['document_id' => $consent->id, 'field_key' => $key, 'value' => $value]);
        }

        $html = $this->actingAs($instructor)->get(route('instructor.documents.printable', $consent))->assertOk()
            ->assertSeeInOrder(['Mark Harley V. Bristol', 'Campus Internship Coordinator'])
            ->assertSeeInOrder(['Dr. Liza L. Quimson', 'Campus Executive Director'])
            ->assertSeeInOrder(['Sieg Freed Tabucol', 'Signature over Printed Name of Student'])
            ->assertSeeInOrder(['Maria Tabucol', 'Signature over Printed Name of Parent/Guardian'])
            ->getContent();

        // "Signed at Alaminos City, Pangasinan." - not "Pangasinan, Pangasinan"
        $this->assertStringNotContainsString('Pangasinan</span>, Pangasinan', $html);
        $this->assertMatchesRegularExpression('/Signed at <span class="line medium">Alaminos City<\/span>, Pangasinan/', $html);
        $this->assertMatchesRegularExpression('/20<span class="line short">26<\/span>/', $html);
    }
}
