<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\HteListing;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\Term;
use Database\Seeders\OfficialOjtFormsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards against a form page and its save disagreeing on field names (the
 * "company name field is required" bug on Forms 15 and 18): for every
 * pre-deployment form a student fills in online, open the real page, fill
 * in every field it shows, submit, and expect it to be accepted.
 */
class EveryFormSubmitsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $role = Role::forceCreate(['code' => 'student', 'name' => 'student']);
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'Pangasinan State University - Alaminos City Campus', 'status' => 'active', 'address' => 'Alaminos City, Pangasinan']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology', 'required_hours' => 486]);
        $hte = Hte::forceCreate([
            'campus_id' => $campus->id, 'name' => 'FAREHEAVEN BOARDING HOUSE', 'address' => 'Bolaney, Alaminos City, Pangasinan',
            'contact_person' => 'Mayos', 'representative_position' => 'Manager',
        ]);
        [$academicYear, $semester] = Term::current();
        HteListing::forceCreate(['campus_id' => $campus->id, 'program_id' => $program->id, 'hte_id' => $hte->id, 'academic_year' => $academicYear, 'semester' => $semester, 'slots' => 10]);

        $this->seed(OfficialOjtFormsSeeder::class);

        $this->user = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => '23-AC-0094', 'name' => 'Lucy Ro',
            'email' => 'lucy@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $this->user->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);
    }

    /** Builds a submission from the inputs the page actually shows. */
    private function payloadFrom(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//form[contains(@action, "/fill")]')->item(0);
        $this->assertNotNull($form, 'the page has the fill form');

        $data = [];
        foreach ($xpath->query('.//input[@name] | .//textarea[@name] | .//select[@name]', $form) as $el) {
            $name = $el->getAttribute('name');
            $type = strtolower($el->getAttribute('type') ?: $el->nodeName);
            if (in_array($name, ['_token'], true) || in_array($type, ['submit', 'button'], true)) {
                continue;
            }
            $value = match (true) {
                $type === 'file' => UploadedFile::fake()->create($name.'.pdf', 10, 'application/pdf'),
                $type === 'checkbox' => $el->getAttribute('value') ?: '1',
                $type === 'radio' => $el->getAttribute('value'),
                $type === 'select' => $this->firstOption($xpath, $el),
                $type === 'textarea' => trim($el->textContent) !== '' ? trim($el->textContent) : 'Sample text',
                $type === 'date' => $el->getAttribute('value') ?: '2026-10-17',
                $type === 'number' => $el->getAttribute('value') ?: match ($name) {
                    'age' => '21',
                    'hte_number_of_hours', 'required_hours' => '486',
                    default => '1',
                },
                $type === 'tel' => $el->getAttribute('value') ?: '0917 123 4567',
                $type === 'email' => $el->getAttribute('value') ?: 'lucy@example.com',
                $type === 'hidden' => $el->getAttribute('value'),
                // Filled the way a student would: real numbers and years where the field asks for them.
                $el->getAttribute('value') !== '' => $el->getAttribute('value'),
                str_contains($name, 'contact') => '0917 123 4567',
                str_ends_with($name, '_date') => '2016-2022',
                in_array($name, ['height', 'weight'], true) => '160',
                default => 'Sample',
            };
            if ($type === 'radio' && isset($data[$name])) {
                continue;   // keep the first choice
            }
            $data[preg_replace('/\[\]$/', '', $name)] = $value;
        }
        // Form 1's academic table: the sample rows need their SEM and SY.
        foreach ($data as $key => $value) {
            if (preg_match('/^academic_(\d+)_subject$/', $key, $m)) {
                $data[$key] = '';
            }
        }

        return $data;
    }

    private function firstOption(\DOMXPath $xpath, \DOMElement $select): string
    {
        foreach ($xpath->query('.//option', $select) as $option) {
            if ($option->getAttribute('value') !== '' && ! $option->hasAttribute('disabled')) {
                return $option->getAttribute('value');
            }
        }

        return '';
    }

    public static function forms(): array
    {
        return array_map(fn ($code) => [$code], [
            'APPLICATION_FOR_OJT', 'PARENT_CONSENT', 'ENDORSEMENT_LETTER', 'OJT_RESUME',
            'INTERNSHIP_AGREEMENT', 'INTERNSHIP_RELEASE_FORM', 'TRAINING_LIABILITY',
        ]);
    }

    /** @dataProvider forms */
    public function test_the_form_page_can_be_submitted(string $code): void
    {
        $requirement = DocumentRequirement::where('code', $code)->firstOrFail();

        $html = $this->actingAs($this->user)->get(route('student.documents.fill', $requirement))->assertOk()->getContent();
        $response = $this->actingAs($this->user)->post(route('student.documents.fill.store', $requirement), $this->payloadFrom($html));

        $response->assertSessionHasNoErrors();
        $this->assertSame('submitted', Document::where('document_requirement_id', $requirement->id)->value('status'), "{$code} was saved");
    }

    /** Submitting checks that what was entered is correct; a wrong entry is not saved. */
    public function test_wrong_entries_are_caught_on_submit(): void
    {
        $form1 = DocumentRequirement::where('code', 'APPLICATION_FOR_OJT')->firstOrFail();
        $html = $this->actingAs($this->user)->get(route('student.documents.fill', $form1))->assertOk()->getContent();
        $this->assertStringContainsString('type="tel"', $html);
        $this->assertStringContainsString('fillForm', $html);

        $data = array_merge($this->payloadFrom($html), [
            'surname' => 'Ro123', 'age' => '7', 'contact_number' => '0917-ABC', 'hte_contact_number' => '12345',
            'civil_status' => 'Complicated', 'hte_number_of_hours' => '0',
        ]);
        $this->actingAs($this->user)->post(route('student.documents.fill.store', $form1), $data)
            ->assertSessionHasErrors([
                'surname' => 'The Surname may only contain letters, spaces, periods, commas, hyphens and apostrophes.',
                'age' => 'The Age must be between 15 and 60.',
                'contact_number', 'hte_contact_number', 'civil_status', 'hte_number_of_hours',
            ]);
        $this->assertNull(Document::where('document_requirement_id', $form1->id)->first(), 'nothing was saved');

        // Corrected (a landline is fine too): accepted.
        $data = array_merge($data, [
            'surname' => 'Dela Cruz-Ro', 'age' => '21', 'contact_number' => '+63 917 123 4567', 'hte_contact_number' => '(075) 551 2345',
            'civil_status' => 'Single', 'hte_number_of_hours' => '486',
        ]);
        $this->actingAs($this->user)->post(route('student.documents.fill.store', $form1), $data)->assertSessionHasNoErrors();
        $this->assertSame('submitted', Document::where('document_requirement_id', $form1->id)->value('status'));
    }

    public function test_the_resume_checks_years_email_and_measurements(): void
    {
        $resume = DocumentRequirement::where('code', 'OJT_RESUME')->firstOrFail();
        $html = $this->actingAs($this->user)->get(route('student.documents.fill', $resume))->assertOk()->getContent();
        $data = array_merge($this->payloadFrom($html), [
            'education_college_date' => '2026-2022', 'education_elementary_date' => 'grade school', 'training_1_date' => 'June 2099',
            'email' => 'lucy@gmail', 'height' => 'tall', 'gender' => 'Sample', 'reference_1_contact' => '123',
        ]);
        $this->actingAs($this->user)->post(route('student.documents.fill.store', $resume), $data)
            ->assertSessionHasErrors([
                'education_college_date' => 'The first year (2026) should not be after the second (2022).',
                'education_elementary_date', 'training_1_date', 'email', 'height', 'gender', 'reference_1_contact',
            ]);
        $this->assertNull(Document::where('document_requirement_id', $resume->id)->first());
    }

    public function test_phone_numbers(): void
    {
        foreach (['09171234567', '0917 123 4567', '+639171234567', '(075) 551 2345', '551-2345'] as $ok) {
            $this->assertTrue(\App\Support\FieldCorrectness::validPhone($ok), $ok);
        }
        foreach (['12345', '0917123', '0917-ABC-1234', '091712345678901', 'n/a'] as $bad) {
            $this->assertFalse(\App\Support\FieldCorrectness::validPhone($bad), $bad);
        }
    }
}
