<?php

namespace Tests\Feature;

use App\Mail\HteAccountMail;
use App\Models\Campus;
use App\Models\Hte;
use App\Models\HteRepresentative;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The HTE account's sign-in details are emailed to the address the
 * coordinator entered (prefilled from the partner agency's contact email).
 */
class HteAccountEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    private Hte $hte;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['coordinator', 'hte_representative'] as $code) {
            Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $this->coordinator = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => Role::where('code', 'coordinator')->value('id'), 'id_number' => 'ACC-COORD-001',
            'name' => 'Coordinator', 'email' => 'coord@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $this->hte = Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'Fareheaven Boarding House', 'contact_person' => 'Virgie Osorio', 'contact_email' => 'virgie@fareheaven.test']);
    }

    private function create(array $extra = [])
    {
        return $this->actingAs($this->coordinator)->post(route('coordinator.hte-accounts.store'), $extra + [
            'hte_id' => $this->hte->id, 'id_number' => 'ACC-HTE-001', 'name' => 'Virgie Osorio', 'email' => 'virgie@fareheaven.test', 'send_email' => '1',
        ]);
    }

    public function test_the_form_is_prefilled_from_the_partner_agency(): void
    {
        $this->actingAs($this->coordinator)->get(route('coordinator.hte-accounts.create'))->assertOk()
            ->assertSee('data-contact="Virgie Osorio"', false)
            ->assertSee('data-email="virgie@fareheaven.test"', false)
            ->assertSee('Nothing is emailed now')->assertSee('value="ACC-HTE-001"', false);
    }

    public function test_creating_an_account_emails_nothing_yet(): void
    {
        Mail::fake();
        $this->create()->assertRedirect(route('coordinator.hte-accounts.index'));
        Mail::assertNothingSent();
        $this->assertNull(session('provisioned_account')['emailed']);
        $password = session('provisioned_account')['temporary_password'];

        $this->actingAs($this->coordinator)->get(route('coordinator.hte-accounts.index'))
            ->assertSee('Nothing was emailed')->assertSee('Sign-in details not sent yet');

        // the email (sent later with the mail icon) carries the ID number, the password and the sign-in link
        $html = (new HteAccountMail(User::where('id_number', 'ACC-HTE-001')->first(), $password, 'Fareheaven Boarding House', 'PSU - Alaminos City Campus'))->render();
        $this->assertStringContainsString('ACC-HTE-001', $html);
        $this->assertStringContainsString($password, $html);
        $this->assertStringContainsString(route('login'), $html);
    }

    public function test_resetting_the_password_shows_it_without_emailing(): void
    {
        Mail::fake();
        $this->create();
        $representative = HteRepresentative::first();

        $this->actingAs($this->coordinator)->post(route('coordinator.hte-accounts.reset-password', $representative));
        $password = session('provisioned_account')['temporary_password'];
        Mail::assertNothingSent();
        $this->assertTrue(Hash::check($password, $representative->user->fresh()->password));
    }

    public function test_changing_the_agency_email_updates_the_account(): void
    {
        Mail::fake();
        $this->create();
        $representative = HteRepresentative::first();

        // edited on Partner Agencies
        $this->hte->update(['contact_email' => 'virgie.new@gmail.test']);
        $this->assertSame('virgie.new@gmail.test', $representative->user->fresh()->email);
    }

    public function test_an_account_already_out_of_step_is_caught_up_on_the_list(): void
    {
        $this->create(['send_email' => '0']);
        // changed without the model event (e.g. directly in the database)
        Hte::whereKey($this->hte->id)->toBase()->update(['contact_email' => 'virgie.new@gmail.test']);

        $this->actingAs($this->coordinator)->get(route('coordinator.hte-accounts.index'))->assertOk()
            ->assertSee('virgie.new@gmail.test');
        $this->assertSame('virgie.new@gmail.test', User::where('id_number', 'ACC-HTE-001')->value('email'));
    }

    public function test_an_agency_email_used_by_another_account_is_flagged(): void
    {
        Mail::fake();
        $this->create();
        // the coordinator's own address entered as the agency's email
        $this->hte->update(['contact_email' => 'coord@t.local']);
        $representative = HteRepresentative::first();
        $this->assertSame('virgie@fareheaven.test', $representative->user->fresh()->email);

        $this->actingAs($this->coordinator)->post(route('coordinator.hte-accounts.reset-password', $representative));
        $this->assertStringContainsString('coord@t.local is already used by another PSU Intern-Link account (Coordinator', session('provisioned_account')['email_warning']);
        $this->actingAs($this->coordinator)->get(route('coordinator.hte-accounts.index'))
            ->assertSee('already used by another PSU Intern-Link account', false)
            ->assertSee("Not the agency's email (coord@t.local)", false);
    }

}
