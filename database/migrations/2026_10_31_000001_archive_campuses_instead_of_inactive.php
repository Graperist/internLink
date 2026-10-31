<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Campuses are archived, never deleted. A campus that was set to
 * "inactive" is now "archived" (same effect: its accounts can't sign in,
 * its records are kept, and it can be restored).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('campuses')->where('status', 'inactive')->update(['status' => 'archived']);
    }

    public function down(): void
    {
        DB::table('campuses')->where('status', 'archived')->update(['status' => 'inactive']);
    }
};
