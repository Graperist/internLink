<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentEntry;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Form 19: every week is its own report, checked by the instructor as soon as it is finished. */
class Form19WeeklyReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $studentUser;
    private User $instructorUser;
    private Internship $internship;
    private DocumentRequirement $form19;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00'));
        $roles = [];
        foreach (['coordinator', 'instructor', 'hte_representative', 'student'] as $code) {
            $roles[$code] = Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $mk = fn ($role, $id) => User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $roles[$role]->id, 'id_number' => $id, 'name' => "$role $id",
            'email' => "$id@t.local", 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $this->instructorUser = $mk('instructor', 'I-1');
        Instructor::forceCreate(['user_id' => $this->instructorUser->id, 'campus_id' => $campus->id, 'program_id' => $program->id]);
        $this->studentUser = $mk('student', 'S-1');
        $student = Student::forceCreate(['user_id' => $this->studentUser->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $this->internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'deployed']);
        $hte = Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'Fareheaven']);
        Deployment::forceCreate(['internship_id' => $this->internship->id, 'hte_id' => $hte->id, 'status' => 'active', 'start_date' => '2026-10-01']);
        $this->form19 = DocumentRequirement::forceCreate([
            'campus_id' => $campus->id, 'code' => 'WEEKLY_REPORT', 'name' => 'Form 19 – Internship/Practicum Weekly Report', 'form_number' => '19',
            'stage' => 'active', 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true,
            'is_repeating' => true, 'entry_cadence' => 'daily', 'display_order' => 19,
        ]);
    }

    private function addDay(string $date)
    {
        return $this->actingAs($this->studentUser)->post(route('student.documents.log.entry.store', $this->form19),
            ['entry_date' => $date, 'tasks_accomplished' => 'Tasks '.$date, 'knowledge_skills_learned' => 'Learned']);
    }

    public function test_each_week_is_its_own_form_19(): void
    {
        foreach (['2026-10-01', '2026-10-02', '2026-10-08', '2026-11-06'] as $date) {
            $this->addDay($date)->assertSessionHasNoErrors();
        }
        // Week 1 = Oct 01-07, Week 2 = Oct 08-14, Week 6 = Nov 05-11.
        $this->assertEquals([1 => 2, 2 => 1, 6 => 1],
            Document::where('document_requirement_id', $this->form19->id)->withCount('entries')->get()->pluck('entries_count', 'packet_number')->sortKeys()->all());

        // Submitting Week 1 doesn't touch Week 2: the student keeps logging.
        $this->actingAs($this->studentUser)->post(route('student.documents.log.packet.submit', [$this->form19, 1]))
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Week 1 submitted for instructor review.');
        $this->assertSame('submitted', Document::where('packet_number', 1)->value('status'));
        $this->assertSame('draft', Document::where('packet_number', 2)->value('status'));
        $this->addDay('2026-10-03')->assertForbidden();             // week 1 is with the instructor
        $this->addDay('2026-10-09')->assertSessionHasNoErrors();    // week 2 is not

        // The student sees months, then weeks.
        $this->actingAs($this->studentUser)->get(route('student.documents.log', $this->form19))->assertOk()
            ->assertSeeInOrder(['October 2026', 'Week 1', 'With your instructor', 'Week 2', 'Submit week', 'November 2026', 'Week 6']);

        // The instructor sees each week on its own, grouped by month, and approves Week 1 for printing.
        $week1 = Document::where('packet_number', 1)->firstOrFail();
        $this->actingAs($this->instructorUser)->get(route('instructor.documents', ['student' => $this->internship->student_id, 'form' => $this->form19->id]))->assertOk()
            ->assertSee('October 2026')->assertSee('Week 1')->assertSee('1 waiting for your review')
            ->assertSee(route('instructor.documents.print', $week1));
        $this->actingAs($this->instructorUser)->post(route('instructor.documents.print', $week1))->assertRedirect();
        $this->assertSame('printed', $week1->fresh()->status);
        $this->assertSame('draft', Document::where('packet_number', 2)->value('status'));
    }

    public function test_an_old_four_week_draft_packet_is_split_into_weeks(): void
    {
        $draft = Document::forceCreate(['internship_id' => $this->internship->id, 'document_requirement_id' => $this->form19->id, 'packet_number' => 1, 'status' => 'draft']);
        $signed = Document::forceCreate(['internship_id' => $this->internship->id, 'document_requirement_id' => $this->form19->id, 'packet_number' => 2, 'status' => 'approved', 'file_path' => 'x.pdf']);
        foreach (['2026-10-01', '2026-10-09', '2026-10-16'] as $date) {   // weeks 1, 2, 3
            DocumentEntry::forceCreate(['document_id' => $draft->id, 'entry_date' => $date]);
        }
        foreach (['2026-10-29', '2026-11-06'] as $date) {                 // weeks 5, 6 - already signed
            DocumentEntry::forceCreate(['document_id' => $signed->id, 'entry_date' => $date]);
        }

        (require database_path('migrations/2026_10_19_000004_form19_one_document_per_week.php'))->up();

        $docs = Document::where('document_requirement_id', $this->form19->id)->withCount('entries')->orderBy('packet_number')->get();
        $this->assertEquals([1, 2, 3, 5], $docs->pluck('packet_number')->all());
        $this->assertEquals([1, 1, 1, 2], $docs->pluck('entries_count')->all());
        $this->assertSame($draft->id, $docs[0]->id);                              // the first week keeps the document
        $this->assertSame('approved', $docs->firstWhere('packet_number', 5)->status);   // the signed packet stays whole
        $this->assertSame(5, DB::table('document_entries')->count());
    }
}
