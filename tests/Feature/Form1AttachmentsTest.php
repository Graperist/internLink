<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
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
 * The Medical Certificate and the Psychological Test Result are attached to
 * Form 1 (Application for Internship) instead of being separate requirements.
 */
class Form1AttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Program $bsit;

    private DocumentRequirement $form1;

    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        foreach (['coordinator', 'instructor', 'student'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $this->campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $this->bsit = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology', 'required_hours' => 486]);
        $this->form1 = DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'APPLICATION_FOR_OJT', 'name' => 'Form 1 – Application for Internship',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'print_sign',
            'is_active' => true, 'is_required' => true, 'display_order' => 10, 'form_number' => '1',
        ]);
        DocumentFieldDefinition::forceCreate([
            'document_requirement_id' => $this->form1->id, 'field_key' => 'surname', 'label' => 'Surname',
            'field_type' => 'text', 'is_required' => true, 'sort_order' => 1,
        ]);
    }

    private function user(string $role, string $id): User
    {
        return User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $this->roles[$role]->id, 'id_number' => $id, 'name' => "{$role} {$id}",
            'email' => strtolower($id).'@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
    }

    private function student(): Student
    {
        $student = Student::forceCreate(['user_id' => $this->user('student', '23-AC-0001')->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id, 'required_hours' => 486, 'status' => 'pre_deployment']);

        return $student->fresh();
    }

    private function files(): array
    {
        return [
            'attachment_medical_certificate' => UploadedFile::fake()->create('medical.pdf', 200, 'application/pdf'),
            'attachment_psychological_test' => UploadedFile::fake()->image('psych.jpg'),
        ];
    }

    public function test_form1_page_asks_for_both_attachments(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)->get(route('student.documents.fill', $this->form1))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="attachment_medical_certificate"', false)
            ->assertSee('name="attachment_psychological_test"', false);
    }

    public function test_form1_cannot_be_submitted_without_the_attachments(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)->post(route('student.documents.fill.store', $this->form1), ['surname' => 'TABUCOL'])
            ->assertSessionHasErrors(['attachment_medical_certificate', 'attachment_psychological_test']);

        $this->assertSame(0, Document::count());
    }

    public function test_form1_is_submitted_together_with_the_attachments(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)->post(route('student.documents.fill.store', $this->form1), ['surname' => 'TABUCOL'] + $this->files())
            ->assertSessionHasNoErrors();

        $document = Document::with('attachments')->sole();
        $this->assertSame('submitted', $document->status);
        $this->assertCount(2, $document->attachments);
        $this->assertSame('medical.pdf', $document->attachment('medical_certificate')->original_filename);
        Storage::assertExists($document->attachment('psychological_test')->file_path);
    }

    public function test_resubmission_keeps_or_replaces_the_attachments(): void
    {
        $student = $this->student();
        $this->actingAs($student->user)->post(route('student.documents.fill.store', $this->form1), ['surname' => 'TABUCOL'] + $this->files());
        $document = Document::sole();
        $document->update(['status' => 'returned']);
        $oldMedical = $document->attachments()->where('kind', 'medical_certificate')->value('file_path');

        // Correct a field only: the files already attached are kept.
        $this->actingAs($student->user)->post(route('student.documents.fill.store', $this->form1), ['surname' => 'TABUCOL JR'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, DocumentAttachment::count());

        // Replace the medical certificate: the old file is removed.
        $document->refresh()->update(['status' => 'returned']);
        $this->actingAs($student->user)->post(route('student.documents.fill.store', $this->form1), [
            'surname' => 'TABUCOL JR',
            'attachment_medical_certificate' => UploadedFile::fake()->create('new-medical.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, DocumentAttachment::count());
        $this->assertSame('new-medical.pdf', $document->attachments()->where('kind', 'medical_certificate')->value('original_filename'));
        Storage::assertMissing($oldMedical);
    }

    public function test_attachments_must_be_pdf_or_image(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)->post(route('student.documents.fill.store', $this->form1), [
            'surname' => 'TABUCOL',
            'attachment_medical_certificate' => UploadedFile::fake()->create('virus.exe', 10),
            'attachment_psychological_test' => UploadedFile::fake()->image('psych.png'),
        ])->assertSessionHasErrors('attachment_medical_certificate');
    }

    public function test_instructor_sees_and_opens_the_attachments_with_form1(): void
    {
        $student = $this->student();
        $this->actingAs($student->user)->post(route('student.documents.fill.store', $this->form1), ['surname' => 'TABUCOL'] + $this->files());
        $document = Document::sole();
        $attachment = $document->attachments()->where('kind', 'medical_certificate')->first();

        $instructor = $this->user('instructor', 'ACC-INST-001');
        Instructor::forceCreate(['user_id' => $instructor->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id]);

        $this->actingAs($instructor)->get(route('instructor.documents.show', $document))->assertOk()
            ->assertSee('Medical Certificate (government physician)')
            ->assertSee('Psychological Test Result')
            ->assertSee('medical.pdf')
            ->assertSee(route('instructor.documents.attachment', $attachment), false);

        $this->actingAs($instructor)->get(route('instructor.documents', ['student' => $student->id]))->assertOk()
            ->assertSee(route('instructor.documents.attachment', $attachment), false);

        $this->actingAs($instructor)->get(route('instructor.documents.attachment', $attachment))->assertOk();
        $this->actingAs($student->user)->get(route('student.documents.attachment', $attachment))->assertOk();
    }

    public function test_other_students_and_campuses_cannot_open_the_attachments(): void
    {
        $student = $this->student();
        $this->actingAs($student->user)->post(route('student.documents.fill.store', $this->form1), ['surname' => 'TABUCOL'] + $this->files());
        $attachment = DocumentAttachment::first();

        $other = Student::forceCreate(['user_id' => $this->user('student', '23-AC-0002')->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id, 'hours_rendered' => 0]);
        $this->actingAs($other->user)->get(route('student.documents.attachment', $attachment))->assertForbidden();

        $otherCampus = Campus::forceCreate(['code' => 'BC', 'name' => 'PSU - Bayambang', 'status' => 'active']);
        $outsider = User::forceCreate([
            'campus_id' => $otherCampus->id, 'role_id' => $this->roles['instructor']->id, 'id_number' => 'BC-INST-001', 'name' => 'Outsider',
            'email' => 'bc@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        Instructor::forceCreate(['user_id' => $outsider->id, 'campus_id' => $otherCampus->id]);
        $this->actingAs($outsider)->get(route('instructor.documents.attachment', $attachment))->assertForbidden();
    }

    public function test_coordinator_download_all_includes_the_attachments(): void
    {
        $student = $this->student();
        $this->actingAs($student->user)->post(route('student.documents.fill.store', $this->form1), ['surname' => 'TABUCOL'] + $this->files());
        $document = Document::sole();
        Storage::put('signed/form1.pdf', 'signed');
        $document->update(['status' => 'approved', 'file_path' => 'signed/form1.pdf', 'original_filename' => 'form1.pdf']);

        $coordinator = $this->user('coordinator', 'ACC-COORD-001');
        $response = $this->actingAs($coordinator)->get(route('coordinator.repository.student.download-all', $student))->assertOk();

        $zip = new \ZipArchive();
        $zip->open($response->baseResponse->getFile()->getPathname());
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->all();
        $zip->close();

        $this->assertContains('Pre-Deployment/Form 1 – Application for Internship.pdf', $names);
        $this->assertContains('Pre-Deployment/Medical Certificate (government physician).pdf', $names);
        $this->assertContains('Pre-Deployment/Psychological Test Result.jpg', $names);
    }

    public function test_migration_moves_old_uploads_onto_form1_and_retires_the_requirements(): void
    {
        $student = $this->student();
        $internship = $student->currentInternship;
        $medical = DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'MEDICAL_CERTIFICATE', 'name' => 'Medical Certificate',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => true, 'is_required' => true, 'display_order' => 20,
        ]);
        $form1 = Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $this->form1->id, 'status' => 'submitted']);
        Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $medical->id, 'status' => 'approved', 'file_path' => 'old/med.pdf', 'original_filename' => 'med.pdf']);

        $migration = require database_path('migrations/2026_10_16_300000_create_document_attachments_table.php');
        $migration->down();
        $medical->update(['is_active' => true, 'is_required' => true]);
        $migration->up();

        $this->assertSame('old/med.pdf', $form1->attachments()->where('kind', 'medical_certificate')->value('file_path'));
        $this->assertFalse((bool) $medical->fresh()->is_active);
        $this->assertFalse((bool) $medical->fresh()->is_required);
    }
}
