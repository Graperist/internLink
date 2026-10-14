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

class ChangePasswordCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::forceCreate(['code' => 'student', 'name' => 'Student']);
        $campus = Campus::forceCreate(['code' => 'AAA', 'name' => 'Campus A', 'status' => 'active']);
        $this->user = User::forceCreate([
            'campus_id' => $campus->id,
            'role_id' => $role->id,
            'id_number' => '23-AC-0002',
            'name' => 'Test Student',
            'email' => 'student@test.local',
            'password' => Hash::make('old-password'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
    }

    private function submitForm(): string
    {
        Mail::fake();

        $this->actingAs($this->user)->put(route('password.update'), [
            'current_password' => 'old-password',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('password.change.verify'));

        $code = null;
        Mail::assertSent(PasswordResetCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('student@test.local');
        });

        return $code;
    }

    public function test_password_is_not_changed_until_the_code_is_confirmed(): void
    {
        $code = $this->submitForm();

        $this->assertTrue(Hash::check('old-password', $this->user->fresh()->password));

        $this->actingAs($this->user)->post(route('password.change.verify.check'), ['code' => $code])
            ->assertRedirect(route('student.dashboard'));

        $this->assertTrue(Hash::check('brand-new-pass', $this->user->fresh()->password));
    }

    public function test_wrong_code_keeps_the_old_password(): void
    {
        $this->submitForm();

        $this->actingAs($this->user)->post(route('password.change.verify.check'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('old-password', $this->user->fresh()->password));
    }

    public function test_first_time_temporary_password_change_needs_no_code(): void
    {
        $this->user->forceFill(['must_change_password' => true])->save();
        Mail::fake();

        $this->actingAs($this->user)->put(route('password.update'), [
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('student.dashboard'));

        Mail::assertNothingSent();
        $this->assertTrue(Hash::check('brand-new-pass', $this->user->fresh()->password));
    }
}
