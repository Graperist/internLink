<?php

namespace App\Support;

use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails and checks the 6-digit code used when a signed-in user changes their
 * password (the forgot-password flow uses the same table and mail).
 */
class PasswordCodeService
{
    public const CODE_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SECONDS = 60;

    /** Create a fresh code for the user and email it to the address on their profile. */
    public function issue(User $user, string $purpose = 'reset'): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordResetCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::CODE_MINUTES),
                'last_sent_at' => now(),
            ],
        );

        try {
            Mail::to($user->email)->send(new PasswordResetCodeMail($user, $code, self::CODE_MINUTES, $purpose));
        } catch (Throwable $e) {
            report($e); // the admin sees it in storage/logs/laravel.log
        }
    }

    /** @return 'ok'|'invalid'|'locked' */
    public function check(User $user, string $code): string
    {
        $record = $user->passwordResetCode;

        if (! $record || $record->isExpired()) {
            return 'invalid';
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            $record->delete();

            return 'locked';
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return 'invalid';
        }

        $record->delete(); // one use only

        return 'ok';
    }

    /** juan.delacruz@gmail.com -> ju*********@gmail.com */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1)));

        return $visible.str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }
}
