<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Support\AccountResolver;
use App\Support\AuditLogger;
use App\Support\PasswordCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * "Forgot password?" flow (like Facebook):
 *   1. person gives their ID number (campus is read from the ID)
 *   2. a 6-digit code is emailed to the address on their profile
 *   3. they type the code
 *   4. they choose a new password
 *
 * The screens never reveal whether an account exists - the same message is
 * shown either way.
 */
class ForgotPasswordController extends Controller
{
    private const CODE_MINUTES = 10;      // how long a code is valid
    private const MAX_ATTEMPTS = 5;       // wrong guesses before the code is burned
    private const RESEND_SECONDS = 60;    // minimum gap between emails
    private const RESET_WINDOW_MINUTES = 15; // time allowed to pick the new password after verifying

    // ---------- step 1: ask for the ID ----------

    public function showRequest(): View
    {
        return view('auth.forgot-password');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_number' => ['required', 'string', 'max:60'],
        ]);

        $request->session()->forget('pw_reset');
        $request->session()->put('pw_reset.id_number', $data['id_number']);

        $user = $this->findUser($request);
        $request->session()->put('pw_reset.sent_at', now()->timestamp);

        // HTE accounts keep the password the coordinator gave them.
        if ($user && $user->isHteRepresentative()) {
            $request->session()->forget('pw_reset');

            return back()->withInput()->withErrors(['id_number' => 'HTE supervisor accounts cannot reset their password here. Ask your campus OJT Coordinator for your sign-in details.']);
        }

        if ($user && $user->status === 'active') {
            $request->session()->put('pw_reset.masked_email', $this->maskEmail($user->email));
            $this->issueCode($user, $request);
        } else {
            AuditLogger::record(
                'auth.password_reset_requested',
                'Password reset requested for ID "'.AuditLogger::clean($data['id_number'], 60).'" - no active account',
                null,
                $request,
                campusId: AccountResolver::campusFromId($data['id_number'])?->id,
            );
        }

        return redirect()->route('password.forgot.verify')->with('status', $this->genericSentMessage());
    }

    // ---------- step 2: enter the code ----------

    public function showVerify(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('pw_reset.id_number')) {
            return redirect()->route('password.forgot');
        }

        $sentAt = (int) $request->session()->get('pw_reset.sent_at', 0);
        $cooldown = max(0, self::RESEND_SECONDS - (now()->timestamp - $sentAt));

        return view('auth.forgot-password-verify', [
            'maskedEmail' => $request->session()->get('pw_reset.masked_email'),
            'cooldown' => $cooldown,
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $request->session()->has('pw_reset.id_number')) {
            return redirect()->route('password.forgot');
        }

        $user = $this->findUser($request);

        if ($user && $user->status === 'active') {
            $existing = $user->passwordResetCode;

            if ($existing?->last_sent_at && $existing->last_sent_at->gt(now()->subSeconds(self::RESEND_SECONDS))) {
                $wait = self::RESEND_SECONDS - (int) $existing->last_sent_at->diffInSeconds(now(), true);

                return back()->withErrors(['code' => 'Please wait '.max($wait, 1).' seconds before asking for a new code.']);
            }

            $request->session()->put('pw_reset.masked_email', $this->maskEmail($user->email));
            $this->issueCode($user, $request);
        }

        $request->session()->put('pw_reset.sent_at', now()->timestamp);

        return back()->with('status', $this->genericSentMessage());
    }

    public function verify(Request $request): RedirectResponse
    {
        if (! $request->session()->has('pw_reset.id_number')) {
            return redirect()->route('password.forgot');
        }

        $request->validate(['code' => ['required', 'digits:6']], [
            'code.digits' => 'The code is 6 digits.',
        ]);

        $user = $this->findUser($request);
        $record = $user?->passwordResetCode;
        $invalid = ValidationException::withMessages(['code' => 'That code is invalid or has expired. Please try again or request a new one.']);

        if (! $user || ! $record || $record->isExpired()) {
            throw $invalid;
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            $record->delete();

            throw ValidationException::withMessages(['code' => 'Too many wrong attempts. Please request a new code.']);
        }

        if (! Hash::check($request->input('code'), $record->code_hash)) {
            $record->increment('attempts');

            AuditLogger::record('auth.password_reset_failed', 'Wrong password reset code entered', $user, $request);

            throw $invalid;
        }

        // Code is good - burn it so it can't be used twice, and allow the reset step.
        $record->delete();
        $request->session()->put('pw_reset.user_id', $user->id);
        $request->session()->put('pw_reset.verified_at', now()->timestamp);

        return redirect()->route('password.forgot.reset');
    }

    // ---------- step 3: choose a new password ----------

    public function showReset(Request $request): View|RedirectResponse
    {
        if (! $this->verifiedUser($request)) {
            return redirect()->route('password.forgot')->withErrors(['id_number' => 'Your reset session expired. Please start again.']);
        }

        return view('auth.forgot-password-reset');
    }

    public function reset(Request $request): RedirectResponse
    {
        $user = $this->verifiedUser($request);

        if (! $user || $user->isHteRepresentative()) {
            return redirect()->route('password.forgot')->withErrors(['id_number' => 'Your reset session expired. Please start again.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        // Sign the account out everywhere else (only applies to the database session driver).
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        AuditLogger::record('auth.password_reset', 'Password reset using an emailed code', $user, $request);

        $request->session()->forget('pw_reset');

        return redirect()->route('login')->with('status', 'Your password has been changed. You can now sign in.');
    }

    // ---------- helpers ----------

    private function findUser(Request $request): ?User
    {
        return AccountResolver::findUser((string) $request->session()->get('pw_reset.id_number'));
    }

    private function verifiedUser(Request $request): ?User
    {
        $userId = $request->session()->get('pw_reset.user_id');
        $verifiedAt = (int) $request->session()->get('pw_reset.verified_at', 0);

        if (! $userId || now()->timestamp - $verifiedAt > self::RESET_WINDOW_MINUTES * 60) {
            return null;
        }

        return User::where('id', $userId)->where('status', 'active')->first();
    }

    private function issueCode(User $user, Request $request): void
    {
        app(PasswordCodeService::class)->issue($user, 'reset');

        AuditLogger::record('auth.password_reset_requested', 'Password reset code emailed', $user, $request);
    }

    /** juan.delacruz@gmail.com -> ju*********@gmail.com */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1)));

        return $visible.str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }

    private function genericSentMessage(): string
    {
        return 'If that account exists, we sent a 6-digit code to the email address on its profile. It expires in '.self::CODE_MINUTES.' minutes.';
    }
}
