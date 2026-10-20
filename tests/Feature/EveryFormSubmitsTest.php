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
                $type === 'number' => $el->getAttribute('value') ?: '1',
                $type === 'email' => $el->getAttribute('value') ?: 'lucy@example.com',
                $type === 'hidden' => $el->getAttribute('value'),
                default => $el->getAttribute('value') !== '' ? $el->getAttribute('value') : 'Sample',
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

    /** The "Number of Hours" the student types in Form 1 is their required hours everywhere. */
    public function test_required_hours_come_from_form_1(): void
    {
        $internship = Internship::firstOrFail();
        $this->assertSame(486, $internship->requiredHours(), 'before Form 1: the program hours');

        $form1 = DocumentRequirement::where('code', 'APPLICATION_FOR_OJT')->firstOrFail();
        $html = $this->actingAs($this->user)->get(route('student.documents.fill', $form1))->assertOk()->getContent();
        $data = $this->payloadFrom($html);
        $data['hte_number_of_hours'] = '600';
        $this->actingAs($this->user)->post(route('student.documents.fill.store', $form1), $data)->assertSessionHasNoErrors();

        $internship = $internship->fresh();
        $this->assertSame(600, $internship->requiredHours());
        $this->assertSame(600, (int) $internship->required_hours, 'saved on the internship too');

        // Forms filled after Form 1 carry the typed hours.
        $consent = DocumentRequirement::where('code', 'PARENT_CONSENT')->firstOrFail();
        $this->actingAs($this->user)->get(route('student.documents.fill', $consent))->assertOk()->assertSee('600 hours');
        $this->actingAs($this->user)->get(route('student.dashboard'))->assertOk()->assertSee('600');
    }
}
