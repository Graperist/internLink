<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use App\Support\PasswordCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    private const PENDING_MINUTES = 15; // time allowed to type the code after submitting the form

    public function __construct(private PasswordCodeService $codes) {}

    public function show(): View|RedirectResponse
    {
        if (auth()->user()?->isHteRepresentative()) {
            return $this->hteCannotChange();
        }

        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user?->isHteRepresentative()) {
            return $this->hteCannotChange();
        }

        $validated = $request->validate([
            // Skip the "current password" check the very first time - a
            // coordinator-generated temp password is meant to be handed off
            // verbally/on-screen once, not typed back in by the student.
            'current_password' => $user->must_change_password
                ? ['nullable']
                : ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        // First-time change (temporary password): no code - the person has never
        // been able to confirm their email yet, and must not get locked out.
        if ($user->must_change_password) {
            $user->update([
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
            ]);

            return redirect()
                ->route($user->dashboardRouteName())
                ->with('status', 'Your password has been updated.');
        }

        // Normal change: hold the new password (already hashed) in the session and
        // only apply it once the emailed code is confirmed.
        $request->session()->put('pw_change', [
            'hash' => Hash::make($validated['password']),
            'at' => now()->timestamp,
            'sent_at' => now()->timestamp,
        ]);

        $this->codes->issue($user, 'change');
        AuditLogger::record('auth.password_change_requested', 'Password change code emailed', $user, $request);

        return redirect()->route('password.change.verify')
            ->with('status', 'We sent a 6-digit code to the email address on your profile.');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isHteRepresentative()) {
            return $this->hteCannotChange();
        }
        if (! $this->pending($request)) {
            return redirect()->route('password.change')->withErrors(['password' => 'Please enter your new password again.']);
        }

        $sentAt = (int) $request->session()->get('pw_change.sent_at', 0);

        return view('auth.forgot-password-verify', [
            'maskedEmail' => PasswordCodeService::maskEmail($request->user()->email),
            'cooldown' => max(0, PasswordCodeService::RESEND_SECONDS - (now()->timestamp - $sentAt)),
            'action' => route('password.change.verify.check'),
            'resendAction' => route('password.change.resend'),
            'backUrl' => route('password.change'),
            'backLabel' => 'Cancel',
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $this->pending($request)) {
            return redirect()->route('password.change');
        }

        $sentAt = (int) $request->session()->get('pw_change.sent_at', 0);
        $wait = PasswordCodeService::RESEND_SECONDS - (now()->timestamp - $sentAt);

        if ($wait > 0) {
            return back()->withErrors(['code' => 'Please wait '.$wait.' seconds before asking for a new code.']);
        }

        $this->codes->issue($request->user(), 'change');
        $request->session()->put('pw_change.sent_at', now()->timestamp);

        return back()->with('status', 'We sent a new 6-digit code to the email address on your profile.');
    }

    public function verify(Request $request): RedirectResponse
    {
        if ($request->user()?->isHteRepresentative()) {
            return $this->hteCannotChange();
        }
        if (! $this->pending($request)) {
            return redirect()->route('password.change')->withErrors(['password' => 'Please enter your new password again.']);
        }

        $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'The code is 6 digits.']);

        $user = $request->user();
        $result = $this->codes->check($user, $request->input('code'));

        if ($result === 'locked') {
            throw ValidationException::withMessages(['code' => 'Too many wrong attempts. Please request a new code.']);
        }

        if ($result === 'invalid') {
            AuditLogger::record('auth.password_change_failed', 'Wrong password change code entered', $user, $request);

            throw ValidationException::withMessages(['code' => 'That code is invalid or has expired. Please try again or request a new one.']);
        }

        $user->forceFill([
            'password' => $request->session()->get('pw_change.hash'),
            'must_change_password' => false,
        ])->save();

        // Sign out the account everywhere else (database session driver only).
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        AuditLogger::record('auth.password_changed', 'Password changed using an emailed code', $user, $request);

        $request->session()->forget('pw_change');

        return redirect()
            ->route($user->dashboardRouteName())
            ->with('status', 'Your password has been updated.');
    }

    private function pending(Request $request): bool
    {
        $pending = $request->session()->get('pw_change');

        return $pending
            && now()->timestamp - (int) ($pending['at'] ?? 0) <= self::PENDING_MINUTES * 60;
    }

    /** HTE accounts are given by the coordinator for evaluations only; they keep that password. */
    private function hteCannotChange(): RedirectResponse
    {
        return redirect()->route('hte.evaluations')
            ->with('status', 'HTE supervisor accounts keep the password given by the OJT Coordinator.');
    }
}
