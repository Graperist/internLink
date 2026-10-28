<?php

namespace Tests\Feature;

use App\Mail\StaffAccountMail;
use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The Super Admin adds campuses and their OJT Coordinator accounts. */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Campus $acc;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['coordinator', 'instructor', 'hte_representative', 'student', 'super_admin'] as $code) {
            Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $this->acc = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $this->admin = User::forceCreate(['campus_id' => null, 'role_id' => Role::where('code', 'super_admin')->value('id'),
            'id_number' => 'PSU-ADMIN-001', 'name' => 'Super Admin', 'email' => 'admin@t.local', 'password' => Hash::make('password'),
            'status' => 'active', 'must_change_password' => false]);
    }

    private function staff(string $role, string $id): User
    {
        return User::forceCreate(['campus_id' => $this->acc->id, 'role_id' => Role::where('code', $role)->value('id'),
            'id_number' => $id, 'name' => $id, 'email' => strtolower($id).'@t.local', 'password' => Hash::make('x'),
            'status' => 'active', 'must_change_password' => false]);
    }

    public function test_the_super_admin_signs_in_with_an_id_that_has_no_campus(): void
    {
        $this->post(route('login.attempt'), ['id_number' => 'psu-admin-001', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.campuses.index'))->assertOk()
            ->assertSee('Super Admin')->assertSee('PSU - Alaminos City Campus')
            ->assertSee(route('admin.coordinators.index'), false)->assertSee(route('admin.login-photos'), false);
    }

    public function test_only_the_super_admin_gets_in(): void
    {
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');
        foreach (['admin.campuses.index', 'admin.campuses.create', 'admin.coordinators.index', 'admin.coordinators.create', 'admin.login-photos', 'admin.account'] as $name) {
            $this->actingAs($coordinator)->get(route($name))->assertForbidden();
            $this->actingAs($this->admin)->get(route($name))->assertOk();
        }
        // and the Super Admin stays out of the campus portals
        $this->actingAs($this->admin)->get(route('instructor.dashboard'))->assertForbidden();
    }

    public function test_adding_and_editing_a_campus(): void
    {
        $this->actingAs($this->admin)->post(route('admin.campuses.store'), ['code' => 'bmc', 'name' => 'PSU - Binmaley Campus', 'address' => 'Binmaley, Pangasinan', 'status' => 'active'])
            ->assertRedirect(route('admin.campuses.index'));
        $bmc = Campus::where('code', 'BMC')->firstOrFail();
        $this->assertSame('PSU - Binmaley Campus', $bmc->name);

        // codes are letters only and unique
        $this->actingAs($this->admin)->post(route('admin.campuses.store'), ['code' => 'ACC', 'name' => 'Other', 'status' => 'active'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('admin.campuses.store'), ['code' => 'B-1', 'name' => 'Other', 'status' => 'active'])->assertSessionHasErrors('code');

        // no accounts yet: the code can still change
        $this->actingAs($this->admin)->put(route('admin.campuses.update', $bmc), ['code' => 'BIN', 'name' => 'PSU - Binmaley Campus', 'status' => 'active']);
        $this->assertSame('BIN', $bmc->fresh()->code);

        // ACC has accounts: its code stays, the rest changes
        $this->staff('coordinator', 'ACC-COORD-001');
        $this->actingAs($this->admin)->put(route('admin.campuses.update', $this->acc), ['code' => 'XYZ', 'name' => 'PSU - Alaminos', 'status' => 'inactive']);
        $this->assertSame(['ACC', 'PSU - Alaminos', 'inactive'], [$this->acc->fresh()->code, $this->acc->fresh()->name, $this->acc->fresh()->status]);
    }

    public function test_adding_a_coordinator_emails_the_details_and_asks_for_a_new_password(): void
    {
        Mail::fake();
        $this->actingAs($this->admin)->post(route('admin.coordinators.store'), [
            'campus_id' => $this->acc->id, 'id_number' => 'acc-coord-002', 'name' => 'Maria Santos', 'email' => 'maria@psu.test', 'send_email' => '1',
        ])->assertRedirect(route('admin.coordinators.index'));

        $user = User::where('id_number', 'ACC-COORD-002')->firstOrFail();
        $this->assertTrue($user->isCoordinator());
        $this->assertSame($this->acc->id, $user->campus_id);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertNotNull($user->instructor);
        $password = session('provisioned_account')['password'];
        Mail::assertSent(StaffAccountMail::class, fn ($m) => $m->hasTo('maria@psu.test') && $m->password === $password && ! $m->isReset);

        $this->actingAs($this->admin)->get(route('admin.coordinators.index'))->assertOk()
            ->assertSee('Maria Santos')->assertSee('emailed to <strong>maria@psu.test</strong>', false);

        // they can sign in, and are asked to choose their own password first
        auth()->logout();
        $this->post(route('login.attempt'), ['id_number' => 'ACC-COORD-002', 'password' => $password]);
        $this->get(route('instructor.dashboard'))->assertRedirect(route('password.change'));
    }

    public function test_the_coordinator_id_must_carry_the_campus_code(): void
    {
        $this->actingAs($this->admin)->post(route('admin.coordinators.store'), [
            'campus_id' => $this->acc->id, 'id_number' => 'SMC-COORD-001', 'name' => 'X', 'email' => 'x@psu.test',
        ])->assertSessionHasErrors('id_number');
        $this->assertFalse(User::where('id_number', 'SMC-COORD-001')->exists());
    }

    public function test_reset_password_and_deactivate(): void
    {
        Mail::fake();
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');

        $this->actingAs($this->admin)->post(route('admin.coordinators.reset-password', $coordinator))->assertRedirect();
        $password = session('provisioned_account')['password'];
        $this->assertTrue(Hash::check($password, $coordinator->fresh()->password));
        Mail::assertSent(StaffAccountMail::class, fn ($m) => $m->isReset && $m->hasTo($coordinator->email));

        $this->actingAs($this->admin)->post(route('admin.coordinators.status', $coordinator));
        $this->assertSame('inactive', $coordinator->fresh()->status);
        auth()->logout();
        $this->post(route('login.attempt'), ['id_number' => 'ACC-COORD-001', 'password' => $password])->assertSessionHasErrors('id_number');

        $this->actingAs($this->admin)->post(route('admin.coordinators.status', $coordinator));
        $this->assertSame('active', $coordinator->fresh()->status);

        // only coordinator accounts
        $student = $this->staff('student', '23-AC-0001');
        $this->actingAs($this->admin)->post(route('admin.coordinators.reset-password', $student))->assertNotFound();
    }

    public function test_the_migration_adds_a_first_super_admin_to_a_database_in_use(): void
    {
        $this->admin->delete();
        Role::where('code', 'super_admin')->delete();

        (require database_path('migrations/2026_10_28_000001_add_super_admin.php'))->up();

        $admin = User::where('id_number', 'PSU-ADMIN-001')->firstOrFail();
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertNull($admin->campus_id);
        $this->assertTrue(Hash::check('password', $admin->password));

        // running it again adds nothing
        (require database_path('migrations/2026_10_28_000001_add_super_admin.php'))->up();
        $this->assertSame(1, DB::table('users')->where('id_number', 'PSU-ADMIN-001')->count());
    }

    public function test_the_super_admin_manages_a_campus_as_its_coordinator_and_comes_back(): void
    {
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');
        $coordinator->update(['must_change_password' => true]);   // not asked of the Super Admin
        $other = Campus::forceCreate(['code' => 'BC', 'name' => 'PSU - Bayambang Campus', 'status' => 'active']);

        $this->actingAs($this->admin)->get(route('admin.campuses.index'))->assertOk()
            ->assertSee(route('admin.campuses.manage', $this->acc), false)
            ->assertDontSee(route('admin.campuses.manage', $other), false);   // no coordinator yet

        $this->actingAs($this->admin)->post(route('admin.campuses.manage', $this->acc))->assertRedirect(route('instructor.dashboard'));
        $this->assertSame($coordinator->id, auth()->id());

        // every coordinator page of that campus, with the banner
        $this->get(route('instructor.dashboard'))->assertOk()
            ->assertSee("you're managing", false)->assertSee('PSU - Alaminos City Campus')->assertSee('Back to Super Admin');
        foreach (['coordinator.programs.index', 'coordinator.instructors.index', 'coordinator.hte-accounts.index', 'coordinator.documents', 'coordinator.settings', 'coordinator.campus-settings.edit'] as $name) {
            $this->get(route($name))->assertOk();
        }

        // the audit trail names the Super Admin
        $this->post(route('coordinator.programs.store'), ['code' => 'BSCS', 'name' => 'BS Computer Science', 'required_hours' => 486]);
        $this->assertTrue(\App\Models\Program::where('campus_id', $this->acc->id)->where('code', 'BSCS')->exists());
        $this->get(route('coordinator.programs.index'));
        $this->assertTrue(\App\Models\ActivityLog::where('user_id', $coordinator->id)->where('description', 'like', '%(by Super Admin Super Admin)%')->exists());

        // back to the Super Admin portal
        $this->post(route('admin.campuses.leave'))->assertRedirect(route('admin.dashboard'));
        $this->assertSame($this->admin->id, auth()->id());
        $this->get(route('admin.campuses.index'))->assertOk();
        $this->get(route('instructor.dashboard'))->assertForbidden();

        // nobody else can use "back to Super Admin"
        $coordinator->update(['must_change_password' => false]);
        $this->actingAs($coordinator)->post(route('admin.campuses.leave'))->assertForbidden();
        $this->actingAs($coordinator)->post(route('admin.campuses.manage', $this->acc))->assertForbidden();
    }

    public function test_the_audit_trail_shows_every_campus_with_a_campus_filter(): void
    {
        $other = Campus::forceCreate(['code' => 'BC', 'name' => 'PSU - Bayambang Campus', 'status' => 'active']);
        $accCoord = $this->staff('coordinator', 'ACC-COORD-001');
        $bcCoord = User::forceCreate(['campus_id' => $other->id, 'role_id' => Role::where('code', 'coordinator')->value('id'),
            'id_number' => 'BC-COORD-001', 'name' => 'BC Coord', 'email' => 'bc@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false]);
        \App\Support\AuditLogger::record('program.created', 'Added program ACCPROG.', $accCoord);
        \App\Support\AuditLogger::record('program.created', 'Added program BCPROG.', $bcCoord);
        \App\Support\AuditLogger::record('admin.campus_added', 'Added campus XYZ.', $this->admin);

        $this->actingAs($this->admin)->get(route('admin.campuses.index'))->assertSee(route('admin.audit-trail'), false);
        $this->actingAs($this->admin)->get(route('admin.audit-trail'))->assertOk()
            ->assertSee('Every campus')->assertSee('All campuses')
            ->assertSee('Added program ACCPROG.')->assertSee('Added program BCPROG.')->assertSee('Added campus XYZ.');
        $this->actingAs($this->admin)->get(route('admin.audit-trail', ['campus_id' => $other->id]))->assertOk()
            ->assertSee('Added program BCPROG.')->assertDontSee('Added program ACCPROG.')->assertDontSee('Added campus XYZ.');
        $this->actingAs($this->admin)->get(route('admin.audit-trail', ['campus_id' => 'none']))->assertOk()
            ->assertSee('Added campus XYZ.')->assertDontSee('Added program BCPROG.');

        // the coordinator's own Audit Trail still shows only their campus
        $this->actingAs($accCoord)->get(route('instructor.audit-trail'))->assertOk()
            ->assertSee('Added program ACCPROG.')->assertDontSee('Added program BCPROG.')->assertDontSee('Added campus XYZ.')
            ->assertDontSee('All campuses');
        $this->actingAs($accCoord)->get(route('admin.audit-trail'))->assertForbidden();
    }

    public function test_the_dashboard_totals_each_campus(): void
    {
        $program = \App\Models\Program::forceCreate(['campus_id' => $this->acc->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $hte = \App\Models\Hte::forceCreate(['campus_id' => $this->acc->id, 'name' => 'Agency']);
        $requirement = \App\Models\DocumentRequirement::forceCreate(['campus_id' => $this->acc->id, 'code' => 'APPLICATION_FOR_OJT', 'name' => 'Form 1',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => true, 'is_required' => true, 'display_order' => 1]);
        foreach (['23-AC-0001' => 'active', '23-AC-0002' => 'completed', '23-AC-0003' => null] as $id => $deploymentStatus) {
            $student = \App\Models\Student::forceCreate(['user_id' => $this->staff('student', $id)->id, 'campus_id' => $this->acc->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
            $internship = \App\Models\Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $this->acc->id, 'program_id' => $program->id, 'required_hours' => 486,
                'status' => $deploymentStatus === 'completed' ? 'completed' : ($deploymentStatus ? 'deployed' : 'pre_deployment')]);
            if ($deploymentStatus) {
                \App\Models\Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'start_date' => '2026-09-01', 'status' => $deploymentStatus]);
            } else {
                \App\Models\Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $requirement->id, 'status' => 'submitted', 'submitted_at' => now()]);
            }
        }
        Campus::forceCreate(['code' => 'BC', 'name' => 'PSU - Bayambang Campus', 'status' => 'active']);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Active campuses')->assertSee('Documents waiting for review')
            ->assertSeeInOrder(['PSU - Alaminos City Campus', 'Add coordinator', '3', '1', '1', '33%'])
            ->assertSee('PSU - Bayambang Campus')
            ->getContent();
        $this->assertStringContainsString('text-bg-warning">1</span>', $html);   // one document waiting at ACC
        $this->actingAs($this->staff('coordinator', 'ACC-COORD-001'))->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_an_inactive_campus_is_closed_to_its_accounts(): void
    {
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');
        $coordinator->update(['password' => Hash::make('secret')]);

        // signed in when the campus is closed: signed out on the next page
        $this->actingAs($coordinator)->get(route('instructor.dashboard'))->assertOk();
        $this->acc->update(['status' => 'inactive']);
        $this->get(route('instructor.dashboard'))->assertRedirect(route('login'))->assertSessionHasErrors('id_number');
        $this->assertGuest();

        // and can't sign in again
        $this->post(route('login.attempt'), ['id_number' => 'ACC-COORD-001', 'password' => 'secret'])
            ->assertSessionHasErrors(['id_number' => \App\Http\Middleware\EnsureCampusIsActive::MESSAGE]);
        $this->assertGuest();

        // the Super Admin still signs in and can still open the campus
        $this->post(route('login.attempt'), ['id_number' => 'PSU-ADMIN-001', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.campuses.manage', $this->acc))->assertRedirect(route('instructor.dashboard'));
        $this->get(route('instructor.dashboard'))->assertOk()->assertSee('Back to Super Admin');
        $this->post(route('admin.campuses.leave'))->assertRedirect(route('admin.dashboard'));

        // active again: everyone gets back in
        $this->acc->update(['status' => 'active']);
        auth()->logout();
        $this->post(route('login.attempt'), ['id_number' => 'ACC-COORD-001', 'password' => 'secret'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($coordinator);
    }

    /** A student on ACC with an internship, a deployment, a document with a file and a review. */
    private function studentWithRecords(string $id, ?\App\Models\Instructor $reviewer = null): array
    {
        \Illuminate\Support\Facades\Storage::fake();
        $program = \App\Models\Program::firstOrCreate(['campus_id' => $this->acc->id, 'code' => 'BSIT'], ['name' => 'BSIT', 'required_hours' => 486]);
        $hte = \App\Models\Hte::firstOrCreate(['campus_id' => $this->acc->id, 'name' => 'Agency']);
        $requirement = \App\Models\DocumentRequirement::firstOrCreate(['campus_id' => $this->acc->id, 'code' => 'APPLICATION_FOR_OJT'], ['name' => 'Form 1',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => true, 'is_required' => true, 'display_order' => 1]);
        $user = $this->staff('student', $id);
        $student = \App\Models\Student::forceCreate(['user_id' => $user->id, 'campus_id' => $this->acc->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $internship = \App\Models\Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $this->acc->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'deployed']);
        \App\Models\Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'start_date' => '2026-09-01', 'status' => 'active']);
        \Illuminate\Support\Facades\Storage::put("documents/{$id}.pdf", 'pdf');
        $document = \App\Models\Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $requirement->id, 'status' => 'approved', 'file_path' => "documents/{$id}.pdf"]);
        if ($reviewer) {
            \App\Models\DocumentReview::forceCreate(['document_id' => $document->id, 'instructor_id' => $reviewer->id, 'decision' => 'approved', 'reviewed_at' => now()]);
        }

        return [$user, $student, $document];
    }

    public function test_the_super_admin_deletes_a_student_instructor_and_coordinator(): void
    {
        $instructorUser = $this->staff('instructor', 'ACC-INST-001');
        $instructor = \App\Models\Instructor::forceCreate(['user_id' => $instructorUser->id, 'campus_id' => $this->acc->id]);
        [$studentUser, $student, $document] = $this->studentWithRecords('23-AC-0001', $instructor);
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');
        $spare = $this->staff('coordinator', 'ACC-COORD-002');

        // in the campus (Manage): trash icons on Students and Instructors
        $this->actingAs($this->admin)->post(route('admin.campuses.manage', $this->acc));
        $this->get(route('instructor.advisees'))->assertOk()->assertSee(route('admin.campus-accounts.destroy', $studentUser), false);
        $this->get(route('coordinator.instructors.index'))->assertOk()->assertSee(route('admin.campus-accounts.destroy', $instructorUser), false);

        // the instructor: their review stays, without them
        $this->delete(route('admin.campus-accounts.destroy', $instructorUser))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $instructorUser->id]);
        $this->assertDatabaseMissing('instructors', ['id' => $instructor->id]);
        $this->assertDatabaseHas('document_reviews', ['document_id' => $document->id, 'instructor_id' => null]);

        // the student: account, internship, documents and the file
        $this->delete(route('admin.campus-accounts.destroy', $studentUser))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        \Illuminate\Support\Facades\Storage::assertMissing('documents/23-AC-0001.pdf');

        // not the coordinator being used, and nothing from another campus
        $this->delete(route('admin.campus-accounts.destroy', $coordinator))->assertNotFound();
        $this->post(route('admin.campuses.leave'));

        // coordinators from OJT Coordinators
        $this->get(route('admin.coordinators.index'))->assertSee(route('admin.coordinators.destroy', $spare), false);
        $this->delete(route('admin.coordinators.destroy', $spare))->assertRedirect(route('admin.coordinators.index'));
        $this->assertDatabaseMissing('users', ['id' => $spare->id]);
        $this->assertDatabaseHas('campuses', ['id' => $this->acc->id]);
    }

    public function test_only_the_super_admin_can_delete(): void
    {
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');
        [$studentUser] = $this->studentWithRecords('23-AC-0001');

        // the coordinator sees no trash icon and can't use the routes
        $this->actingAs($coordinator)->get(route('instructor.advisees'))->assertOk()->assertDontSee(route('admin.campus-accounts.destroy', $studentUser), false);
        $this->actingAs($coordinator)->delete(route('admin.campus-accounts.destroy', $studentUser))->assertForbidden();
        $this->actingAs($coordinator)->delete(route('admin.campuses.destroy', $this->acc), ['confirm_code' => 'ACC'])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $studentUser->id]);
    }

    public function test_deleting_a_campus_needs_its_code_and_removes_everything_on_it(): void
    {
        $instructorUser = $this->staff('instructor', 'ACC-INST-001');
        $instructor = \App\Models\Instructor::forceCreate(['user_id' => $instructorUser->id, 'campus_id' => $this->acc->id]);
        [$studentUser] = $this->studentWithRecords('23-AC-0001', $instructor);
        $hteUser = $this->staff('hte_representative', 'ACC-HTE-001');
        \App\Models\HteRepresentative::forceCreate(['user_id' => $hteUser->id, 'hte_id' => \App\Models\Hte::first()->id]);
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');
        $other = Campus::forceCreate(['code' => 'BC', 'name' => 'PSU - Bayambang Campus', 'status' => 'active']);

        $this->actingAs($this->admin)->get(route('admin.campuses.index'))->assertSee(route('admin.campuses.destroy', $this->acc), false)->assertSee('data-confirm-code="ACC"', false);
        $this->actingAs($this->admin)->delete(route('admin.campuses.destroy', $this->acc), ['confirm_code' => 'WRONG'])->assertSessionHasErrors('confirm_code');
        $this->assertDatabaseHas('campuses', ['id' => $this->acc->id]);

        $this->actingAs($this->admin)->delete(route('admin.campuses.destroy', $this->acc), ['confirm_code' => 'acc'])->assertRedirect(route('admin.campuses.index'));
        $this->assertDatabaseMissing('campuses', ['id' => $this->acc->id]);
        foreach ([$instructorUser, $studentUser, $hteUser, $coordinator] as $user) {
            $this->assertDatabaseMissing('users', ['id' => $user->id]);
        }
        $this->assertSame(0, \App\Models\Program::count() + \App\Models\Hte::count() + \App\Models\Internship::count() + \App\Models\Document::count());
        $this->assertDatabaseHas('campuses', ['id' => $other->id]);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }
}
