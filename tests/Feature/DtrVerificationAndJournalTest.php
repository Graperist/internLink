<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\DocumentRequirement;
use App\Models\Dtr;
use App\Models\Hte;
use App\Models\HteRepresentative;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyJournal;
use App\Support\WeeklyJournalSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Today in the scenarios below is a Sunday (2026-10-04). Deployment starts
 * Wednesday 2026-09-30, so week 1 is Mon Sep 28 - Sun Oct 4, due Fri Oct 2.
 */
class DtrVerificationAndJournalTest extends TestCase
{
    use RefreshDatabase;

    private array $roles = [];
    private Campus $campus;
    private Program $program;
    private Hte $hte;
    private User $repUser;
    private HteRepresentative $rep;
    private User $studentUser;
    private Student $student;
    private User $instructorUser;
    private Internship $internship;
    private Deployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        foreach (['coordinator', 'instructor', 'hte_representative', 'student'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }

        $this->campus = Campus::forceCreate(['code' => 'AAA', 'name' => 'Campus A', 'status' => 'active']);
        $this->program = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 300]);
        $this->hte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Fareheaven']);

        $this->repUser = $this->makeUser('hte_representative', 'R-1');
        $this->rep = HteRepresentative::forceCreate(['user_id' => $this->repUser->id, 'hte_id' => $this->hte->id]);

        $this->studentUser = $this->makeUser('student', 'S-1');
        $this->student = Student::forceCreate(['user_id' => $this->studentUser->id, 'campus_id' => $this->campus->id, 'program_id' => $this->program->id]);

        $this->instructorUser = $this->makeUser('instructor', 'I-1');
        Instructor::forceCreate(['user_id' => $this->instructorUser->id, 'campus_id' => $this->campus->id, 'program_id' => $this->program->id]);

        $this->internship = Internship::forceCreate([
            'student_id' => $this->student->id, 'campus_id' => $this->campus->id,
            'program_id' => $this->program->id, 'status' => 'deployed',
        ]);
        $this->deployment = Deployment::forceCreate([
            'internship_id' => $this->internship->id, 'hte_id' => $this->hte->id,
            'start_date' => '2026-09-30', 'status' => 'active',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    }

    private function makeUser(string $role, string $idNumber): User
    {
        return User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $this->roles[$role]->id,
            'id_number' => $idNumber, 'name' => "{$role} {$idNumber}", 'email' => "{$idNumber}@test.local",
            'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
    }

    private function makeDtr(array $attrs = []): Dtr
    {
        return Dtr::forceCreate($attrs + [
            'deployment_id' => $this->deployment->id, 'log_date' => '2026-10-04',
            'time_in' => '12:13:00', 'time_out' => '14:13:00', 'hours_logged' => 2, 'status' => 'pending',
        ]);
    }

    // ===================== DTR: verify clock-in / clock-out separately =====================

    public function test_clock_in_can_be_verified_before_the_student_clocks_out(): void
    {
        $dtr = $this->makeDtr(['time_out' => null, 'hours_logged' => 0]);

        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify", ['target' => 'clock_in'])->assertRedirect('/hte/dtr');

        $dtr->refresh();
        $this->assertTrue($dtr->isClockInVerified());
        $this->assertFalse($dtr->isClockOutVerified());
        $this->assertSame('pending', $dtr->status);
        $this->assertSame(0, (int) $this->student->fresh()->hours_rendered);
    }

    public function test_clock_out_cannot_be_verified_before_it_exists(): void
    {
        $dtr = $this->makeDtr(['time_out' => null, 'hours_logged' => 0]);

        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify", ['target' => 'clock_out'])->assertStatus(422);
        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify", ['target' => 'both'])->assertStatus(422);

        $this->assertFalse($dtr->fresh()->isClockOutVerified());
    }

    public function test_hours_are_credited_only_when_both_events_are_verified_and_only_once(): void
    {
        $dtr = $this->makeDtr();

        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify", ['target' => 'clock_in']);
        $this->assertSame('pending', $dtr->fresh()->status);
        $this->assertSame(0, (int) $this->student->fresh()->hours_rendered);

        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify", ['target' => 'clock_out']);
        $dtr->refresh();
        $this->assertSame('approved', $dtr->status);
        $this->assertSame($this->rep->id, $dtr->verified_by);
        $this->assertSame(2, (int) $this->student->fresh()->hours_rendered);

        // Verifying again must not credit hours a second time.
        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify", ['target' => 'both'])->assertStatus(422);
        $this->assertSame(2, (int) $this->student->fresh()->hours_rendered);
    }

    public function test_verify_both_approves_in_one_step_and_a_verified_event_cannot_be_reverified(): void
    {
        $dtr = $this->makeDtr();
        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify", ['target' => 'both']);
        $this->assertSame('approved', $dtr->fresh()->status);

        $second = $this->makeDtr(['log_date' => '2026-10-03']);
        $this->actingAs($this->repUser)->post("/hte/dtr/{$second->id}/verify", ['target' => 'clock_in']);
        $this->actingAs($this->repUser)->post("/hte/dtr/{$second->id}/verify", ['target' => 'clock_in'])->assertStatus(422);
    }

    public function test_a_target_is_required(): void
    {
        $dtr = $this->makeDtr();
        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify", [])->assertSessionHasErrors('target');
    }

    public function test_flagging_withdraws_only_the_flagged_events_verification(): void
    {
        $dtr = $this->makeDtr();
        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify", ['target' => 'clock_in']);

        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/flag", [
            'flag_target' => 'clock_out', 'flag_reason' => 'Time entry needs correction',
        ])->assertRedirect('/hte/dtr');

        $dtr->refresh();
        $this->assertSame('flagged', $dtr->status);
        $this->assertTrue($dtr->isClockInVerified());   // not flagged -> stays verified
        $this->assertFalse($dtr->isClockOutVerified());

        // Flagging clock-in withdraws that one too.
        $other = $this->makeDtr(['log_date' => '2026-10-03']);
        $this->actingAs($this->repUser)->post("/hte/dtr/{$other->id}/verify", ['target' => 'both']);
        $this->actingAs($this->repUser)->post("/hte/dtr/{$other->id}/flag", ['flag_target' => 'both', 'flag_reason' => 'x'])->assertStatus(422); // approved can't be flagged
    }

    public function test_a_flagged_entry_cannot_be_verified_until_the_student_resolves_it(): void
    {
        $dtr = $this->makeDtr();
        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/flag", ['flag_target' => 'both', 'flag_reason' => 'Other']);
        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify", ['target' => 'clock_in'])->assertStatus(422);

        // Student corrects it -> all verification cleared, back to pending.
        $this->actingAs($this->studentUser)->put("/student/dtr/{$dtr->id}/resolve", [
            'time_in' => '08:00', 'time_out' => '17:00',
        ])->assertRedirect();
        $dtr->refresh();
        $this->assertSame('pending', $dtr->status);
        $this->assertNull($dtr->clock_in_verified_at);
        $this->assertNull($dtr->clock_out_verified_at);
    }

    public function test_another_hte_cannot_verify_this_entry(): void
    {
        $otherHte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Other HTE']);
        $otherUser = $this->makeUser('hte_representative', 'R-2');
        HteRepresentative::forceCreate(['user_id' => $otherUser->id, 'hte_id' => $otherHte->id]);
        $dtr = $this->makeDtr();

        $this->actingAs($otherUser)->post("/hte/dtr/{$dtr->id}/verify", ['target' => 'both'])->assertStatus(403);
        $this->assertNull($dtr->fresh()->clock_in_verified_at);
    }

    // ===================== Weekly journal: schedule math =====================

    public function test_week_boundaries_and_due_dates_are_calculated_from_the_start_date(): void
    {
        $weeks = WeeklyJournalSchedule::weeks($this->internship->fresh(), CarbonImmutable::parse('2026-10-14'));

        $this->assertCount(3, $weeks);
        $this->assertSame('2026-09-28', $weeks[0]['start']->toDateString());
        $this->assertSame('2026-10-04', $weeks[0]['end']->toDateString());
        $this->assertSame('2026-10-02', $weeks[0]['due']->toDateString()); // Friday
        $this->assertSame('2026-10-09', $weeks[1]['due']->toDateString());
        $this->assertSame('missing', $weeks[0]['state']);
        $this->assertSame('missing', $weeks[1]['state']);
        $this->assertSame('open', $weeks[2]['state']);
    }

    public function test_due_soon_state_the_day_before_the_deadline(): void
    {
        $weeks = WeeklyJournalSchedule::weeks($this->internship->fresh(), CarbonImmutable::parse('2026-10-08')); // Thursday
        $this->assertSame('due_soon', $weeks->last()['state']);
        $this->assertSame('2026-10-09', $weeks->last()['due']->toDateString());
    }

    public function test_a_start_after_the_due_weekday_does_not_create_an_already_overdue_week_one(): void
    {
        $this->deployment->update(['start_date' => '2026-10-03']); // Saturday
        $weeks = WeeklyJournalSchedule::weeks($this->internship->fresh(), CarbonImmutable::parse('2026-10-14'));

        $this->assertSame('2026-10-05', $weeks[0]['start']->toDateString());
        $this->assertSame('2026-10-09', $weeks[0]['due']->toDateString());
    }

    public function test_due_weekday_is_configurable(): void
    {
        config(['internlink.journal_due_weekday' => 7]); // Sunday
        $weeks = WeeklyJournalSchedule::weeks($this->internship->fresh(), CarbonImmutable::parse('2026-10-04'));
        $this->assertSame('2026-10-04', $weeks[0]['due']->toDateString());
        $this->assertSame('due_soon', $weeks[0]['state']); // today is the due date
    }

    // ===================== Weekly journal: student upload =====================

    private function upload(int $week, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->studentUser)->post("/student/journals/week/{$week}", [
            'file' => UploadedFile::fake()->create('journal.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_an_on_time_upload_is_not_marked_late(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00:00')); // Thursday, before the Friday due date

        $this->upload(1)->assertRedirect('/student/journals');

        $journal = WeeklyJournal::firstOrFail();
        $this->assertSame(1, $journal->week_number);
        $this->assertSame('2026-10-02', $journal->due_date->toDateString());
        $this->assertFalse($journal->is_late);
        $this->assertSame('submitted', $journal->status);
        Storage::assertExists($journal->file_path);
    }

    public function test_the_due_date_itself_still_counts_as_on_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-02 23:30:00'));
        $this->upload(1);
        $this->assertFalse(WeeklyJournal::firstOrFail()->is_late);
    }

    public function test_a_late_upload_is_accepted_and_marked_late(): void
    {
        $this->upload(1)->assertRedirect('/student/journals'); // today is Sunday Oct 4, due was Fri Oct 2

        $this->assertTrue(WeeklyJournal::firstOrFail()->is_late);
    }

    public function test_a_future_week_cannot_be_uploaded(): void
    {
        $this->upload(2)->assertStatus(404);
        $this->assertSame(0, WeeklyJournal::count());
    }

    public function test_a_week_cannot_be_submitted_twice_but_a_returned_one_can_be_replaced_keeping_its_late_flag(): void
    {
        $this->upload(1);
        $this->upload(1)->assertStatus(422); // already awaiting review

        $journal = WeeklyJournal::firstOrFail();
        $oldPath = $journal->file_path;
        $journal->update(['status' => 'returned', 'instructor_feedback' => 'Missing signature']);

        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00'));
        $this->upload(1)->assertRedirect('/student/journals');

        $journal->refresh();
        $this->assertSame('submitted', $journal->status);
        $this->assertNull($journal->instructor_feedback);
        $this->assertTrue($journal->is_late);
        $this->assertNotSame($oldPath, $journal->file_path);
        Storage::assertMissing($oldPath);
        $this->assertSame(1, WeeklyJournal::count());

        $journal->update(['status' => 'approved']);
        $this->upload(1)->assertStatus(422);
    }

    public function test_pre_deployment_students_cannot_upload(): void
    {
        $this->internship->update(['status' => 'pre_deployment']);
        $this->upload(1)->assertStatus(403);
    }

    public function test_a_student_cannot_download_someone_elses_journal(): void
    {
        $this->upload(1);
        $journal = WeeklyJournal::firstOrFail();

        $otherUser = $this->makeUser('student', 'S-2');
        Student::forceCreate(['user_id' => $otherUser->id, 'campus_id' => $this->campus->id, 'program_id' => $this->program->id]);

        $this->actingAs($otherUser)->get("/student/journals/{$journal->id}/download")->assertStatus(403);
        $this->actingAs($this->studentUser)->get("/student/journals/{$journal->id}/download")->assertOk();
    }

    public function test_uploading_notifies_the_instructor_and_flags_lateness_in_the_title(): void
    {
        $this->upload(1);

        $note = Notification::where('user_id', $this->instructorUser->id)->where('type', 'journal')->firstOrFail();
        $this->assertStringContainsString('(LATE)', $note->title);
    }

    // ===================== Weekly journal: instructor review =====================

    public function test_instructor_can_approve_and_return_with_required_remarks(): void
    {
        $this->upload(1);
        $journal = WeeklyJournal::firstOrFail();

        $this->actingAs($this->instructorUser)->post("/instructor/journals/{$journal->id}/review", ['decision' => 'returned'])
            ->assertSessionHasErrors('remarks');
        $this->assertSame('submitted', $journal->fresh()->status);

        $this->actingAs($this->instructorUser)->post("/instructor/journals/{$journal->id}/review", ['decision' => 'returned', 'remarks' => 'Please sign it'])
            ->assertRedirect('/instructor/journals');
        $journal->refresh();
        $this->assertSame('returned', $journal->status);
        $this->assertSame('Please sign it', $journal->instructor_feedback);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->studentUser->id, 'type' => 'journal', 'title' => 'Weekly Journal Returned']);

        $this->upload(1);
        $this->actingAs($this->instructorUser)->post("/instructor/journals/{$journal->id}/review", ['decision' => 'approved']);
        $this->assertSame('approved', $journal->fresh()->status);
    }

    public function test_an_instructor_of_another_program_cannot_review(): void
    {
        $this->upload(1);
        $journal = WeeklyJournal::firstOrFail();

        $otherProgram = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSBA', 'name' => 'BSBA', 'required_hours' => 300]);
        $other = $this->makeUser('instructor', 'I-2');
        Instructor::forceCreate(['user_id' => $other->id, 'campus_id' => $this->campus->id, 'program_id' => $otherProgram->id]);

        $this->actingAs($other)->post("/instructor/journals/{$journal->id}/review", ['decision' => 'approved'])->assertStatus(403);
        $this->actingAs($other)->get("/instructor/journals/{$journal->id}/download")->assertStatus(403);
    }

    // ===================== Reminders =====================

    public function test_reminders_go_out_the_day_before_and_are_not_duplicated(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 08:00:00')); // Thursday; week 2 due Friday

        $this->artisan('journals:send-reminders')->assertSuccessful();
        $this->artisan('journals:send-reminders')->assertSuccessful(); // run twice

        $this->assertSame(1, Notification::where('user_id', $this->studentUser->id)->where('title', 'like', 'Weekly Journal due tomorrow%')->count());
    }

    public function test_no_reminder_when_the_week_is_already_submitted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 08:00:00'));
        $this->deployment->internship->weeklyJournals()->create([
            'week_number' => 2, 'week_start' => '2026-10-05', 'week_end' => '2026-10-11', 'due_date' => '2026-10-09',
            'file_path' => 'x.pdf', 'original_filename' => 'x.pdf', 'status' => 'submitted', 'submitted_at' => now(),
        ]);

        $this->artisan('journals:send-reminders')->assertSuccessful();
        $this->assertSame(0, Notification::where('title', 'like', 'Weekly Journal due tomorrow%')->count());
    }

    // ===================== My Documents gating =====================

    private function makeRequirement(string $code, string $stage): DocumentRequirement
    {
        return DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => $code, 'name' => $code, 'stage' => $stage,
            'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => true, 'is_required' => true, 'display_order' => 1,
        ]);
    }

    public function test_post_deployment_forms_stay_hidden_while_deployed_except_the_weekly_journal(): void
    {
        $this->makeRequirement('WEEKLY_JOURNAL', 'post_deployment');
        $this->makeRequirement('NARRATIVE_REPORT', 'post_deployment');
        $this->makeRequirement('PRE_FORM', 'pre_deployment');

        $this->actingAs($this->studentUser)->get('/student/documents')
            ->assertOk()->assertSee('WEEKLY_JOURNAL')->assertSee('PRE_FORM')->assertDontSee('NARRATIVE_REPORT');

        $this->internship->update(['status' => 'completed']);
        // Fresh user instance: the first request cached student->currentInternship on the old one.
        $this->actingAs(User::find($this->studentUser->id))->get('/student/documents')->assertOk()->assertSee('NARRATIVE_REPORT');
    }

    public function test_the_old_single_upload_path_is_closed_for_the_weekly_journal(): void
    {
        $req = $this->makeRequirement('WEEKLY_JOURNAL', 'post_deployment');

        $this->actingAs($this->studentUser)->post('/student/documents', [
            'document_requirement_id' => $req->id,
            'file' => UploadedFile::fake()->create('j.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);
    }

    // ===================== Pages render =====================

    public function test_every_touched_page_renders(): void
    {
        $this->upload(1);
        $dtr = $this->makeDtr(['time_out' => null, 'hours_logged' => 0, 'log_date' => '2026-10-04']);

        $this->actingAs($this->repUser)->get('/hte/dtr')->assertOk()->assertSee('Verify Clock-In')->assertDontSee('Verify Clock-Out');
        $dtr->update(['time_out' => '14:00:00']);
        $this->actingAs($this->repUser)->get('/hte/dtr')->assertOk()->assertSee('Verify Clock-Out')->assertSee('Verify Both');

        $this->actingAs($this->studentUser)->get('/student/journals')->assertOk()->assertSee('Week 1');
        $this->actingAs($this->studentUser)->get('/student/dashboard')->assertOk();
        $this->actingAs($this->studentUser)->get('/student/dtr')->assertOk()->assertDontSee('Task done today');
        $this->actingAs($this->instructorUser)->get('/instructor/journals')->assertOk()->assertSee('Awaiting review');
        $this->actingAs($this->instructorUser)->get('/instructor/documents')->assertOk();
    }
}
