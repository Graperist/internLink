<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Document;
use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\Form1Academics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Form 1's Academic Credentials/Qualifications is a table: SEM dropdown,
 * SY picked by its first year (2026 -> 2026-2027), subject typed in.
 */
class Form1AcademicTableTest extends TestCase
{
    use RefreshDatabase;

    private DocumentRequirement $form1;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $roles = [];
        foreach (['instructor', 'student'] as $code) {
            $roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology', 'required_hours' => 486]);
        $this->form1 = DocumentRequirement::forceCreate([
            'campus_id' => $campus->id, 'code' => 'APPLICATION_FOR_OJT', 'name' => 'Form 1', 'stage' => 'pre_deployment',
            'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => 10,
        ]);
        $order = 1;
        DocumentFieldDefinition::forceCreate(['document_requirement_id' => $this->form1->id, 'field_key' => 'surname', 'label' => 'Surname', 'group_label' => 'Personal Profile', 'field_type' => 'text', 'is_required' => true, 'sort_order' => $order++]);
        DocumentFieldDefinition::forceCreate(['document_requirement_id' => $this->form1->id, 'field_key' => 'passed_units', 'label' => 'Total Passed Units', 'group_label' => 'Academic Credentials/Qualifications', 'field_type' => 'number', 'is_required' => false, 'sort_order' => $order++]);
        for ($i = 1; $i <= 5; $i++) {
            foreach (['sem', 'sy', 'subject', 'passing', 'failing', 'grade', 'signature', 'date'] as $col) {
                DocumentFieldDefinition::forceCreate(['document_requirement_id' => $this->form1->id, 'field_key' => "academic_{$i}_{$col}", 'label' => "Row {$i} – {$col}", 'group_label' => 'Academic Credentials/Qualifications', 'field_type' => 'text', 'is_required' => false, 'sort_order' => $order++]);
            }
        }

        $user = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $roles['student']->id, 'id_number' => '23-AC-0094', 'name' => 'Lucy Ro',
            'email' => 'lucy@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $this->student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $this->student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);

        $instructor = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $roles['instructor']->id, 'id_number' => 'ACC-INST-001', 'name' => 'Inst',
            'email' => 'inst@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        Instructor::forceCreate(['user_id' => $instructor->id, 'campus_id' => $campus->id, 'program_id' => $program->id]);
    }

    private function submit(array $data)
    {
        return $this->actingAs($this->student->user)->post(route('student.documents.fill.store', $this->form1), $data + [
            'surname' => 'RO',
            'attachment_medical_certificate' => UploadedFile::fake()->create('m.pdf', 10, 'application/pdf'),
            'attachment_psychological_test' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'),
        ]);
    }

    public function test_school_years_list_the_first_year_and_store_the_range(): void
    {
        $options = Form1Academics::schoolYearOptions(2026);
        $this->assertSame(2026, $options['2026-2027']);
        $this->assertSame('2026-2027', array_key_first($options));
        $this->assertArrayHasKey('2019-2020', $options);
    }

    public function test_the_fill_page_shows_the_table_with_dropdowns(): void
    {
        $html = $this->actingAs($this->student->user)->get(route('student.documents.fill', $this->form1))->assertOk()
            ->assertSee('il-academic-table', false)
            ->assertSee('<option value="1st Semester"', false)
            ->assertSee('<option value="2nd Semester"', false)
            ->assertSee('<option value="'.now()->year.'-'.(now()->year + 1).'" data-year="'.now()->year.'"', false)
            ->assertSee('name="academic_1_subject"', false)
            ->getContent();

        // Signature and Date are signed by hand, not typed by the student.
        $this->assertStringNotContainsString('name="academic_1_signature"', $html);
        $this->assertStringNotContainsString('name="academic_1_date"', $html);
        $this->assertStringNotContainsString('Row 1 – sem', $html);
    }

    public function test_rows_are_saved_and_printed(): void
    {
        $this->submit([
            'passed_units' => '150',
            'academic_1_sem' => '1st Semester', 'academic_1_sy' => '2026-2027', 'academic_1_subject' => 'IT 401 Capstone', 'academic_1_grade' => '1.50',
            'academic_2_sem' => '2nd Semester', 'academic_2_sy' => '2025-2026', 'academic_2_subject' => 'IT 312',
        ])->assertSessionHasNoErrors();

        $document = Document::sole();
        $this->assertSame('2026-2027', $document->fieldValue('academic_1_sy'));
        $this->assertSame('1st Semester', $document->fieldValue('academic_1_sem'));

        $instructor = User::where('id_number', 'ACC-INST-001')->first();
        $this->actingAs($instructor)->get(route('instructor.documents.printable', $document))->assertOk()
            ->assertSee('1st Sem')->assertSee('2026-2027')->assertSee('IT 401 Capstone')->assertSee('2nd Sem');

        // The picked year shows as the full school year when the form is reopened.
        $document->update(['status' => 'returned']);
        $this->actingAs($this->student->user)->get(route('student.documents.fill', $this->form1))
            ->assertSee('<option value="2026-2027" data-year="2026" selected>2026-2027</option>', false);
    }

    public function test_a_subject_needs_its_sem_and_sy_and_values_are_checked(): void
    {
        $this->submit(['academic_1_subject' => 'IT 401'])
            ->assertSessionHasErrors(['academic_1_sem', 'academic_1_sy']);

        $this->submit(['academic_1_subject' => 'IT 401', 'academic_1_sem' => '3rd Semester', 'academic_1_sy' => '2026-2028'])
            ->assertSessionHasErrors(['academic_1_sem', 'academic_1_sy']);

        $this->assertSame(0, Document::count());
    }
}
