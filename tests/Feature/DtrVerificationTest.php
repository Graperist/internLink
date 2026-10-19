<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Notification;
use App\Models\Dtr;
use App\Models\Hte;
use App\Models\HteRepresentative;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Form 17 – Internship Time Frame as the DTR: the student types the morning
 * and afternoon time in/out, the system totals it, the HTE supervisor
 * approves or declines each day, the instructor can return a day.
 */
class DtrVerificationTest extends TestCase
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

    private function day(array $attrs = []): array
    {
        return $attrs + ['log_date' => '2026-10-02', 'am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '16:00'];
    }

    private function makeDtr(array $attrs = []): Dtr
    {
        return Dtr::forceCreate($attrs + [
            'deployment_id' => $this->deployment->id, 'log_date' => '2026-10-02',
            'am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00',
            'time_in' => '08:00', 'time_out' => '17:00', 'hours_logged' => 8, 'status' => 'pending',
        ]);
    }

    public function test_student_enters_a_day_and_the_system_totals_it_without_the_lunch_break(): void
    {
        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day())->assertRedirect('/student/dtr?date=2026-10-02')->assertSessionHasNoErrors();

        $dtr = Dtr::sole();
        $this->assertSame('pending', $dtr->status);
        $this->assertEquals(7, (float) $dtr->hours_logged);   // undertime: 4 + 3 hours
        $this->assertSame('8:00 AM - 12:00 PM', $dtr->period('am'));
        $this->assertSame('1:00 PM - 4:00 PM', $dtr->period('pm'));
        $this->assertTrue(Notification::where('user_id', $this->repUser->id)->where('resolve_key', 'like', 'dtr:'.$dtr->id.':%')->exists());

        $this->actingAs($this->studentUser)->get('/student/dtr')->assertOk()
            ->assertSee('Form 17 – Internship Time Frame')->assertSee('Waiting for supervisor')->assertDontSee('GPS');
    }

    public function test_a_half_day_counts_only_the_morning(): void
    {
        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day(['pm_in' => null, 'pm_out' => null]))->assertSessionHasNoErrors();
        $this->assertEquals(4, (float) Dtr::sole()->hours_logged);
    }

    public function test_morning_and_afternoon_are_saved_separately_into_one_day(): void
    {
        $this->actingAs($this->studentUser)->post('/student/dtr', ['part' => 'am', 'log_date' => '2026-10-02', 'am_in' => '07:00', 'am_out' => '12:00'])
            ->assertRedirect('/student/dtr?date=2026-10-02')->assertSessionHasNoErrors();
        $dtr = Dtr::sole();
        $this->assertEquals(5, (float) $dtr->hours_logged);
        $this->assertNull($dtr->pm_in);

        // Later the same day: the afternoon is added to the same day, the morning is kept.
        $this->actingAs($this->studentUser)->post('/student/dtr', ['part' => 'pm', 'log_date' => '2026-10-02', 'pm_in' => '13:00', 'pm_out' => '16:00', 'notes' => 'Left early'])
            ->assertSessionHasNoErrors();
        $dtr = Dtr::sole();
        $this->assertSame('07:00', substr($dtr->am_in, 0, 5));
        $this->assertSame('16:00', substr($dtr->pm_out, 0, 5));
        $this->assertEquals(8, (float) $dtr->hours_logged);
        $this->assertSame('Left early', $dtr->notes);
        $this->assertSame('pending', $dtr->status);

        // The supervisor got a notice for the morning and another for the afternoon.
        $notices = Notification::where('user_id', $this->repUser->id)->where('resolve_key', 'like', 'dtr:'.$dtr->id.':%')->orderBy('id')->get();
        $this->assertCount(2, $notices);
        $this->assertStringStartsWith('Morning time in/out', $notices[0]->title);
        $this->assertStringContainsString('7:00 AM - 12:00 PM', $notices[0]->message);
        $this->assertStringStartsWith('Afternoon time in/out', $notices[1]->title);
        $this->assertStringContainsString('1:00 PM - 4:00 PM', $notices[1]->message);

        // Correcting only the morning keeps the afternoon and the note.
        $this->actingAs($this->studentUser)->post('/student/dtr', ['part' => 'am', 'log_date' => '2026-10-02', 'am_in' => '08:00', 'am_out' => '12:00'])->assertSessionHasNoErrors();
        $dtr->refresh();
        $this->assertEquals(7, (float) $dtr->hours_logged);
        $this->assertSame('Left early', $dtr->notes);
        $this->assertSame(3, Notification::where('user_id', $this->repUser->id)->where('resolve_key', 'like', 'dtr:'.$dtr->id.':%')->count());

        // The afternoon must start after the saved morning ends.
        $this->actingAs($this->studentUser)->post('/student/dtr', ['part' => 'pm', 'log_date' => '2026-10-02', 'pm_in' => '11:00', 'pm_out' => '16:00'])->assertSessionHasErrors('pm_in');

        $this->actingAs($this->studentUser)->get('/student/dtr?date=2026-10-02')->assertOk()
            ->assertSee('Save morning and resend')->assertSee('Save afternoon and resend')->assertSee('value="08:00"', false);

        // Once approved, neither half can be changed, and the supervisor's notices clear.
        $dtr->update(['status' => 'approved']);
        $this->assertSame(0, Notification::where('user_id', $this->repUser->id)->where('resolve_key', 'like', 'dtr:'.$dtr->id.'%')->count());
        $this->actingAs($this->studentUser)->post('/student/dtr', ['part' => 'pm', 'log_date' => '2026-10-02', 'pm_in' => '13:00', 'pm_out' => '17:00'])->assertSessionHasErrors('pm_in');
        $this->actingAs($this->studentUser)->get('/student/dtr?date=2026-10-02')->assertOk()->assertSee('can no longer be changed')->assertDontSee('Save afternoon');
    }

    public function test_wrong_times_are_rejected(): void
    {
        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day(['am_out' => '07:00']))->assertSessionHasErrors('am_out');
        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day(['pm_in' => '11:00']))->assertSessionHasErrors('pm_in');
        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day(['am_out' => null]))->assertSessionHasErrors('am_out');
        $this->actingAs($this->studentUser)->post('/student/dtr', ['log_date' => '2026-10-02'])->assertSessionHasErrors();
        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day(['log_date' => '2026-10-05']))->assertSessionHasErrors('log_date');  // future
        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day(['log_date' => '2026-09-29']))->assertSessionHasErrors('log_date');  // before deployment
        $this->assertSame(0, Dtr::count());
    }

    public function test_one_entry_per_date(): void
    {
        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day());
        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day())->assertSessionHasErrors('log_date');
        $this->assertSame(1, Dtr::count());
    }

    public function test_supervisor_approval_counts_the_hours_once(): void
    {
        $dtr = $this->makeDtr();

        $this->actingAs($this->repUser)->get('/hte/dtr')->assertOk()->assertSee('Waiting for your approval')->assertSee('8:00 AM - 12:00 PM');
        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify")->assertRedirect('/hte/dtr');
        $this->assertSame('approved', $dtr->fresh()->status);
        $this->assertEquals(8, (float) $this->student->fresh()->hours_rendered);

        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/verify")->assertStatus(422);
        $this->assertEquals(8, (float) $this->student->fresh()->hours_rendered);
    }

    public function test_declined_day_goes_back_to_the_student_who_corrects_it(): void
    {
        $dtr = $this->makeDtr();

        $this->actingAs($this->repUser)->post("/hte/dtr/{$dtr->id}/flag", ['flag_reason' => 'The student was undertime', 'flag_note' => 'You left at 4 PM.'])->assertRedirect('/hte/dtr');
        $dtr->refresh();
        $this->assertSame('flagged', $dtr->status);
        $this->assertSame($this->repUser->id, $dtr->declined_by_user_id);
        $this->assertTrue(Notification::where('user_id', $this->studentUser->id)->where('resolve_key', 'dtr:'.$dtr->id)->exists());

        $this->actingAs($this->studentUser)->get('/student/dtr?edit='.$dtr->id)->assertOk()->assertSee('The student was undertime')->assertSee('Save afternoon and resend');

        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day(['dtr_id' => $dtr->id, 'pm_out' => '16:00']))->assertSessionHasNoErrors();
        $dtr->refresh();
        $this->assertSame('pending', $dtr->status);
        $this->assertEquals(7, (float) $dtr->hours_logged);
        $this->assertNotNull($dtr->resolved_at);
        $this->assertSame(1, Dtr::count());
    }

    public function test_approved_day_is_locked_for_the_student(): void
    {
        $dtr = $this->makeDtr(['status' => 'approved']);

        $this->actingAs($this->studentUser)->post('/student/dtr', $this->day(['dtr_id' => $dtr->id]))->assertSessionHasErrors('log_date');
        $this->actingAs($this->studentUser)->delete("/student/dtr/{$dtr->id}")->assertStatus(422);
        $this->assertEquals(8, (float) $dtr->fresh()->hours_logged);
    }

    public function test_student_can_remove_a_waiting_day(): void
    {
        $dtr = $this->makeDtr();
        $this->actingAs($this->studentUser)->delete("/student/dtr/{$dtr->id}")->assertRedirect('/student/dtr');
        $this->assertSame(0, Dtr::count());
    }

    public function test_another_hte_cannot_approve(): void
    {
        $other = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Other']);
        $otherUser = $this->makeUser('hte_representative', 'R-2');
        HteRepresentative::forceCreate(['user_id' => $otherUser->id, 'hte_id' => $other->id]);
        $dtr = $this->makeDtr();

        $this->actingAs($otherUser)->post("/hte/dtr/{$dtr->id}/verify")->assertForbidden();
    }

    public function test_instructor_sees_only_approved_days_and_cannot_return_them(): void
    {
        $this->makeDtr(['status' => 'approved']);
        $this->makeDtr(['log_date' => '2026-10-03', 'am_in' => '07:15', 'am_out' => '11:15', 'pm_in' => null, 'pm_out' => null, 'hours_logged' => 4]);   // waiting
        $this->makeDtr(['log_date' => '2026-10-01', 'am_in' => '09:45', 'am_out' => '11:45', 'pm_in' => null, 'pm_out' => null, 'hours_logged' => 2, 'status' => 'flagged', 'flag_reason' => 'Absent']);

        $this->actingAs($this->instructorUser)->get('/instructor/monitoring?student='.$this->student->id)->assertOk()
            ->assertSee('Time Frame Monitoring')->assertSee('1:00 PM - 5:00 PM')->assertSee('1 approved day')
            ->assertDontSee('7:15 AM')->assertDontSee('9:45 AM')->assertDontSee('Return');

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('instructor.monitoring.return'));
    }

    public function test_form_17_is_just_the_approved_time_frame_with_nothing_to_submit(): void
    {
        $requirement = DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'INTERNSHIP_TIME_FRAME', 'name' => 'Form 17 – Internship Time Frame', 'form_number' => '17',
            'stage' => 'active', 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => false, 'is_repeating' => true, 'display_order' => 17,
        ]);
        $this->makeDtr(['status' => 'approved']);
        $this->makeDtr(['log_date' => '2026-10-03', 'am_in' => '08:00', 'am_out' => '11:30', 'pm_in' => null, 'pm_out' => null, 'hours_logged' => 3.5, 'status' => 'approved']);
        $this->makeDtr(['log_date' => '2026-10-01', 'am_in' => '09:10', 'am_out' => '11:00', 'pm_in' => null, 'pm_out' => null, 'hours_logged' => 2]);   // waiting

        // No sending to the instructor any more.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('student.dtr.submit'));
        $this->actingAs($this->studentUser)->get('/student/dtr')->assertOk()
            ->assertSee('There is nothing to submit')->assertSee('Download Form 17')->assertDontSee('Send Form 17');

        // The student prints or downloads it straight from the approved days, any time.
        $this->actingAs($this->studentUser)->get(route('student.dtr.form'))->assertOk()
            ->assertSee('INTERNSHIP TIME FRAME')->assertSee('Oct 02, 2026')->assertSee('Friday')->assertSee('Oct 03, 2026')->assertSee('11.5')
            ->assertDontSee('Oct 01, 2026')->assertSee('window.print()', false);
        $this->actingAs($this->studentUser)->get(route('student.dtr.form', ['download' => 1]))->assertOk()
            ->assertSee('pdfStatus', false)->assertDontSee('onload="window.print()"', false);
        $this->assertSame(0, Document::count());

        // My Documents: listed with the approved hours and the log, but nothing to submit or upload.
        $this->actingAs($this->studentUser)->get('/student/documents?form='.$requirement->id)->assertOk()
            ->assertSee('11.5 / 300 hours approved')->assertSee('Time frame log')->assertSee('2 approved days')
            ->assertSee('There is nothing to submit')->assertDontSee('Instructor feedback')->assertDontSee('Upload Signed Copy');

        // The instructor: not in Document Review; viewed in Time Frame monitoring (view and download only).
        $this->actingAs($this->instructorUser)->get('/instructor/documents?student='.$this->student->id)->assertOk()
            ->assertDontSee('Form 17 – Internship Time Frame');
        $this->actingAs($this->instructorUser)->get('/instructor/monitoring?student='.$this->student->id)->assertOk()
            ->assertSee('View Form 17')->assertSee(route('instructor.monitoring.form', ['student' => $this->student, 'download' => 1]));
        $this->actingAs($this->instructorUser)->get(route('instructor.monitoring.form', $this->student))->assertOk()
            ->assertSee('Oct 03, 2026')->assertDontSee('window.print()', false);
        $this->actingAs($this->instructorUser)->get(route('instructor.monitoring.form', ['student' => $this->student, 'download' => 1]))->assertOk()
            ->assertSee('pdfStatus', false);

        // Another instructor's program cannot open it.
        $otherProgram = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSHM', 'name' => 'BSHM', 'required_hours' => 300]);
        $other = $this->makeUser('instructor', 'I-2');
        Instructor::forceCreate(['user_id' => $other->id, 'campus_id' => $this->campus->id, 'program_id' => $otherProgram->id]);
        $this->actingAs($other)->get(route('instructor.monitoring.form', $this->student))->assertForbidden();

        // The old log page sends the student to the time frame.
        $this->actingAs($this->studentUser)->get("/student/documents/{$requirement->id}/log")->assertRedirect('/student/dtr');
    }
}
