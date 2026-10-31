<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Coordinators are archived, never deleted: a deactivated coordinator is now "archived". */
return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'coordinator')->value('id');
        if ($roleId) {
            DB::table('users')->where('role_id', $roleId)->where('status', 'inactive')->update(['status' => 'archived']);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('code', 'coordinator')->value('id');
        if ($roleId) {
            DB::table('users')->where('role_id', $roleId)->where('status', 'archived')->update(['status' => 'inactive']);
        }
    }
};
