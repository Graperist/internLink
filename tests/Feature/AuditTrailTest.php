<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Hte;
use App\Models\HteRepresentative;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private array $roles = [];

    private Campus $campusA;

    private Campus $campusB;

    private Program $programA;

    private Program $programB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['coordinator', 'instructor', 'hte_representative', 'student'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }

        $this->campusA = Campus::forceCreate(['code' => 'AAA', 'name' => 'Campus A', 'status' => 'active']);
        $this->campusB = Campus::forceCreate(['code' => 'BBB', 'name' => 'Campus B', 'status' => 'active']);

        $this->programA = Program::forceCreate(['campus_id' => $this->campusA->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 300]);
        $this->programB = Program::forceCreate(['campus_id' => $this->campusA->id, 'code' => 'BSBA', 'name' => 'BSBA', 'required_hours' => 300]);
    }

    // ---------- fixtures ----------

    private function makeUser(string $role, Campus $campus, string $idNumber): User
    {
        return User::forceCreate([
            'campus_id' => $campus->id,
            'role_id' => $this->roles[$role]->id,
            'id_number' => $idNumber,
            'name' => "{$role} {$idNumber}",
            'email' => "{$idNumber}@test.local",
            'password' => Hash::make('correct-password'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
    }

    private function makeStudent(Campus $campus, Program $program, string $idNumber): User
    {
        $user = $this->makeUser('student', $campus, $idNumber);
        Student::forceCreate(['user_id' => $user->id, 'campus_id' => $campus->id, 'program_id' => $program->id]);

        return $user;
    }

    private function makeInstructor(Campus $campus, Program $program, string $idNumber): User
    {
        $user = $this->makeUser('instructor', $campus, $idNumber);
        Instructor::forceCreate(['user_id' => $user->id, 'campus_id' => $campus->id, 'program_id' => $program->id]);

        return $user;
    }

    private function seedLog(User $user, string $description = 'Something happened', string $action = 'page.view'): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $user->id,
            'campus_id' => $user->campus_id,
            'role_code' => $user->role->code,
            'action' => $action,
            'description' => $description,
        ]);
    }

    private function feedDescriptions($response): array
    {
        return collect($response->json('items'))->pluck('description')->all();
    }

    // ---------- sign-in / sign-out ----------

    public function test_successful_login_and_logout_are_recorded(): void
    {
        $user = $this->makeUser('coordinator', $this->campusA, 'C-001');

        $this->post('/login', [
            'id_number' => 'C-001', 'password' => 'correct-password',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.login', 'campus_id' => $this->campusA->id]);

        $this->post('/logout');

        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.logout']);
    }

    public function test_failed_login_is_recorded_with_the_real_reason_but_screen_stays_generic(): void
    {
        $user = $this->makeUser('coordinator', $this->campusA, 'C-001');

        $this->post('/login', [
            'id_number' => 'C-001', 'password' => 'hunter2-not-the-password',
        ])->assertSessionHasErrors('id_number');

        $log = ActivityLog::where('action', 'auth.login_failed')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertStringContainsString('wrong password', $log->description);

        // Unknown ID: no user to attach to, but the campus named inside the
        // ID ("AAA") still gets it, so that campus's coordinator can see it.
        $this->post('/login', [
            'id_number' => '23-AAA-9999', 'password' => 'whatever',
        ]);

        $unknown = ActivityLog::where('action', 'auth.login_failed')->latest('id')->first();
        $this->assertNull($unknown->user_id);
        $this->assertSame($this->campusA->id, $unknown->campus_id);
        $this->assertStringContainsString('23-AAA-9999', $unknown->description);
    }

    public function test_passwords_and_request_bodies_are_never_stored(): void
    {
        $this->makeUser('coordinator', $this->campusA, 'C-001');

        $this->post('/login', [
            'id_number' => 'C-001', 'password' => 'hunter2-not-the-password',
        ]);

        foreach (ActivityLog::all() as $log) {
            $this->assertStringNotContainsString('hunter2', json_encode($log->toArray()));
        }
    }

    // ---------- automatic request logging ----------

    public function test_page_views_are_logged_in_plain_language(): void
    {
        $coordinator = $this->makeUser('coordinator', $this->campusA, 'C-001');

        $this->actingAs($coordinator)->get(route('coordinator.dashboard'))->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $coordinator->id,
            'action' => 'page.view',
            'description' => 'Opened the dashboard',
            'route_name' => 'coordinator.dashboard',
            'method' => 'GET',
        ]);
    }

    public function test_attempting_a_page_outside_your_role_is_logged_as_a_security_event(): void
    {
        $student = $this->makeStudent($this->campusA, $this->programA, 'S-001');

        $this->actingAs($student)->get('/coordinator/dashboard')->assertForbidden();

        $log = ActivityLog::where('action', 'security.denied')->firstOrFail();
        $this->assertSame($student->id, $log->user_id);
        $this->assertStringContainsString('/coordinator/dashboard', $log->description);
        $this->assertSame('security', $log->kind);
    }

    public function test_a_form_rejected_by_validation_is_labelled_as_such(): void
    {
        $coordinator = $this->makeUser('coordinator', $this->campusA, 'C-001');

        $this->actingAs($coordinator)->post(route('coordinator.students.store'), [])
            ->assertSessionHasErrors();

        $log = ActivityLog::where('action', 'action.post')->firstOrFail();
        $this->assertSame('Added an intern - rejected (validation failed)', $log->description);
    }

    public function test_the_audit_endpoints_do_not_log_themselves(): void
    {
        $coordinator = $this->makeUser('coordinator', $this->campusA, 'C-001');

        $this->actingAs($coordinator)->getJson(route('audit.feed'))->assertOk();
        $this->actingAs($coordinator)->post(route('audit.click'), ['label' => 'X', 'type' => 'button']);

        $this->assertDatabaseMissing('activity_logs', ['route_name' => 'audit.feed']);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'page.view', 'route_name' => 'audit.click']);
    }

    // ---------- students must not see it ----------

    public function test_students_cannot_read_the_feed_even_by_calling_it_directly(): void
    {
        $student = $this->makeStudent($this->campusA, $this->programA, 'S-001');

        $this->actingAs($student)->getJson(route('audit.feed'))->assertForbidden();
    }

    public function test_guests_cannot_read_the_feed(): void
    {
        $this->getJson(route('audit.feed'))->assertUnauthorized();
    }

    public function test_popup_is_on_staff_screens_and_never_on_the_student_screen(): void
    {
        $coordinator = $this->makeUser('coordinator', $this->campusA, 'C-001');
        $student = $this->makeStudent($this->campusA, $this->programA, 'S-001');

        $this->actingAs($coordinator)->get(route('coordinator.dashboard'))
            ->assertSee('id="ilAudit"', false)
            ->assertSee('audit-trail.js', false);

        $this->actingAs($student)->get(route('student.notifications'))
            ->assertOk()
            ->assertDontSee('ilAudit', false)
            ->assertDontSee('audit-trail.js', false)
            ->assertDontSee('Audit Trail');
    }

    // ---------- who sees what ----------

    public function test_coordinator_sees_their_whole_campus_and_nothing_from_other_campuses(): void
    {
        $coordA = $this->makeUser('coordinator', $this->campusA, 'CA-1');
        $studentA = $this->makeStudent($this->campusA, $this->programA, 'SA-1');
        $coordB = $this->makeUser('coordinator', $this->campusB, 'CB-1');

        $this->seedLog($studentA, 'A student did a thing');
        $this->seedLog($coordB, 'Other campus did a thing');

        $seen = $this->feedDescriptions($this->actingAs($coordA)->getJson(route('audit.feed')));

        $this->assertContains('A student did a thing', $seen);
        $this->assertNotContains('Other campus did a thing', $seen);
    }

    public function test_instructor_sees_themselves_and_their_advisees_only(): void
    {
        $instructor = $this->makeInstructor($this->campusA, $this->programA, 'I-1');
        $advisee = $this->makeStudent($this->campusA, $this->programA, 'S-adv');
        $otherProgramStudent = $this->makeStudent($this->campusA, $this->programB, 'S-other');
        $otherInstructor = $this->makeInstructor($this->campusA, $this->programB, 'I-2');

        $this->seedLog($instructor, 'mine');
        $this->seedLog($advisee, 'advisee');
        $this->seedLog($otherProgramStudent, 'not my student');
        $this->seedLog($otherInstructor, 'another instructor');

        $seen = $this->feedDescriptions($this->actingAs($instructor)->getJson(route('audit.feed')));

        $this->assertEqualsCanonicalizing(['mine', 'advisee'], $seen);
    }

    public function test_hte_representatives_cannot_read_the_feed_and_get_no_popup(): void
    {
        $repUser = $this->makeUser('hte_representative', $this->campusA, 'H-1');
        $hte = Hte::forceCreate(['campus_id' => $this->campusA->id, 'name' => 'Acme']);
        HteRepresentative::forceCreate(['user_id' => $repUser->id, 'hte_id' => $hte->id]);
        $student = $this->makeStudent($this->campusA, $this->programA, 'S-1');

        $this->actingAs($repUser)->getJson(route('audit.feed'))->assertForbidden();

        $this->actingAs($repUser)->get(route('hte.dashboard'))
            ->assertOk()
            ->assertDontSee('ilAudit', false)
            ->assertDontSee('audit-trail.js', false)
            ->assertSee('il-audit-tracker', false); // still tracked, just not shown
    }

    // ---------- feed paging ----------

    public function test_feed_pages_backwards_and_refreshes_forwards(): void
    {
        $coordinator = $this->makeUser('coordinator', $this->campusA, 'C-001');

        foreach (range(1, 45) as $i) {
            $this->seedLog($coordinator, "entry {$i}");
        }

        $first = $this->actingAs($coordinator)->getJson(route('audit.feed'))->assertOk();
        $this->assertCount(40, $first->json('items'));
        $this->assertTrue($first->json('has_more'));
        $this->assertSame('entry 45', $first->json('items.0.description')); // newest first

        $older = $this->getJson(route('audit.feed', ['before_id' => $first->json('items.39.id')]));
        $this->assertCount(5, $older->json('items'));
        $this->assertFalse($older->json('has_more'));

        $latest = $first->json('latest_id');
        $this->seedLog($coordinator, 'brand new');
        $fresh = $this->getJson(route('audit.feed', ['after_id' => $latest]));
        $this->assertSame(['brand new'], $this->feedDescriptions($fresh));
    }

    // ---------- click beacon ----------

    public function test_click_beacon_records_a_click_for_the_signed_in_user_only(): void
    {
        $student = $this->makeStudent($this->campusA, $this->programA, 'S-001');
        $other = $this->makeStudent($this->campusA, $this->programA, 'S-002');

        $this->actingAs($student)->post(route('audit.click'), [
            'label' => 'Clock In', 'type' => 'submit', 'page' => '/student/dtr',
            'user_id' => $other->id, // a forged attempt to blame someone else
        ])->assertNoContent();

        $log = ActivityLog::where('action', 'ui.click')->firstOrFail();
        $this->assertSame($student->id, $log->user_id);
        $this->assertSame('Clicked the "Clock In" button (on /student/dtr)', $log->description);
        $this->assertSame('click', $log->kind);
    }

    public function test_click_beacon_requires_login(): void
    {
        $this->post(route('audit.click'), ['label' => 'X', 'type' => 'button'])->assertRedirect();

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_click_beacon_rejects_bad_payloads_without_flashing_errors_into_the_session(): void
    {
        $student = $this->makeStudent($this->campusA, $this->programA, 'S-001');

        $this->actingAs($student)->post(route('audit.click'), ['type' => 'button'])
            ->assertStatus(422)
            ->assertSessionMissing('errors');

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_click_descriptions_drop_query_strings_and_control_characters(): void
    {
        $student = $this->makeStudent($this->campusA, $this->programA, 'S-001');

        $this->actingAs($student)->post(route('audit.click'), [
            'label' => "Sneaky\nFake entry: Coordinator approved everything",
            'type' => 'link',
            'href' => '/student/documents?token=SECRET123#frag',
            'page' => '/student/dtr?x=1',
        ]);

        $description = ActivityLog::where('action', 'ui.click')->firstOrFail()->description;

        $this->assertStringNotContainsString("\n", $description);
        $this->assertStringNotContainsString('SECRET123', $description);
        $this->assertStringContainsString('→ /student/documents', $description);
        $this->assertStringContainsString('(on /student/dtr)', $description);
    }

    // ---------- tamper resistance ----------

    public function test_log_rows_cannot_be_edited_or_deleted_through_the_model(): void
    {
        $user = $this->makeUser('coordinator', $this->campusA, 'C-001');
        $log = $this->seedLog($user, 'original');

        $this->assertFalse($log->update(['description' => 'tampered']));
        $this->assertFalse($log->delete());

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id, 'description' => 'original']);
    }
}
