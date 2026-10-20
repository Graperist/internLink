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
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Week 1 submitted for instructor review. It was due Oct 09, 2026, so it is marked Late.');
        $this->assertSame('submitted', Document::where('packet_number', 1)->value('status'));
        $this->assertSame('draft', Document::where('packet_number', 2)->value('status'));
        $this->addDay('2026-10-03')->assertSessionHasErrors('entry_date'); // week 1 is with the instructor
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

    public function test_a_week_can_only_be_submitted_once_it_is_finished(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00'));
        $this->addDay('2026-10-18')->assertSessionHasNoErrors(); // Week 1 = Oct 18-24

        $this->actingAs($this->studentUser)->get(route('student.documents.log', $this->form19))->assertOk()
            ->assertSee('Submit on Oct 24')->assertDontSee('Submit week');
        $this->actingAs($this->studentUser)->post(route('student.documents.log.packet.submit', [$this->form19, 1]))
            ->assertSessionHasErrors('week');
        $this->assertSame('draft', Document::where('packet_number', 1)->value('status'));

        // A future day can't be logged ahead of time.
        $this->addDay('2026-10-20')->assertSessionHasErrors('entry_date');

        // On its last day it can be sent.
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00'));
        $this->actingAs($this->studentUser)->post(route('student.documents.log.packet.submit', [$this->form19, 1]))
            ->assertSessionHasNoErrors();
        $this->assertSame('submitted', Document::where('packet_number', 1)->value('status'));
    }

    public function test_week_one_is_fixed_once_a_week_is_sent(): void
    {
        $this->addDay('2026-10-08')->assertSessionHasNoErrors();
        // Before anything is sent, an earlier day just becomes the new Week 1
        // and the draft weeks are renumbered.
        $this->addDay('2026-10-01')->assertSessionHasNoErrors();
        $this->assertEquals([1, 2], Document::where('document_requirement_id', $this->form19->id)->orderBy('packet_number')->pluck('packet_number')->all());

        $this->actingAs($this->studentUser)->post(route('student.documents.log.packet.submit', [$this->form19, 1]))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01', $this->internship->fresh()->week_one_date->toDateString());

        // Now an earlier day is refused instead of renumbering reviewed weeks.
        $this->addDay('2026-09-28')->assertSessionHasErrors('entry_date');
        $this->addDay('2026-10-09')->assertSessionHasNoErrors();
        $this->assertSame(2, Document::where('document_requirement_id', $this->form19->id)->count());

        // Days of a week that was sent are refused, with the next week's start named.
        $this->actingAs($this->studentUser)->get(route('student.documents.log.entry.create', $this->form19))->assertOk()
            ->assertSee('lockedWeekWarning')->assertSee('"start":"2026-10-01"', false);
        $this->addDay('2026-10-03')->assertSessionHasErrors(['entry_date' => 'Week 1 (Oct 01 – Oct 07, 2026) has already been sent to your instructor, so no more days can be added to it. The next week starts on Oct 08, 2026.']);
    }

    public function test_the_page_says_when_this_week_is_already_sent(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00'));
        $doc = Document::forceCreate(['internship_id' => $this->internship->id, 'document_requirement_id' => $this->form19->id, 'packet_number' => 1, 'status' => 'approved']);
        \App\Models\DocumentEntry::forceCreate(['document_id' => $doc->id, 'entry_date' => '2026-10-18']);
        $this->internship->forceFill(['week_one_date' => '2026-10-18'])->save();

        $this->actingAs($this->studentUser)->get(route('student.documents.log', $this->form19))->assertOk()
            ->assertSee('You can add days again from')->assertSee('Oct 25, 2026');
    }

    public function test_the_migration_fixes_week_one_from_weeks_already_sent(): void
    {
        // Sent as Week 1 when weeks started on the first DTR day (Oct 18); a draft day before it.
        \App\Models\Dtr::forceCreate(['deployment_id' => Deployment::first()->id, 'log_date' => '2026-10-18', 'status' => 'approved']);
        $sent = Document::forceCreate(['internship_id' => $this->internship->id, 'document_requirement_id' => $this->form19->id, 'packet_number' => 1, 'status' => 'approved']);
        \App\Models\DocumentEntry::forceCreate(['document_id' => $sent->id, 'entry_date' => '2026-10-18']);
        $draft = Document::forceCreate(['internship_id' => $this->internship->id, 'document_requirement_id' => $this->form19->id, 'packet_number' => 0, 'status' => 'draft']);
        \App\Models\DocumentEntry::forceCreate(['document_id' => $draft->id, 'entry_date' => '2026-10-11']);

        $migration = require database_path('migrations/2026_10_20_000001_pin_form19_week_one.php');
        $migration->up();
        $this->assertSame('2026-10-18', $this->internship->fresh()->week_one_date->toDateString());

        $this->actingAs($this->studentUser)->get(route('student.documents.log', $this->form19))->assertOk()
            ->assertSeeInOrder(['Before Week 1', 'Week 1', 'Approved'])->assertDontSee('Week 0');
        $this->addDay('2026-10-25')->assertSessionHasNoErrors();
        $this->assertSame('draft', Document::where('packet_number', 2)->value('status'));
    }

    public function test_each_week_has_a_due_date_and_a_late_week_is_flagged_like_ms_teams(): void
    {
        // Week 1 = Oct 01-07, due 2 days later: Oct 09, 11:59 PM (Campus Settings default).
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
        $this->addDay('2026-10-01')->assertSessionHasNoErrors();
        $this->actingAs($this->studentUser)->get(route('student.documents.log', $this->form19))->assertOk()
            ->assertSee('Due Oct 09, 11:59 PM')->assertDontSee('>Late<', false)->assertDontSee('>Missing<', false);

        // Past due and not sent: Missing, for the student and the instructor.
        $this->travelTo(CarbonImmutable::parse('2026-10-10 09:00:00'));
        $this->actingAs($this->studentUser)->get(route('student.documents.log', $this->form19))->assertOk()->assertSee('>Missing<', false);
        $this->actingAs($this->instructorUser)->get(route('instructor.documents', ['student' => $this->internship->student_id, 'form' => $this->form19->id]))
            ->assertOk()->assertSee('due Oct 09')->assertSee('1 missing');

        // Sent late: marked Late and the instructor is told.
        $this->actingAs($this->studentUser)->post(route('student.documents.log.packet.submit', [$this->form19, 1]))->assertSessionHasNoErrors();
        $week1 = Document::where('packet_number', 1)->firstOrFail();
        $this->assertNotNull($week1->first_submitted_at);
        $notice = \App\Models\Notification::where('user_id', $this->instructorUser->id)->where('title', 'like', '%submitted Week 1 late%')->first();
        $this->assertNotNull($notice, 'the instructor is told the week came in late');
        $this->assertStringContainsString('was due Oct 09, 2026 11:59 PM; submitted 1 day late', $notice->message);
        $this->actingAs($this->instructorUser)->get(route('instructor.documents', ['student' => $this->internship->student_id, 'form' => $this->form19->id]))
            ->assertOk()->assertSee('>Late<', false)->assertSee('1 late');

        // Returned and sent again: still counted from the first submission (still Late, not a new date).
        $first = $week1->first_submitted_at;
        $week1->update(['status' => 'returned']);
        $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00:00'));
        $this->actingAs($this->studentUser)->post(route('student.documents.log.packet.submit', [$this->form19, 1]))->assertSessionHasNoErrors();
        $this->assertTrue($week1->fresh()->first_submitted_at->equalTo($first));
    }

    public function test_a_week_sent_on_time_is_not_late_and_the_instructor_sets_the_deadline(): void
    {
        // The Subject Instructor sets it under Settings (not the coordinator).
        $this->actingAs($this->instructorUser)->get(route('instructor.settings'))->assertOk()->assertSee('Form 19 weekly report deadline');
        $this->actingAs($this->instructorUser)->put(route('instructor.settings.update'), ['weekly_report_due_days' => 0])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $this->internship->program->fresh()->weekly_report_due_days);
        $this->internship->unsetRelation('program');
        $coord = User::forceCreate(['campus_id' => $this->internship->campus_id, 'role_id' => Role::where('code', 'coordinator')->value('id'), 'id_number' => 'C-1', 'name' => 'Coord',
            'email' => 'c1@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false]);
        Instructor::forceCreate(['user_id' => $coord->id, 'campus_id' => $this->internship->campus_id, 'program_id' => $this->internship->program_id]);
        $this->actingAs($coord)->put(route('instructor.settings.update'), ['weekly_report_due_days' => 5])->assertForbidden();
        $this->actingAs($coord)->get(route('coordinator.campus-settings.edit'))->assertOk()->assertDontSee('weekly report deadline');
        $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00:00'));   // Week 1's last day = its due date
        $this->addDay('2026-10-01')->assertSessionHasNoErrors();
        $this->actingAs($this->studentUser)->get(route('student.documents.log', $this->form19))->assertOk()->assertSee('Due Oct 07, 11:59 PM');
        $this->actingAs($this->studentUser)->post(route('student.documents.log.packet.submit', [$this->form19, 1]))
            ->assertSessionHas('status', 'Week 1 submitted for instructor review.');
        $this->assertSame(0, \App\Models\Notification::where('title', 'like', '%late%')->count());
    }

    public function test_the_overdue_reminder_uses_the_due_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 08:00:00'));
        $this->addDay('2026-10-01')->assertSessionHasNoErrors();

        // Oct 09 is the due day: no overdue notice yet.
        $this->travelTo(CarbonImmutable::parse('2026-10-09 08:00:00'));
        $this->artisan('internlink:send-reminders --overdue')->assertSuccessful();
        $this->assertSame(0, \App\Models\Notification::where('title', 'Week 1 report is overdue')->count());

        $this->travelTo(CarbonImmutable::parse('2026-10-10 08:00:00'));
        $this->artisan('internlink:send-reminders --overdue')->assertSuccessful();
        $this->assertSame(1, \App\Models\Notification::where('user_id', $this->studentUser->id)->where('title', 'Week 1 report is overdue')->count());
    }
}
