<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Photocopy of Parent/Guardian Valid ID is attached to Form 3
 * (Consent and Waiver) instead of being a separate requirement.
 */
class Form3GuardianIdTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private DocumentRequirement $form3;

    private Student $student;

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $roles = [];
        foreach (['instructor', 'student'] as $code) {
            $roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $this->campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active', 'address' => 'Alaminos City, Pangasinan']);
        $program = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology', 'required_hours' => 486]);
        Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Hundred Islands Resort']);

        $this->form3 = DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'PARENT_CONSENT', 'name' => 'Form 3 – Consent and Waiver', 'form_number' => '3',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => 50,
        ]);
        foreach (['full_name', 'address', 'course', 'required_hours', 'company_name', 'signed_city', 'signed_date'] as $n => $key) {
            DocumentFieldDefinition::forceCreate(['document_requirement_id' => $this->form3->id, 'field_key' => $key, 'label' => $key, 'field_type' => 'text', 'is_required' => false, 'sort_order' => $n]);
        }

        $user = User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $roles['student']->id, 'id_number' => '23-AC-0094', 'name' => 'Lucy Ro',
            'email' => 'lucy@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $this->student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $this->student->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);

        $this->instructor = User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $roles['instructor']->id, 'id_number' => 'ACC-INST-001', 'name' => 'Inst',
            'email' => 'inst@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        Instructor::forceCreate(['user_id' => $this->instructor->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id]);
    }

    private function consent(array $extra = []): array
    {
        return ['address' => 'Sual, Pangasinan', 'company_name' => 'Hundred Islands Resort', 'signed_date' => '2026-10-16'] + $extra;
    }

    public function test_form3_page_asks_for_the_guardian_id(): void
    {
        $this->actingAs($this->student->user)->get(route('student.documents.fill', $this->form3))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="attachment_parent_guardian_id"', false)
            ->assertSee('Parent/Guardian Valid ID (photocopy)');
    }

    public function test_form3_needs_the_guardian_id(): void
    {
        $this->actingAs($this->student->user)->post(route('student.documents.fill.store', $this->form3), $this->consent())
            ->assertSessionHasErrors(['attachment_parent_guardian_id' => "Attach a photocopy of your parent/guardian's valid ID."]);
        $this->assertSame(0, Document::count());
    }

    public function test_form3_is_submitted_with_the_guardian_id_and_kept_on_resubmission(): void
    {
        $this->actingAs($this->student->user)->post(route('student.documents.fill.store', $this->form3), $this->consent([
            'attachment_parent_guardian_id' => UploadedFile::fake()->image('mama-id.jpg'),
        ]))->assertSessionHasNoErrors();

        $document = Document::sole();
        $this->assertSame('submitted', $document->status);
        $this->assertSame('mama-id.jpg', $document->attachments()->value('original_filename'));
        $this->assertSame('Hundred Islands Resort', $document->fieldValues()->where('field_key', 'company_name')->value('value'));

        // Returned for a correction: the ID already attached is kept.
        $document->update(['status' => 'returned']);
        $this->actingAs($this->student->user)->post(route('student.documents.fill.store', $this->form3), $this->consent(['signed_date' => '2026-10-17']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, DocumentAttachment::count());
        $this->assertSame('submitted', $document->fresh()->status);
    }

    public function test_instructor_sees_and_opens_the_guardian_id(): void
    {
        $this->actingAs($this->student->user)->post(route('student.documents.fill.store', $this->form3), $this->consent([
            'attachment_parent_guardian_id' => UploadedFile::fake()->create('guardian-id.pdf', 50, 'application/pdf'),
        ]));
        $document = Document::sole();
        $attachment = DocumentAttachment::sole();

        $this->actingAs($this->instructor)->get(route('instructor.documents.show', $document))->assertOk()
            ->assertSee('Parent/Guardian Valid ID (photocopy)')->assertSee('guardian-id.pdf')
            ->assertSee(route('instructor.documents.attachment', $attachment), false);
        $this->actingAs($this->instructor)->get(route('instructor.documents', ['student' => $this->student->id, 'form' => $this->form3->id]))->assertOk()
            ->assertSee(route('instructor.documents.attachment', $attachment), false);
        $this->actingAs($this->instructor)->get(route('instructor.documents.attachment', $attachment))->assertOk();
    }

    public function test_migration_moves_uploaded_ids_onto_form3(): void
    {
        $idRequirement = DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'PARENT_GUARDIAN_ID', 'name' => 'Photocopy of Parent/Guardian Valid ID',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => true, 'is_required' => true, 'display_order' => 60,
        ]);
        $internship = $this->student->currentInternship;
        $form3 = Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $this->form3->id, 'status' => 'submitted']);
        Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $idRequirement->id, 'status' => 'approved', 'file_path' => 'old/id.jpg', 'original_filename' => 'id.jpg']);

        $migration = require database_path('migrations/2026_10_16_400000_attach_guardian_id_to_consent_form.php');
        $migration->up();

        $this->assertSame('old/id.jpg', $form3->attachments()->where('kind', 'parent_guardian_id')->value('file_path'));
        $this->assertFalse((bool) $idRequirement->fresh()->is_active);
        $this->assertFalse((bool) $idRequirement->fresh()->is_required);
    }
}
