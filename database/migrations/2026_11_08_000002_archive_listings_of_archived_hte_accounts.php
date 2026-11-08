<?php

use App\Support\Term;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HTE accounts archived before archiving also took the agency off the
 * partner list: take those agencies off this and later terms' lists now
 * (when none of their accounts is still active). Restoring the account
 * puts them back.
 */
return new class extends Migration
{
    public function up(): void
    {
        $archivedHtes = DB::table('hte_representatives')
            ->join('users', 'users.id', '=', 'hte_representatives.user_id')
            ->where('users.status', 'archived')
            ->pluck('hte_representatives.hte_id')
            ->unique();

        foreach ($archivedHtes as $hteId) {
            $stillActive = DB::table('hte_representatives')
                ->join('users', 'users.id', '=', 'hte_representatives.user_id')
                ->where('hte_representatives.hte_id', $hteId)
                ->where('users.status', '!=', 'archived')
                ->exists();
            if ($stillActive) {
                continue;
            }

            DB::table('hte_listings')
                ->where('hte_id', $hteId)
                ->whereNull('archived_at')
                ->where('academic_year', '>=', Term::current()[0])
                ->update(['archived_at' => now(), 'archived_with_account' => true]);
        }
    }

    public function down(): void
    {
        DB::table('hte_listings')->where('archived_with_account', true)
            ->update(['archived_at' => null, 'archived_with_account' => false]);
    }
};
