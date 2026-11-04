<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * The rule for a password a person chooses (first sign-in, change, forgot):
 * at least 8 characters with a capital letter, a small letter, a number and
 * a special character.
 */
class PasswordRules
{
    public static function rules(): array
    {
        return ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()];
    }

    public static function messages(): array
    {
        return [
            'password.min' => 'The password must be at least 8 characters.',
            'password.mixed' => 'The password must have at least one capital letter and one small letter.',
            'password.numbers' => 'The password must have at least one number.',
            'password.symbols' => 'The password must have at least one special character (e.g. ! @ # $ %).',
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }
}
