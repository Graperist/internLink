<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Form 19 deadlines, like MS Teams: each week is due a set number of days
     * after it ends (Campus Settings); a week first sent after that is "Late".
     * first_submitted_at keeps the first time a document was sent, so a week
     * returned for correction and sent again keeps its on-time/late status.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('campuses', 'weekly_report_due_days')) {
            Schema::table('campuses', function (Blueprint $table) {
                $table->unsignedTinyInteger('weekly_report_due_days')->default(2);
            });
        }

        if (! Schema::hasColumn('documents', 'first_submitted_at')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->timestamp('first_submitted_at')->nullable()->after('submitted_at');
            });
        }

        DB::table('documents')->whereNull('first_submitted_at')->whereNotNull('submitted_at')
            ->update(['first_submitted_at' => DB::raw('submitted_at')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('documents', 'first_submitted_at')) {
            Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('first_submitted_at'));
        }
        if (Schema::hasColumn('campuses', 'weekly_report_due_days')) {
            Schema::table('campuses', fn (Blueprint $table) => $table->dropColumn('weekly_report_due_days'));
        }
    }
};
