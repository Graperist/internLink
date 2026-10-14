<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            // Which part of the entry the HTE is flagging: 'clock_in', 'clock_out', or 'both'.
            // Nullable so existing flagged rows (flagged before this feature existed) simply
            // show no specific target rather than breaking.
            if (! Schema::hasColumn('dtrs', 'flag_target')) {
                $table->string('flag_target')->nullable()->after('flag_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            if (Schema::hasColumn('dtrs', 'flag_target')) {
                $table->dropColumn('flag_target');
            }
        });
    }
};
