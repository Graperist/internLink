<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets the HTE supervisor verify the clock-in and the clock-out of a DTR
     * entry independently. The entry's overall `status` becomes 'approved'
     * (and hours are credited) only once BOTH events are verified.
     */
    public function up(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            if (! Schema::hasColumn('dtrs', 'clock_in_verified_by')) {
                $table->foreignId('clock_in_verified_by')->nullable()->constrained('hte_representatives')->nullOnDelete();
            }
            if (! Schema::hasColumn('dtrs', 'clock_in_verified_at')) {
                $table->timestamp('clock_in_verified_at')->nullable();
            }
            if (! Schema::hasColumn('dtrs', 'clock_out_verified_by')) {
                $table->foreignId('clock_out_verified_by')->nullable()->constrained('hte_representatives')->nullOnDelete();
            }
            if (! Schema::hasColumn('dtrs', 'clock_out_verified_at')) {
                $table->timestamp('clock_out_verified_at')->nullable();
            }
        });

        // Entries that were already approved under the old all-or-nothing flow
        // count as having both events verified.
        DB::table('dtrs')->where('status', 'approved')->update([
            'clock_in_verified_by' => DB::raw('verified_by'),
            'clock_in_verified_at' => DB::raw('updated_at'),
            'clock_out_verified_by' => DB::raw('verified_by'),
            'clock_out_verified_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clock_in_verified_by');
            $table->dropConstrainedForeignId('clock_out_verified_by');
            $table->dropColumn(['clock_in_verified_at', 'clock_out_verified_at']);
        });
    }
};
