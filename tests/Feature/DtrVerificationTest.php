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
 * Form 17 – Internship Time Frame as the DTR: the student enters how many
 * hours they finished each day (no time in/out, no photo). The hours count
 * right away toward the rendered hours; the HTE and the instructor only view.
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

    private function enter(array $data)
    {
        return $this->actingAs($this->studentUser)->post(route('student.dtr.store'), $data);
    }

    private function rendered(): float
    {
        return (float) $this->student->fresh()->hours_rendered;
    }

    public function test_student_enters_the_hours_of_a_day_and_they_count_right_away(): void
    {
        $this->actingAs($this->studentUser)->get('/student/dtr')->assertOk()
            ->assertSee('Hours finished')->assertDontSee('Time in')->assertDontSee('Morning')->assertDontSee('photo');

        $this->enter(['log_date' => '2026-10-02', 'hours' => 8, 'notes' => 'Inventory'])
            ->assertRedirect('/student/dtr')->assertSessionHas('status');

        $dtr = Dtr::sole();
        $this->assertSame('approved', $dtr->status);
        $this->assertEquals(8, $dtr->hours_logged);
        $this->assertNull($dtr->am_in);
        $this->assertNull($dtr->time_in);
        $this->assertEquals(8, $this->rendered());

        $this->enter(['log_date' => '2026-10-03', 'hours' => 4.5])->assertRedirect();
        $this->assertEquals(12.5, $this->rendered());

        $this->actingAs($this->studentUser)->get('/student/dtr')->assertOk()
            ->assertSee('Oct 02, 2026')->assertSee('Inventory')->assertSee('12.5')
            ->assertSee(route('student.dtr.form', ['preview' => 1]), false);

        // The page previews the printable Form 17 (no print dialog in the preview).
        $this->actingAs($this->studentUser)->get(route('student.dtr.form', ['preview' => 1]))->assertOk()
            ->assertSee('INTERNSHIP TIME FRAME')->assertSee('Oct 03, 2026')->assertSee('12.5')
            ->assertDontSee('window.print()', false);
    }

    public function test_editing_a_day_adjusts_the_rendered_hours(): void
    {
        $this->enter(['log_date' => '2026-10-02', 'hours' => 8]);
        $dtr = Dtr::sole();

        $this->actingAs($this->studentUser)->get('/student/dtr?edit='.$dtr->id)->assertOk()->assertSee('Edit Oct 02, 2026');
        $this->enter(['dtr_id' => $dtr->id, 'log_date' => '2026-10-02', 'hours' => 6])->assertRedirect();

        $this->assertSame(1, Dtr::count());
        $this->assertEquals(6, $dtr->fresh()->hours_logged);
        $this->assertEquals(6, $this->rendered());

        // Entering the same date again updates it instead of adding a second day.
        $this->enter(['log_date' => '2026-10-02', 'hours' => 7]);
        $this->assertSame(1, Dtr::count());
        $this->assertEquals(7, $this->rendered());
    }

    public function test_removing_a_day_takes_its_hours_off(): void
    {
        $this->enter(['log_date' => '2026-10-02', 'hours' => 8]);
        $this->enter(['log_date' => '2026-10-03', 'hours' => 5]);
        $this->assertEquals(13, $this->rendered());

        $dtr = Dtr::whereDate('log_date', '2026-10-02')->sole();
        $this->actingAs($this->studentUser)->delete(route('student.dtr.destroy', $dtr))->assertRedirect('/student/dtr');

        $this->assertSame(1, Dtr::count());
        $this->assertEquals(5, $this->rendered());
    }

    public function test_wrong_hours_and_dates_are_rejected(): void
    {
        foreach ([
            ['log_date' => '2026-10-02', 'hours' => 25],
            ['log_date' => '2026-10-02', 'hours' => 0],
            ['log_date' => '2026-10-02', 'hours' => 'abc'],
            ['log_date' => '2026-10-02'],
        ] as $bad) {
            $this->enter($bad)->assertSessionHasErrors('hours');
        }
        $this->enter(['log_date' => '2026-10-05', 'hours' => 8])->assertSessionHasErrors('log_date');   // future
        $this->enter(['log_date' => '2026-09-29', 'hours' => 8])->assertSessionHasErrors('log_date');   // before deployment

        $this->assertSame(0, Dtr::count());
        $this->assertEquals(0, $this->rendered());
    }

    public function test_a_day_cannot_be_moved_onto_a_date_already_entered(): void
    {
        $this->enter(['log_date' => '2026-10-02', 'hours' => 8]);
        $this->enter(['log_date' => '2026-10-03', 'hours' => 4]);
        $second = Dtr::whereDate('log_date', '2026-10-03')->sole();

        $this->enter(['dtr_id' => $second->id, 'log_date' => '2026-10-02', 'hours' => 4])->assertSessionHasErrors('log_date');
        $this->assertEquals(12, $this->rendered());
    }

    public function test_days_cannot_be_changed_after_the_deployment_ends_or_by_another_student(): void
    {
        $this->enter(['log_date' => '2026-10-02', 'hours' => 8]);
        $dtr = Dtr::sole();

        $otherUser = $this->makeUser('student', 'S-2');
        Student::forceCreate(['user_id' => $otherUser->id, 'campus_id' => $this->campus->id, 'program_id' => $this->program->id]);
        $this->actingAs($otherUser)->delete(route('student.dtr.destroy', $dtr))->assertForbidden();

        $this->deployment->forceFill(['status' => 'completed'])->save();
        $this->studentUser = $this->studentUser->fresh();
        $this->enter(['log_date' => '2026-10-03', 'hours' => 8])->assertStatus(422);
        $this->actingAs($this->studentUser)->delete(route('student.dtr.destroy', $dtr))->assertStatus(422);
        $this->assertEquals(8, $this->rendered());
    }

    public function test_the_hte_portal_has_only_interns_and_evaluations(): void
    {
        $this->enter(['log_date' => '2026-10-02', 'hours' => 8, 'notes' => 'Front desk']);
        $this->repUser->forceFill(['must_change_password' => true])->save();

        // Lands on Evaluations; no forced password change.
        $this->actingAs($this->repUser)->get(route('hte.evaluations'))->assertOk()
            ->assertSee('Interns')->assertSee('Evaluations')
            ->assertDontSee('Timeframe')->assertDontSee('My Profile')->assertDontSee('ti-layout-dashboard', false);
        $this->assertSame('hte.evaluations', $this->repUser->fresh()->dashboardRouteName());

        // Interns: no Time Frame column.
        $this->actingAs($this->repUser)->get(route('hte.students'))->assertOk()->assertDontSee('View Days');

        // The removed pages send the HTE to Evaluations.
        foreach (['/hte/dashboard', '/hte/dtr', '/hte/profile', '/hte/notifications'] as $url) {
            $this->actingAs($this->repUser)->get($url)->assertRedirect('/hte/evaluations');
        }

        // HTE accounts can't change their password.
        $this->actingAs($this->repUser)->get(route('password.change'))->assertRedirect(route('hte.evaluations'));
        $this->actingAs($this->repUser)->put(route('password.update'), ['password' => 'NewPass123!', 'password_confirmation' => 'NewPass123!'])
            ->assertRedirect(route('hte.evaluations'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('x', $this->repUser->fresh()->password));

        // ... and can't reset it through Forgot Password.
        auth()->logout();
        $this->post(route('password.forgot.send'), ['id_number' => 'R-1'])->assertSessionHasErrors('id_number');
    }

    public function test_instructor_monitoring_shows_the_days(): void
    {
        $this->enter(['log_date' => '2026-10-02', 'hours' => 8, 'notes' => 'Front desk']);

        // The instructor sees the printable Form 17 (preview), not a table.
        $this->actingAs($this->instructorUser)->get('/instructor/monitoring?student='.$this->student->id)->assertOk()
            ->assertSee(route('instructor.monitoring.form', ['student' => $this->student->id, 'preview' => 1]), false)
            ->assertDontSee('Total rendered')->assertDontSee('Return');
        $this->actingAs($this->instructorUser)->get(route('instructor.monitoring.form', ['student' => $this->student->id, 'preview' => 1]))->assertOk()
            ->assertSee('INTERNSHIP TIME FRAME')->assertSee('Oct 02, 2026')->assertSee('width:8.27in', false)->assertDontSee('window.print()', false);
    }

    public function test_form_17_is_printed_from_the_days_with_nothing_to_submit(): void
    {
        $requirement = DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'INTERNSHIP_TIME_FRAME', 'name' => 'Form 17 – Internship Time Frame', 'form_number' => '17',
            'stage' => 'active', 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => false, 'is_repeating' => true, 'display_order' => 17,
        ]);
        $this->enter(['log_date' => '2026-10-02', 'hours' => 8])->assertRedirect();
        $this->enter(['log_date' => '2026-10-03', 'hours' => 3.5])->assertRedirect();

        // No sending to the instructor any more.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('student.dtr.submit'));
        $this->actingAs($this->studentUser)->get('/student/dtr')->assertOk()
            ->assertSee('there is nothing to submit')->assertSee('Download Form 17')->assertDontSee('Send Form 17');

        // The student prints or downloads it straight from the approved days, any time.
        $this->actingAs($this->studentUser)->get(route('student.dtr.form'))->assertOk()
            ->assertSee('INTERNSHIP TIME FRAME')->assertSee('Oct 02, 2026')->assertSee('Friday')->assertSee('Oct 03, 2026')->assertSee('11.5')
            ->assertSee('window.print()', false);
        $this->actingAs($this->studentUser)->get(route('student.dtr.form', ['download' => 1]))->assertOk()
            ->assertSee('pdfStatus', false)->assertDontSee('onload="window.print()"', false);
        $this->assertSame(0, Document::count());

        // My Documents: the same Form 17 card as the Time Frame page (preview, View / Download), nothing to submit or upload.
        $this->actingAs($this->studentUser)->get('/student/documents?form='.$requirement->id)->assertOk()
            ->assertSee('11.5 / 300 hours rendered')->assertDontSee('Time frame log')
            ->assertSee(route('student.dtr.form', ['preview' => 1]), false)->assertSee('data-tf-mode="view"', false)
            ->assertSee('Which part of your time frame?')
            ->assertSee('There is nothing to submit')->assertDontSee('Instructor feedback')->assertDontSee('Upload Signed Copy');

        // The instructor: not in Document Review; viewed in Time Frame monitoring (view and download only).
        $this->actingAs($this->instructorUser)->get('/instructor/documents?student='.$this->student->id)->assertOk()
            ->assertDontSee('Form 17 – Internship Time Frame');
        $this->actingAs($this->instructorUser)->get('/instructor/monitoring?student='.$this->student->id)->assertOk()
            ->assertSee('Form 17 for')->assertSee('Whole time frame')->assertSee(route('instructor.monitoring.form', $this->student));
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

    public function test_more_than_28_days_continue_on_another_full_sheet(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00'));
        $start = CarbonImmutable::parse('2026-09-30');
        for ($i = 0; $i < 30; $i++) {
            Dtr::forceCreate(['deployment_id' => $this->deployment->id, 'log_date' => $start->addDays($i)->toDateString(),
                'hours_logged' => 8, 'approved_hours' => 8, 'status' => 'approved']);
        }

        // Whole form: two sheets, each with its own header, total and signatures.
        $html = $this->actingAs($this->studentUser)->get(route('student.dtr.form', ['download' => 1]))->assertOk()
            ->assertSee('Sheet 1 of 2')->assertSee('Sheet 2 of 2')->getContent();
        $this->assertSame(2, substr_count($html, 'INTERNSHIP TIME FRAME</h1>'));
        $this->assertSame(2, substr_count($html, 'TOTAL NUMBER OF HOURS'));
        $this->assertSame(2, substr_count($html, 'Prepared by:'));
        $this->assertStringContainsString('<td class="center">224</td>', $html);   // 28 x 8
        $this->assertStringContainsString('<td class="center">16</td>', $html);    // 2 x 8

        // Only sheet 2.
        $this->actingAs($this->studentUser)->get(route('student.dtr.form', ['download' => 1, 'sheet' => 2]))->assertOk()
            ->assertSee('Sheet 2 of 2')->assertDontSee('Sheet 1 of 2')
            ->assertSee('Oct 28, 2026')->assertDontSee('Sep 30, 2026');
        $this->actingAs($this->studentUser)->get(route('student.dtr.form', ['download' => 1, 'sheet' => 3]))->assertNotFound();

        // What the Print / Download dialog sends.
        $this->actingAs($this->studentUser)->get(route('student.dtr.form', ['view' => 1, 'period' => 'sheet:2']))->assertOk()
            ->assertSee('Sheet 2 of 2')->assertDontSee('Sep 30, 2026')
            ->assertDontSee('onload="window.print()"', false)->assertSee('onclick="window.print()"', false);
        $this->actingAs($this->studentUser)->get('/student/dtr')->assertOk()
            ->assertSee('data-tf-mode="view"', false)->assertDontSee('data-tf-mode="print"', false);
        $this->actingAs($this->studentUser)->get(route('student.dtr.form', ['download' => 1, 'period' => 'week:2026-10-28']))->assertOk()
            ->assertSee('Oct 28, 2026')->assertDontSee('Oct 27, 2026')->assertSee('pdfStatus', false);

        // The Time Frame page offers each sheet; the instructor's picker too.
        $this->actingAs($this->studentUser)->get('/student/dtr')->assertOk()
            ->assertSee('Which part of your time frame?')->assertSee('value="sheet:2"', false)->assertSee('value="week:', false)
            ->assertDontSee('Download by sheet')->assertDontSee('Download by week')
            ->assertSee('Sheet 2 of 2')->assertSee(route('student.dtr.form', ['preview' => 1, 'sheet' => 1]));
        $this->actingAs($this->instructorUser)->get('/instructor/monitoring?student='.$this->student->id)->assertOk()
            ->assertSee('value="sheet:2"', false);
        $this->actingAs($this->instructorUser)->get(route('instructor.monitoring.form', ['student' => $this->student, 'period' => 'sheet:2']))->assertOk()
            ->assertSee('Sheet 2 of 2')->assertDontSee('Sep 30, 2026');
    }
}
