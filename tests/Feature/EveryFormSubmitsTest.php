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
        Document::where('document_requirement_id', $form1->id)->update(['status' => 'printed']);
        $consent = DocumentRequirement::where('code', 'PARENT_CONSENT')->firstOrFail();
        $this->actingAs($this->user)->get(route('student.documents.fill', $consent))->assertOk()->assertSee('name="required_hours"', false)->assertSee('value="600"', false);
        $this->actingAs($this->user)->get(route('student.dashboard'))->assertOk()->assertSee('600');
    }

    public function test_the_agency_forms_remind_to_fill_form_1_first(): void
    {
        $consent = DocumentRequirement::where('code', 'PARENT_CONSENT')->firstOrFail();
        $form1 = DocumentRequirement::where('code', 'APPLICATION_FOR_OJT')->firstOrFail();

        // Before Form 1: a reminder, but the form can still be filled.
        $this->actingAs($this->user)->get(route('student.documents', ['form' => $consent->id]))->assertOk()
            ->assertSee('Fill out Form 1 (Application for Internship) first')->assertSee('Go to Form 1')
            ->assertSee('Next')->assertSee('In the suggested order');
        $this->actingAs($this->user)->get(route('student.documents.fill', $consent))->assertOk();
        $this->actingAs($this->user)->get(route('student.documents'))->assertOk()
            ->assertSeeInOrder(['Form 1', 'Form 2', 'Form 11', 'Form 3', 'Form 6', 'Form 12', 'Form 15', 'Form 18', 'Form 10']);

        // After Form 1 is submitted: no reminder.
        Document::create(['internship_id' => Internship::firstOrFail()->id, 'document_requirement_id' => $form1->id, 'status' => 'submitted', 'submitted_at' => now()]);
        $this->actingAs($this->user)->get(route('student.documents', ['form' => $consent->id]))->assertOk()
            ->assertDontSee('Fill out Form 1 (Application for Internship) first');
    }

    /** Form 12: the required hours are typed (they depend on the program). */
    public function test_form_12_required_hours_are_typed(): void
    {
        $form12 = DocumentRequirement::where('code', 'INTERNSHIP_AGREEMENT')->firstOrFail();
        $html = $this->actingAs($this->user)->get(route('student.documents.fill', $form12))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<input type="number"[^>]*name="required_hours"[^>]*value="486"/', $html);
        $this->assertSame(1, substr_count($html, 'name="required_hours"'), 'one hours field, no hidden copy');

        $data = $this->payloadFrom($html);
        $data['required_hours'] = '600';
        $this->actingAs($this->user)->post(route('student.documents.fill.store', $form12), $data)->assertSessionHasNoErrors();
        $doc = Document::where('document_requirement_id', $form12->id)->firstOrFail();
        $this->assertSame('600', $doc->fieldValue('required_hours'));

        $data['required_hours'] = '';
        $this->actingAs($this->user)->post(route('student.documents.fill.store', $form12), $data)
            ->assertSessionHasErrors(['required_hours' => 'Type the required internship hours for your program.']);
    }

    /** Forms 3 and 15 (and the practicum form): the required hours are typed too. */
    public function test_the_other_forms_take_typed_hours(): void
    {
        foreach (['PARENT_CONSENT', 'INTERNSHIP_RELEASE_FORM'] as $code) {
            $form = DocumentRequirement::where('code', $code)->firstOrFail();
            $html = $this->actingAs($this->user)->get(route('student.documents.fill', $form))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<input type="number"[^>]*name="required_hours"[^>]*value="486"/', $html, $code);
            $this->assertSame(1, substr_count($html, 'name="required_hours"'), $code.': one hours field');

            $data = $this->payloadFrom($html);
            $data['required_hours'] = 'abc';
            $this->actingAs($this->user)->post(route('student.documents.fill.store', $form), $data)->assertSessionHasErrors('required_hours');
            $data['required_hours'] = '540';
            $this->actingAs($this->user)->post(route('student.documents.fill.store', $form), $data)->assertSessionHasNoErrors();
            $this->assertSame('540', Document::where('document_requirement_id', $form->id)->firstOrFail()->fieldValue('required_hours'), $code);
        }
    }
}
