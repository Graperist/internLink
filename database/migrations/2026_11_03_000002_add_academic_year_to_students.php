<?php

use App\Support\Term;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Students are enrolled for an academic year (e.g. 2026-2027), so the
 * student list can show one year at a time. Existing students get the
 * academic year they were added in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('academic_year', 9)->nullable()->index();
        });

        DB::table('students')->whereNull('academic_year')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $year = Term::forDate($row->created_at ? Carbon::parse($row->created_at) : now())[0];
                DB::table('students')->where('id', $row->id)->update(['academic_year' => $year]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['academic_year']);
            $table->dropColumn('academic_year');
        });
    }
};
