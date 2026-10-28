<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::forceCreate(['code' => 'student', 'name' => 'Student']);
        $this->campus = Campus::forceCreate(['code' => 'AAA', 'name' => 'Campus A', 'status' => 'active']);
        $this->user = User::forceCreate([
            'campus_id' => $this->campus->id,
            'role_id' => $role->id,
            'id_number' => '23-AC-0001',
            'name' => 'Test Student',
            'email' => 'student@test.local',
            'password' => Hash::make('old-password'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
    }

    private function requestCode(): string
    {
        Mail::fake();

        $this->post(route('password.forgot.send'), [
            'id_number' => '23-AC-0001',
        ])->assertRedirect(route('password.forgot.verify'));

        $code = null;
        Mail::assertSent(PasswordResetCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('student@test.local');
        });

        return $code;
    }

    public function test_login_page_has_forgot_password_link(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Forgot password?');
    }

    public function test_verify_page_shows_masked_email(): void
    {
        $this->requestCode();

        $this->get(route('password.forgot.verify'))
            ->assertOk()
            ->assertSee('st*****@test.local', false)
            ->assertDontSee('student@test.local');
    }

    public function test_full_reset_flow(): void
    {
        $code = $this->requestCode();

        $this->post(route('password.forgot.verify.check'), ['code' => $code])
            ->assertRedirect(route('password.forgot.reset'));

        $this->post(route('password.forgot.reset.save'), [
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('brand-new-pass', $this->user->fresh()->password));
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_wrong_code_is_rejected_and_reset_page_stays_locked(): void
    {
        $this->requestCode();

        $this->post(route('password.forgot.verify.check'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->get(route('password.forgot.reset'))->assertRedirect(route('password.forgot'));
        $this->assertTrue(Hash::check('old-password', $this->user->fresh()->password));
    }

    public function test_code_is_burned_after_too_many_wrong_attempts(): void
    {
        $code = $this->requestCode();
        $wrong = $code === '111111' ? '222222' : '111111';

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.forgot.verify.check'), ['code' => $wrong]);
        }

        // The sixth try - even with the right code - is refused.
        $this->post(route('password.forgot.verify.check'), ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_unknown_account_gets_same_response_and_no_email(): void
    {
        Mail::fake();

        $this->post(route('password.forgot.send'), [
            'id_number' => 'does-not-exist',
        ])->assertRedirect(route('password.forgot.verify'));

        Mail::assertNothingSent();
    }
}
