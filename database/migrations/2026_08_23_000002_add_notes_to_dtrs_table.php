<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            // "Task done today" field from Figure 21 - not in the original
            // schema. Geo-tag verification from that same mockup is NOT
            // implemented here (would need HTE address geocoding to compare
            // against); flagging as a known gap rather than faking it.
            $table->text('notes')->nullable()->after('time_out');
        });
    }

    public function down(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
