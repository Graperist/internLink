<?php

use App\Support\Term;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An HTE account belongs to an academic year and semester (chosen on the
 * add / edit form), and a partner listing archived together with its HTE
 * account is marked so restoring the account brings it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hte_representatives', function (Blueprint $table) {
            $table->string('academic_year', 9)->nullable()->index();
            $table->string('semester', 20)->nullable()->index();
        });
        Schema::table('hte_listings', function (Blueprint $table) {
            $table->boolean('archived_with_account')->default(false);
        });

        // Existing accounts: the term they were made in.
        DB::table('hte_representatives')->orderBy('id')->each(function ($row) {
            [$year, $semester] = Term::forDate(Carbon::parse($row->created_at ?? now()));
            DB::table('hte_representatives')->where('id', $row->id)->update(['academic_year' => $year, 'semester' => $semester]);
        });
    }

    public function down(): void
    {
        Schema::table('hte_representatives', function (Blueprint $table) {
            $table->dropIndex(['academic_year']);
            $table->dropIndex(['semester']);
            $table->dropColumn(['academic_year', 'semester']);
        });
        Schema::table('hte_listings', function (Blueprint $table) {
            $table->dropColumn('archived_with_account');
        });
    }
};
