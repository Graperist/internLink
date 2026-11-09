<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Existing HTE supervisor accounts set their own password on their next sign-in. */
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'hte_representative')->value('id');
        if ($roleId) {
            DB::table('users')->where('role_id', $roleId)->update(['must_change_password' => true]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: whoever already changed their password keeps it.
    }
};
