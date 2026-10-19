<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Document;
use App\Models\DocumentFieldDefinition;
use App\Models\DocumentFieldValue;
use App\Models\DocumentRequirement;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Notification;
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
 * Print-and-sign forms: the instructor checks the filled form and approves
 * it for printing, the STUDENT prints it and has it signed, uploads the
 * signed copy, and the instructor approves (or returns) that copy.
 */
class StudentPrintsFormsTest extends TestCase
{
    use RefreshDatabase;

    private DocumentRequirement $form;

    private Student $student;

    private User $instructor;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $roles = [];
        foreach (['instructor', 'student'] as $code) {
            $roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $this->form = DocumentRequirement::forceCreate([
            'campus_id' => $campus->id, 'code' => 'OJT_RESUME', 'name' => 'Form 11 – Internship Resume', 'form_number' => '11',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => 90,
        ]);
        DocumentFieldDefinition::forceCreate(['document_requirement_id' => $this->form->id, 'field_key' => 'address', 'label' => 'Address', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1]);

        $mk = fn ($role, $id, $name) => User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $roles[$role]->id, 'id_number' => $id, 'name' => $name,
            'email' => strtolower($id).'@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $this->instructor = $mk('instructor', 'ACC-INST-001', 'Prof. Ian');
        Instructor::forceCreate(['user_id' => $this->instructor->id, 'campus_id' => $campus->id, 'program_id' => $program->id]);
        $this->student = Student::forceCreate(['user_id' => $mk('student', '23-AC-0093', 'Jasmine')->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $internship = Internship::forceCreate(['student_id' => $this->student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);

        $this->document = Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $this->form->id, 'status' => 'submitted', 'submitted_at' => now()]);
        DocumentFieldValue::forceCreate(['document_id' => $this->document->id, 'field_key' => 'address', 'value' => 'Sual, Pangasinan']);
    }

    private function upload(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->student->user)->post(route('student.documents.upload-signed', $this->document), [
            'file' => UploadedFile::fake()->create('signed-resume.pdf', 80, 'application/pdf'),
        ]);
    }

    public function test_the_student_cannot_print_before_the_instructor_approves_it(): void
    {
        $this->actingAs($this->student->user)->get(route('student.documents.printable', $this->document))->assertForbidden();
        $this->upload()->assertForbidden();
    }

    public function test_the_full_flow_student_prints_uploads_and_instructor_approves(): void
    {
        // 1. Instructor approves the filled form for printing.
        $this->actingAs($this->instructor)->post(route('instructor.documents.print', $this->document))->assertRedirect();
        $this->assertSame('printed', $this->document->fresh()->status);
        $this->assertTrue(Notification::where('user_id', $this->student->user_id)->where('title', 'like', '%approved for printing%')->exists());

        // 2. The student sees Download Form and can print it; the upload is still closed.
        $this->actingAs($this->student->user)->get(route('student.documents', ['form' => $this->form->id]))->assertOk()
            ->assertSee('Ready to Print')
            ->assertSee('Download Form')
            ->assertSee(route('student.documents.printable', ['document' => $this->document, 'download' => 1]), false)
            ->assertSee('Upload opens after your instructor checks the signatures')
            ->assertDontSee('data-bs-target="#signedUploadModal"', false);
        $this->actingAs($this->student->user)->get(route('student.documents.printable', $this->document))->assertOk()
            ->assertSee('Sual, Pangasinan');
        // Download: the same page saved as a PDF in the browser, no print dialog.
        $this->actingAs($this->student->user)->get(route('student.documents.printable', ['document' => $this->document, 'download' => 1]))->assertOk()
            ->assertSee('vendor/html2pdf/html2pdf.bundle.min.js', false)
            ->assertSee('Internship Resume - Jasmine.pdf', false)
            ->assertDontSee('onload="window.print()"', false);

        // The instructor only views and downloads it - no print dialog or Print button.
        $this->actingAs($this->instructor)->get(route('instructor.documents.printable', $this->document))->assertOk()
            ->assertSee('Sual, Pangasinan')->assertDontSee('window.print()', false);
        $this->actingAs($this->instructor)->get(route('instructor.documents.printable', ['document' => $this->document, 'download' => 1]))->assertOk()
            ->assertSee('pdfStatus', false);

        // 3. Before the instructor checked the signatures, the upload is closed.
        $this->upload()->assertForbidden();
        $this->actingAs($this->instructor)->get(route('instructor.documents', ['student' => $this->student->id, 'form' => $this->form->id]))->assertOk()
            ->assertSee('Signatures confirmed — open upload');

        // The student brings the signed form back; the instructor confirms the signatures.
        $this->actingAs($this->instructor)->post(route('instructor.documents.confirm-signatures', $this->document))->assertRedirect();
        $this->assertNotNull($this->document->fresh()->signatures_confirmed_at);
        $this->assertTrue(Notification::where('user_id', $this->student->user_id)->where('title', 'like', 'Upload your signed%')->exists());
        $this->actingAs($this->instructor)->post(route('instructor.documents.confirm-signatures', $this->document))->assertStatus(422);
        $this->actingAs($this->student->user)->get(route('student.documents', ['form' => $this->form->id]))->assertOk()
            ->assertSee('Upload Signed Copy')->assertSee('data-bs-target="#signedUploadModal"', false);

        // The student uploads the signed copy; it waits for the instructor.
        $this->upload()->assertRedirect();
        $document = $this->document->fresh();
        $this->assertSame('completed', $document->status);
        $this->assertSame('signed-resume.pdf', $document->original_filename);
        $this->assertTrue(Notification::where('user_id', $this->instructor->id)->where('title', 'like', '%uploaded the signed%')->exists());
        $this->upload()->assertStatus(422);   // one copy at a time

        $this->actingAs($this->instructor)->get(route('instructor.documents.show', $document))->assertOk()
            ->assertSee('Signed copy uploaded - for approval')
            ->assertSee('Approve signed copy')->assertSee('#approveStepModal', false);

        // 4. The instructor approves the signed copy.
        $this->actingAs($this->instructor)->post(route('instructor.documents.complete', $document))->assertRedirect();
        $this->assertSame('approved', $document->fresh()->status);
        $this->assertSame('approved', $document->reviews()->latest('id')->value('decision'));
    }

    public function test_returning_a_signed_copy_lets_the_student_correct_and_upload_again(): void
    {
        $this->actingAs($this->instructor)->post(route('instructor.documents.print', $this->document));
        $this->actingAs($this->instructor)->post(route('instructor.documents.confirm-signatures', $this->document));
        $this->upload()->assertRedirect();
        $path = $this->document->fresh()->file_path;
        $this->assertNotNull($path);

        $this->actingAs($this->instructor)->post(route('instructor.documents.review', $this->document), [
            'decision' => 'returned', 'remarks' => 'Missing the coordinator signature',
        ])->assertRedirect();

        $document = $this->document->fresh();
        $this->assertSame('returned', $document->status);
        $this->assertNull($document->file_path);
        $this->assertNull($document->signatures_confirmed_at);   // the next printout needs checking again
        Storage::assertMissing($path);

        // The student corrects the form; it goes back through the steps.
        $this->actingAs($this->student->user)->post(route('student.documents.fill.store', $this->form), ['address' => 'Sual, Pangasinan'])
            ->assertSessionHasNoErrors();
        $this->assertSame('submitted', $document->fresh()->status);
    }

    public function test_the_instructor_cannot_approve_before_the_signed_copy_is_uploaded(): void
    {
        $this->actingAs($this->instructor)->post(route('instructor.documents.print', $this->document));
        $this->actingAs($this->instructor)->post(route('instructor.documents.complete', $this->document))->assertStatus(422);
        $this->assertSame('printed', $this->document->fresh()->status);
    }

    public function test_signatures_cannot_be_confirmed_before_it_is_approved_for_printing(): void
    {
        $this->actingAs($this->instructor)->post(route('instructor.documents.confirm-signatures', $this->document))->assertStatus(422);  // not printed yet
        $this->assertNull($this->document->fresh()->signatures_confirmed_at);
    }

    public function test_another_student_cannot_print_it(): void
    {
        $this->actingAs($this->instructor)->post(route('instructor.documents.print', $this->document));
        $other = User::forceCreate([
            'campus_id' => $this->student->campus_id, 'role_id' => $this->student->user->role_id, 'id_number' => '23-AC-0094', 'name' => 'Lucy',
            'email' => 'lucy@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        Student::forceCreate(['user_id' => $other->id, 'campus_id' => $this->student->campus_id, 'program_id' => $this->student->program_id, 'hours_rendered' => 0]);

        $this->actingAs($other)->get(route('student.documents.printable', $this->document))->assertForbidden();
    }
}
