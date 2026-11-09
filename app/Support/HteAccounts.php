<?php

namespace App\Support;

use App\Mail\HteAccountMail;
use App\Models\Campus;
use App\Models\Hte;
use App\Models\HteRepresentative;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * HTE portal accounts. They are made (from Add partner agency or HTE
 * Accounts) WITHOUT emailing anything; the sign-in details are emailed
 * later with the mail icon, and only once every intern placed at the
 * agency has finished the internship (the HTE account is for evaluating
 * the interns).
 */
class HteAccounts
{
    /** The next free ID like ACC-HTE-003 for the campus. */
    public static function nextIdNumber(?Campus $campus): string
    {
        $prefix = strtoupper($campus?->code ?: 'PSU').'-HTE-';
        $used = User::where('id_number', 'like', $prefix.'%')->pluck('id_number')
            ->map(fn ($id) => (int) substr($id, strlen($prefix)))->all();
        $n = 1;
        while (in_array($n, $used, true)) {
            $n++;
        }

        return $prefix.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /** Make the account (random password, nothing emailed). */
    public static function create(Hte $hte, string $idNumber, string $name, string $email): HteRepresentative
    {
        $role = Role::where('code', 'hte_representative')->firstOrFail();

        return DB::transaction(function () use ($hte, $idNumber, $name, $email, $role) {
            $user = User::create([
                'campus_id' => $hte->campus_id,
                'role_id' => $role->id,
                'id_number' => $idNumber,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::password(16)),
                'status' => 'active',
                'must_change_password' => true,   // set their own password on first sign-in
            ]);

            return HteRepresentative::create(['user_id' => $user->id, 'hte_id' => $hte->id]);
        });
    }

    /**
     * Can the sign-in details be sent yet? Only when the agency had interns
     * and none of them is still pending or on the job.
     *
     * @return array{ready: bool, reason: string}
     */
    public static function readiness(Hte $hte): array
    {
        $statuses = $hte->deployments()->where('status', '!=', 'terminated')->pluck('status');
        $ongoing = $statuses->filter(fn ($s) => in_array($s, ['pending', 'active'], true))->count();

        return match (true) {
            $statuses->isEmpty() => ['ready' => false, 'reason' => 'No interns have been placed at this agency yet.'],
            $ongoing > 0 => ['ready' => false, 'reason' => $ongoing.' '.Str::plural('intern', $ongoing).' at this agency '.($ongoing === 1 ? 'has' : 'have').' not finished the internship yet.'],
            default => ['ready' => true, 'reason' => 'All interns at this agency have finished. Send the sign-in details so the HTE can evaluate them.'],
        };
    }

    /**
     * Email the sign-in details: a new password (the old one is never
     * stored readable), sent to the account's email.
     *
     * @return array{sent: bool, error: ?string, password: string}
     */
    public static function sendCredentials(HteRepresentative $representative): array
    {
        $representative->loadMissing('user', 'hte');
        // Use the agency's current contact email when it can.
        $representative->hte->syncRepresentativeEmails();
        $representative->load('user');
        $user = $representative->user;

        if (blank($user->email)) {
            return ['sent' => false, 'error' => 'The account has no email address.', 'password' => ''];
        }
        if (config('mail.default') === 'log') {
            return ['sent' => false, 'error' => 'Email sending is not set up on this server (MAIL_MAILER is "log"). Set the MAIL_ settings and redeploy.', 'password' => ''];
        }

        $password = Str::password(10, symbols: false);
        $oldHash = $user->password;
        $oldMustChange = (bool) $user->must_change_password;
        $user->update(['password' => Hash::make($password), 'must_change_password' => true]);

        try {
            Mail::to($user->email)->send(new HteAccountMail(
                $user, $password, $representative->hte->name,
                $user->campus?->name ?? 'Pangasinan State University', false,
            ));
        } catch (\Throwable $e) {
            report($e);
            $user->update(['password' => $oldHash, 'must_change_password' => $oldMustChange]);   // nothing was sent: keep the old password

            return ['sent' => false, 'error' => Str::limit(trim(strtok($e->getMessage(), "\n")), 220), 'password' => ''];
        }

        $representative->update(['credentials_sent_at' => now()]);

        return ['sent' => true, 'error' => null, 'password' => $password];
    }
}
