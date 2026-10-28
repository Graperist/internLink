<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The DTR is now entered on Form 17 (Internship Time Frame): the student
 * types the morning and afternoon time in/out for each date and the system
 * totals the hours. No location check any more. The HTE supervisor approves
 * or declines each day; the instructor can return a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            $table->time('am_in')->nullable()->after('time_out');
            $table->time('am_out')->nullable()->after('am_in');
            $table->time('pm_in')->nullable()->after('am_out');
            $table->time('pm_out')->nullable()->after('pm_in');
            // Who declined/returned the day (the HTE supervisor or the instructor).
            $table->foreignId('declined_by_user_id')->nullable()->after('flag_target')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('declined_by_user_id');
            $table->dropColumn(['am_in', 'am_out', 'pm_in', 'pm_out']);
        });
    }
};
