<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
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
 * HTE verification of the clock-in and clock-out as separate steps.
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
}
