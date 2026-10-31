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
        $this->actingAs($this->admin)->post(route('admin.campuses.store'), ['code' => 'bmc', 'name' => 'PSU - Binmaley Campus', 'address' => 'Binmaley, Pangasinan'])
            ->assertRedirect(route('admin.campuses.index'));
        $bmc = Campus::where('code', 'BMC')->firstOrFail();
        $this->assertSame('PSU - Binmaley Campus', $bmc->name);

        // codes are letters only and unique
        $this->assertSame('active', $bmc->status);

        // codes are 2-3 letters and unique; names are unique too
        $this->actingAs($this->admin)->post(route('admin.campuses.store'), ['code' => 'ACC', 'name' => 'Other'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('admin.campuses.store'), ['code' => 'B-1', 'name' => 'Other'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('admin.campuses.store'), ['code' => 'ABCD', 'name' => 'Other'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('admin.campuses.store'), ['code' => 'XYZ', 'name' => ' psu - binmaley campus '])->assertSessionHasErrors('name');
        // the form warns before sending
        $this->actingAs($this->admin)->get(route('admin.campuses.create'))->assertOk()
            ->assertSee('maxlength="3"', false)->assertSee('"ACC"', false)->assertSee('This campus already exists', false)
            ->assertDontSee('name="status"', false)->assertSee('>Add</button>', false);

        // no accounts yet: the code can still change
        $this->actingAs($this->admin)->put(route('admin.campuses.update', $bmc), ['code' => 'BIN', 'name' => 'PSU - Binmaley Campus']);
        $this->assertSame('BIN', $bmc->fresh()->code);

        // ACC has accounts: its code stays, the rest changes
        $this->staff('coordinator', 'ACC-COORD-001');
        $this->actingAs($this->admin)->put(route('admin.campuses.update', $this->acc), ['code' => 'XYZ', 'name' => 'PSU - Alaminos']);
        $this->assertSame(['ACC', 'PSU - Alaminos', 'active'], [$this->acc->fresh()->code, $this->acc->fresh()->name, $this->acc->fresh()->status]);
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

    public function test_a_coordinator_id_can_be_any_employee_number(): void
    {
        Mail::fake();
        $this->actingAs($this->admin)->post(route('admin.coordinators.store'), [
            'campus_id' => $this->acc->id, 'id_number' => '2019-04512', 'name' => 'X', 'email' => 'x@psu.test',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.coordinators.index'));
        $user = User::where('id_number', '2019-04512')->firstOrFail();
        $this->assertSame($this->acc->id, $user->campus_id);
        // the login page shows their campus from the account
        auth()->logout();
        $this->getJson(route('login.campus', ['id' => '2019-04512']))->assertJson(['campus' => 'Alaminos City Campus', 'kind' => 'OJT Coordinator']);
        // still unique across all accounts
        $this->actingAs($this->admin)->post(route('admin.coordinators.store'), [
            'campus_id' => $this->acc->id, 'id_number' => '2019-04512', 'name' => 'Y', 'email' => 'y@psu.test',
        ])->assertSessionHasErrors('id_number');
    }

    public function test_reset_password_and_deactivate(): void
    {
        Mail::fake();
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');

        $this->actingAs($this->admin)->post(route('admin.coordinators.reset-password', $coordinator))->assertRedirect();
        $password = session('provisioned_account')['password'];
        $this->assertTrue(Hash::check($password, $coordinator->fresh()->password));
        Mail::assertSent(StaffAccountMail::class, fn ($m) => $m->isReset && $m->hasTo($coordinator->email));

        // archived, not deleted (there is no delete for coordinators)
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.coordinators.destroy'));
        $this->actingAs($this->admin)->get(route('admin.coordinators.index'))->assertSee(route('admin.coordinators.archive', $coordinator), false);
        $this->actingAs($this->admin)->post(route('admin.coordinators.archive', $coordinator));
        $this->assertSame('archived', $coordinator->fresh()->status);
        $this->actingAs($this->admin)->get(route('admin.coordinators.index'))->assertOk()
            ->assertSee('Archived coordinators')->assertSee(route('admin.coordinators.restore', $coordinator), false);
        auth()->logout();
        $this->post(route('login.attempt'), ['id_number' => 'ACC-COORD-001', 'password' => $password])->assertSessionHasErrors('id_number');

        $this->actingAs($this->admin)->post(route('admin.coordinators.restore', $coordinator));
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

    public function test_the_super_admin_manages_a_campus_with_the_coordinators_access_under_their_own_account(): void
    {
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');
        $other = Campus::forceCreate(['code' => 'BC', 'name' => 'PSU - Bayambang Campus', 'status' => 'active']);

        // every campus can be managed - no coordinator account needed
        $this->actingAs($this->admin)->get(route('admin.campuses.index'))->assertOk()
            ->assertSee(route('admin.campuses.manage', $this->acc), false)
            ->assertSee(route('admin.campuses.manage', $other), false)
            ->assertDontSee('>Coordinator<', false)->assertDontSee('>Students<', false)->assertDontSee('>Status<', false);

        $this->actingAs($this->admin)->post(route('admin.campuses.manage', $this->acc))->assertRedirect(route('instructor.dashboard'));
        // still the Super Admin's own account - the coordinator's is never used
        $this->assertSame($this->admin->id, auth()->id());

        $this->get(route('instructor.dashboard'))->assertOk()
            // still the Super Admin's portal: their sidebar, with the campus's modules in it
            ->assertSee(route('admin.dashboard'), false)->assertSee(route('admin.campuses.index'), false)
            ->assertSee('Managing')->assertSee('Close campus')->assertSee('Campus Dashboard')
            ->assertSee(route('coordinator.programs.index'), false)->assertSee(route('coordinator.instructors.index'), false)
            ->assertDontSee('Coordinator Portal')->assertDontSee('Test OJT Coordinator')->assertDontSee(route('instructor.profile'), false);
        // the Super Admin's own pages keep the campus open in the sidebar
        $this->get(route('admin.campuses.index'))->assertOk()->assertSee('Close campus')->assertSee(route('coordinator.programs.index'), false);
        foreach (['coordinator.programs.index', 'coordinator.instructors.index', 'coordinator.hte-accounts.index', 'coordinator.documents', 'coordinator.settings', 'coordinator.campus-settings.edit', 'instructor.advisees', 'instructor.monitoring', 'instructor.htes', 'instructor.reports', 'instructor.audit-trail'] as $name) {
            $this->get(route($name))->assertOk();
        }
        // no document review, evaluations or announcements for the Super Admin
        $this->get(route('instructor.dashboard'))->assertDontSee(route('instructor.documents'), false)
            ->assertDontSee(route('instructor.evaluations'), false)->assertDontSee(route('instructor.announcements'), false)
            ->assertDontSee('Recent Submissions');
        foreach (['instructor.documents', 'instructor.evaluations', 'instructor.announcements'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->post(route('instructor.announcements.store'), ['title' => 'Hi', 'body' => 'x'])->assertForbidden();
        $this->get(route('instructor.profile'))->assertRedirect(route('admin.account'));

        // what they do is recorded as the Super Admin, on that campus
        $this->post(route('coordinator.programs.store'), ['code' => 'BSCS', 'name' => 'BS Computer Science', 'required_hours' => 486]);
        $this->assertTrue(\App\Models\Program::where('campus_id', $this->acc->id)->where('code', 'BSCS')->exists());
        $log = \App\Models\ActivityLog::where('action', 'program.created')->latest('id')->first();
        $this->assertSame([$this->admin->id, 'super_admin', $this->acc->id], [$log->user_id, $log->role_code, $log->campus_id]);

        // nothing about the campus was saved to their account
        $this->assertNull($this->admin->fresh()->campus_id);
        $this->assertSame(0, \App\Models\Instructor::where('user_id', $this->admin->id)->count());

        // switch campus
        $this->post(route('admin.campuses.leave'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.campuses.index'))->assertOk();
        $this->get(route('instructor.dashboard'))->assertForbidden();
        $this->post(route('admin.campuses.manage', $other));
        $this->get(route('coordinator.programs.index'))->assertOk()->assertDontSee('BSCS');
        $this->post(route('admin.campuses.leave'));

        // nobody else can use these
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
            ->assertSeeInOrder(['3', 'Student interns', '1', 'Deployed now', '1', 'Completed', '1', 'Documents waiting for review'])
            ->assertSeeInOrder(['PSU - Alaminos City Campus', 'Add coordinator'])
            ->assertSee('PSU - Bayambang Campus')
            ->getContent();
        // the By campus table is Campus, Coordinator and Manage only
        $this->assertStringNotContainsString('Docs waiting', $html);
        $this->assertStringNotContainsString('>Progress<', $html);
        $this->actingAs($this->staff('coordinator', 'ACC-COORD-001'))->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_an_inactive_campus_is_closed_to_its_accounts(): void
    {
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');
        $coordinator->update(['password' => Hash::make('secret')]);

        // signed in when the campus is closed: signed out on the next page
        $this->actingAs($coordinator)->get(route('instructor.dashboard'))->assertOk();
        $this->acc->update(['status' => 'archived']);
        $this->get(route('instructor.dashboard'))->assertRedirect(route('login'))->assertSessionHasErrors('id_number');
        $this->assertGuest();

        // and can't sign in again
        $this->post(route('login.attempt'), ['id_number' => 'ACC-COORD-001', 'password' => 'secret'])
            ->assertSessionHasErrors(['id_number' => \App\Http\Middleware\EnsureCampusIsActive::MESSAGE]);
        $this->assertGuest();

        // the Super Admin still signs in (they restore the campus to work in it again)
        $this->post(route('login.attempt'), ['id_number' => 'PSU-ADMIN-001', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.campuses.manage', $this->acc))->assertNotFound();

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

        // coordinators are deleted from OJT Coordinators, not here
        $this->delete(route('admin.campus-accounts.destroy', $coordinator))->assertNotFound();
        $this->post(route('admin.campuses.leave'));

        // coordinators are archived, never deleted
        $this->assertDatabaseHas('users', ['id' => $spare->id]);
        $this->assertDatabaseHas('campuses', ['id' => $this->acc->id]);
    }

    public function test_only_the_super_admin_can_delete(): void
    {
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');
        [$studentUser] = $this->studentWithRecords('23-AC-0001');

        // the coordinator sees no trash icon and can't use the routes
        $this->actingAs($coordinator)->get(route('instructor.advisees'))->assertOk()->assertDontSee(route('admin.campus-accounts.destroy', $studentUser), false);
        $this->actingAs($coordinator)->delete(route('admin.campus-accounts.destroy', $studentUser))->assertForbidden();
        $this->actingAs($coordinator)->post(route('admin.campuses.archive', $this->acc))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $studentUser->id]);
    }

    public function test_a_campus_is_archived_not_deleted_and_can_be_restored(): void
    {
        [$studentUser] = $this->studentWithRecords('23-AC-0001');
        $coordinator = $this->staff('coordinator', 'ACC-COORD-001');
        $coordinator->update(['password' => Hash::make('secret')]);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.campuses.destroy'));
        $this->actingAs($this->admin)->get(route('admin.campuses.index'))->assertOk()
            ->assertSee(route('admin.campuses.archive', $this->acc), false)->assertDontSee('Archived campuses');

        $this->actingAs($this->admin)->post(route('admin.campuses.archive', $this->acc))->assertRedirect(route('admin.campuses.index'));
        $this->assertSame('archived', $this->acc->fresh()->status);
        // everything is kept
        $this->assertDatabaseHas('users', ['id' => $studentUser->id]);
        $this->assertSame(1, \App\Models\Document::count());
        // listed under Archived campuses, with Restore; no Manage for it
        $this->actingAs($this->admin)->get(route('admin.campuses.index'))->assertOk()
            ->assertSee('Archived campuses')->assertSee(route('admin.campuses.restore', $this->acc), false)
            ->assertDontSee(route('admin.campuses.manage', $this->acc), false);
        $this->actingAs($this->admin)->post(route('admin.campuses.manage', $this->acc))->assertNotFound();
        // its accounts can't sign in
        auth()->logout();
        $this->post(route('login.attempt'), ['id_number' => 'ACC-COORD-001', 'password' => 'secret'])->assertSessionHasErrors('id_number');
        $this->assertGuest();

        $this->actingAs($this->admin)->post(route('admin.campuses.restore', $this->acc))->assertRedirect(route('admin.campuses.index'));
        $this->assertSame('active', $this->acc->fresh()->status);
        auth()->logout();
        $this->post(route('login.attempt'), ['id_number' => 'ACC-COORD-001', 'password' => 'secret'])->assertSessionHasNoErrors();
    }

    public function test_coordinators_are_grouped_by_academic_year_and_open_when_clicked(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-29 12:00:00'));
        $this->staff('coordinator', 'ACC-COORD-001');                      // A.Y. 2026-2027
        $old = $this->staff('coordinator', 'ACC-COORD-000');
        $old->forceFill(['created_at' => '2025-09-01 08:00:00'])->save();  // A.Y. 2025-2026

        $html = $this->actingAs($this->admin)->get(route('admin.coordinators.index'))->assertOk()
            ->assertSeeInOrder(['A.Y. 2026-2027', 'Current', 'ACC-COORD-001', 'A.Y. 2025-2026', 'ACC-COORD-000'])
            ->getContent();
        // closed until clicked
        $this->assertSame(2, substr_count($html, '<details class="ay-group" >'));

        // searching opens the groups so the matches show
        $html = $this->actingAs($this->admin)->get(route('admin.coordinators.index', ['search' => 'COORD-000']))->assertOk()
            ->assertSee('A.Y. 2025-2026')->assertDontSee('A.Y. 2026-2027')->getContent();
        $this->assertStringContainsString('<details class="ay-group"  open >', $html);
    }

    public function test_the_audit_trail_shows_10_per_page(): void
    {
        foreach (range(1, 12) as $n) {
            \App\Support\AuditLogger::record('test.entry', "Entry number {$n}.", $this->admin);
        }

        $this->actingAs($this->admin)->get(route('admin.audit-trail', ['kind' => 'action']))->assertOk()
            ->assertSee('Entry number 12.')->assertSee('Entry number 3.')->assertDontSee('Entry number 2.');
        $this->actingAs($this->admin)->get(route('admin.audit-trail', ['kind' => 'action', 'page' => 2]))->assertOk()
            ->assertSee('Entry number 2.')->assertSee('Entry number 1.');
    }
}
