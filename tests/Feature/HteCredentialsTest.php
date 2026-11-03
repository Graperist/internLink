<?php

namespace Tests\Feature;

use App\Mail\HteAccountMail;
use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Hte;
use App\Models\HteListing;
use App\Models\HteRepresentative;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\HteAccounts;
use App\Support\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An HTE account can be made from Add partner agency without emailing
 * anything; the mail icon emails the sign-in details only once every
 * intern at the agency has finished.
 */
class HteCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Program $bsit;

    private User $coordinator;

    private User $instructor;

    private Hte $hte;

    private Deployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['coordinator', 'instructor', 'hte_representative', 'student'] as $code) {
            Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }
        $this->campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $this->bsit = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $bshm = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSHM', 'name' => 'BSHM', 'required_hours' => 486]);
        $this->coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $this->instructor = $this->teacher('instructor', 'ACC-INST-002', $bshm);

        $this->hte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Fareheaven Boarding House', 'contact_person' => 'Virgie Osorio', 'contact_email' => 'virgie@fareheaven.test']);
        HteListing::forceCreate(['campus_id' => $this->campus->id, 'program_id' => $this->bsit->id, 'hte_id' => $this->hte->id, 'academic_year' => '2026-2027', 'semester' => Term::SEMESTER, 'slots' => 3]);

        $student = Student::forceCreate(['user_id' => $this->user('student', '23-AC-0001')->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id, 'hours_rendered' => 0]);
        $internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bsit->id, 'required_hours' => 486, 'status' => 'deployed']);
        $this->deployment = Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $this->hte->id, 'start_date' => '2026-10-01', 'status' => 'active']);
    }

    private function user(string $role, string $id): User
    {
        return User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => Role::where('code', $role)->value('id'), 'id_number' => $id,
            'name' => "{$role} {$id}", 'email' => strtolower($id).'@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
    }

    private function teacher(string $role, string $id, Program $program): User
    {
        $user = $this->user($role, $id);
        Instructor::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id]);

        return $user->fresh();
    }

    private function addAgency(array $extra = [])
    {
        return $this->actingAs($this->coordinator)->post(route('instructor.htes.store'), $extra + [
            'academic_year' => '2026-2027', 'semester' => Term::SEMESTER, 'program_id' => $this->bsit->id,
            'name' => 'BDO Unibank - Alaminos', 'address' => 'Alaminos City', 'contact_person' => 'Marianne Magpayo',
            'representative_position' => 'Manager', 'contact_phone' => '09170000000', 'contact_email' => 'marianne@bdo.test', 'slots' => 2,
        ]);
    }

    private function account(): HteRepresentative
    {
        return HteRepresentative::firstOrCreate(['hte_id' => $this->hte->id], [
            'user_id' => HteAccounts::create($this->hte, 'ACC-HTE-001', 'Virgie Osorio', 'virgie@fareheaven.test')->user_id,
        ])->load('user', 'hte');
    }

    public function test_add_partner_agency_asks_about_an_account_and_suggests_an_id(): void
    {
        $this->actingAs($this->coordinator)->get(route('instructor.htes.create'))->assertOk()
            ->assertSee('Do you also want to create an account for this partner agency?')
            ->assertSee('value="ACC-HTE-001"', false)
            ->assertSee('Nothing is emailed now');
    }

    public function test_yes_creates_the_account_without_emailing(): void
    {
        Mail::fake();
        $this->addAgency(['create_account' => '1', 'account_id_number' => 'ACC-HTE-001'])->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'HTE account (ACC-HTE-001) was created'));

        $user = User::where('id_number', 'ACC-HTE-001')->firstOrFail();
        $this->assertSame('Marianne Magpayo', $user->name);
        $this->assertSame('marianne@bdo.test', $user->email);
        $this->assertSame('hte_representative', $user->role->code);
        $this->assertNull(HteRepresentative::where('user_id', $user->id)->value('credentials_sent_at'));
        Mail::assertNothingSent();
    }

    public function test_no_creates_only_the_agency(): void
    {
        $this->addAgency(['create_account' => '0', 'account_id_number' => 'ACC-HTE-001'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('htes', ['name' => 'BDO Unibank - Alaminos']);
        $this->assertDatabaseMissing('users', ['id_number' => 'ACC-HTE-001']);
    }

    public function test_the_account_needs_an_unused_email(): void
    {
        $this->addAgency(['create_account' => '1', 'account_id_number' => 'ACC-HTE-001', 'contact_email' => '23-ac-0001@t.local'])
            ->assertSessionHasErrors('contact_email');
        $this->addAgency(['create_account' => '1', 'account_id_number' => 'ACC-HTE-001', 'contact_email' => ''])
            ->assertSessionHasErrors('contact_email');
        $this->assertDatabaseMissing('users', ['id_number' => 'ACC-HTE-001']);
    }

    public function test_the_mail_icon_waits_until_the_interns_finish(): void
    {
        Mail::fake();
        $account = $this->account();

        $this->actingAs($this->coordinator)->get(route('instructor.hte-accounts.index'))->assertOk()
            ->assertSee('1 intern at this agency has not finished the internship yet.')
            ->assertDontSee(route('instructor.hte-accounts.send', $account), false);

        $this->actingAs($this->coordinator)->post(route('instructor.hte-accounts.send', $account))->assertSessionHasErrors('send');
        Mail::assertNothingSent();
        $this->assertNull($account->fresh()->credentials_sent_at);
    }

    public function test_once_the_interns_finish_the_mail_icon_emails_the_sign_in_details(): void
    {
        Mail::fake();
        $account = $this->account();
        $this->deployment->update(['status' => 'completed']);

        $this->actingAs($this->coordinator)->get(route('instructor.hte-accounts.index'))->assertOk()
            ->assertSee(route('instructor.hte-accounts.send', $account), false);
        // also on Partner Agencies
        $this->actingAs($this->coordinator)->get(route('instructor.htes', ['academic_year' => '2026-2027', 'semester' => Term::SEMESTER]))->assertOk()
            ->assertSee(route('instructor.hte-accounts.send', $account), false);

        $this->actingAs($this->coordinator)->post(route('instructor.hte-accounts.send', $account))
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'emailed to virgie@fareheaven.test'));

        $sentPassword = null;
        Mail::assertSent(HteAccountMail::class, function ($mail) use (&$sentPassword) {
            $sentPassword = $mail->password;

            return $mail->hasTo('virgie@fareheaven.test');
        });
        $this->assertTrue(Hash::check($sentPassword, $account->user->fresh()->password));
        $this->assertNotNull($account->fresh()->credentials_sent_at);
        $this->actingAs($this->coordinator)->get(route('instructor.hte-accounts.index'))->assertSee('Sign-in details sent');
    }

    public function test_a_failed_email_keeps_the_old_password_and_says_why(): void
    {
        $account = $this->account();
        $this->deployment->update(['status' => 'completed']);
        $before = $account->user->password;

        config(['mail.default' => 'log']);
        $this->actingAs($this->coordinator)->post(route('instructor.hte-accounts.send', $account))
            ->assertSessionHasErrors(['send' => "The sign-in details for Fareheaven Boarding House couldn't be emailed: Email sending is not set up on this server (MAIL_MAILER is \"log\"). Set the MAIL_ settings and redeploy."]);
        $this->assertSame($before, $account->user->fresh()->password);
        $this->assertNull($account->fresh()->credentials_sent_at);
    }

    public function test_an_instructor_sends_only_for_their_own_programs_agencies(): void
    {
        Mail::fake();
        $account = $this->account();
        $this->deployment->update(['status' => 'completed']);

        // BSHM instructor - the agency is on the BSIT list only
        $this->actingAs($this->instructor)->post(route('instructor.hte-accounts.send', $account))->assertForbidden();

        HteListing::forceCreate(['campus_id' => $this->campus->id, 'program_id' => $this->instructor->instructor->program_id, 'hte_id' => $this->hte->id, 'academic_year' => '2026-2027', 'semester' => Term::SEMESTER, 'slots' => 1]);
        $this->actingAs($this->instructor)->post(route('instructor.hte-accounts.send', $account))->assertSessionHasNoErrors();
        Mail::assertSent(HteAccountMail::class);
    }

    public function test_the_account_can_be_edited_and_the_agency_email_follows(): void
    {
        $account = $this->account();
        $this->actingAs($this->coordinator)->get(route('instructor.hte-accounts.edit', $account))->assertOk()
            ->assertSee('value="ACC-HTE-001"', false)->assertSee('virgie@fareheaven.test');

        $this->actingAs($this->coordinator)->put(route('instructor.hte-accounts.update', $account), [
            'id_number' => 'ACC-HTE-010', 'name' => 'Virgie O. Osorio', 'email' => 'virgie.new@fareheaven.test',
        ])->assertRedirect(route('instructor.hte-accounts.index'));

        $user = $account->user->fresh();
        $this->assertSame(['ACC-HTE-010', 'Virgie O. Osorio', 'virgie.new@fareheaven.test'], [$user->id_number, $user->name, $user->email]);
        $this->assertSame('virgie.new@fareheaven.test', $this->hte->fresh()->contact_email);
        // the list keeps it (the agency email no longer pulls it back)
        $this->actingAs($this->coordinator)->get(route('instructor.hte-accounts.index'))->assertSee('virgie.new@fareheaven.test')
            ->assertDontSee("Not the agency's email", false);

        // an email or ID another account uses is refused
        $this->actingAs($this->coordinator)->put(route('instructor.hte-accounts.update', $account), [
            'id_number' => 'ACC-COORD-001', 'name' => 'x', 'email' => '23-ac-0001@t.local',
        ])->assertSessionHasErrors(['id_number', 'email']);
    }

    public function test_accounts_are_archived_and_restored_never_deleted(): void
    {
        $account = $this->account();
        $this->actingAs($this->coordinator)->get(route('instructor.hte-accounts.index'))
            ->assertSee(route('instructor.hte-accounts.archive', $account), false)
            ->assertDontSee('ti-trash', false);

        $this->actingAs($this->coordinator)->from(route('instructor.hte-accounts.index'))->post(route('instructor.hte-accounts.archive', $account))->assertRedirect();
        $this->assertSame('archived', $account->user->fresh()->status);
        $this->assertDatabaseHas('hte_representatives', ['id' => $account->id]);
        $this->actingAs($this->coordinator)->get(route('instructor.hte-accounts.index'))
            ->assertSee('Archived HTE accounts')->assertSee(route('instructor.hte-accounts.restore', $account), false);

        // it can't sign in, and the mail icon can't send to it
        auth()->logout();
        $this->post(route('login.attempt'), ['id_number' => 'ACC-HTE-001', 'password' => 'whatever'])->assertSessionHasErrors();
        $this->deployment->update(['status' => 'completed']);
        Mail::fake();
        $this->actingAs($this->coordinator)->post(route('instructor.hte-accounts.send', $account))->assertSessionHasErrors('send');
        Mail::assertNothingSent();

        $this->actingAs($this->coordinator)->post(route('instructor.hte-accounts.restore', $account))->assertRedirect();
        $this->assertSame('active', $account->user->fresh()->status);
    }

    public function test_the_list_is_filtered_by_academic_year(): void
    {
        $this->account();   // Fareheaven: on the 2026-2027 list
        $old = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Old Partner Resort', 'contact_person' => 'Old Rep', 'contact_email' => 'old@resort.test']);
        HteListing::forceCreate(['campus_id' => $this->campus->id, 'program_id' => $this->bsit->id, 'hte_id' => $old->id, 'academic_year' => '2023-2024', 'semester' => Term::SEMESTER, 'slots' => 2]);
        $oldAccount = HteAccounts::create($old, 'ACC-HTE-050', 'Old Rep', 'old@resort.test');
        $oldAccount->forceFill(['created_at' => '2024-01-10'])->save();

        // current year by default
        $this->actingAs($this->coordinator)->get(route('instructor.hte-accounts.index'))->assertOk()
            ->assertSee('AY 2026-2027 (current)')->assertSee('Fareheaven Boarding House')->assertDontSee('Old Partner Resort');
        $this->actingAs($this->coordinator)->get(route('instructor.hte-accounts.index', ['academic_year' => '2023-2024']))
            ->assertSee('Old Partner Resort')->assertDontSee('Fareheaven Boarding House');
        $this->actingAs($this->coordinator)->get(route('instructor.hte-accounts.index', ['academic_year' => 'all']))
            ->assertSee('Old Partner Resort')->assertSee('Fareheaven Boarding House');
    }
}
