<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The evaluation editing deadline is set by the Subject Instructor for their
 * own program (on Evaluations), not campus-wide by the coordinator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedSmallInteger('evaluation_edit_days')->default(7);
        });
        if (Schema::hasColumn('campuses', 'evaluation_edit_days')) {
            foreach (DB::table('campuses')->get(['id', 'evaluation_edit_days']) as $campus) {
                DB::table('programs')->where('campus_id', $campus->id)->update(['evaluation_edit_days' => $campus->evaluation_edit_days]);
            }
            Schema::table('campuses', function (Blueprint $table) {
                $table->dropColumn('evaluation_edit_days');
            });
        }
    }

    public function down(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->unsignedSmallInteger('evaluation_edit_days')->default(7);
        });
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('evaluation_edit_days');
        });
    }
};
