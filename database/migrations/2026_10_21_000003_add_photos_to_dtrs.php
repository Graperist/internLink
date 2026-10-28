<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photo taken at the HTE for every time in and time out, which the HTE
 * supervisor looks at when approving the day:
 * {"am_in": {"path": "...", "at": "2026-10-21 07:58:12"}, "am_out": ..., "pm_in": ..., "pm_out": ...}
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dtrs', 'photos')) {
            Schema::table('dtrs', function (Blueprint $table) {
                $table->json('photos')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dtrs', 'photos')) {
            Schema::table('dtrs', fn (Blueprint $table) => $table->dropColumn('photos'));
        }
    }
};
