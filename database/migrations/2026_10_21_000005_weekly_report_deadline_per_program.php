<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Form 19 deadline is set by the Subject Instructor for their program
 * (Settings in the instructor portal), not by the coordinator. Null = the
 * default of 2 days after each week ends.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('programs', 'weekly_report_due_days')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->unsignedTinyInteger('weekly_report_due_days')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('programs', 'weekly_report_due_days')) {
            Schema::table('programs', fn (Blueprint $table) => $table->dropColumn('weekly_report_due_days'));
        }
    }
};
