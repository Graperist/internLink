<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Captures where the student actually was, via the browser's own
     * Geolocation API, at the moment they clocked in and clocked out -
     * separate columns for each since a student could plausibly be in a
     * different place at the end of the day than at the start. Nullable
     * throughout: a student who denies location permission, or whose
     * device/browser doesn't support it, can still clock in and out -
     * this is evidence for the HTE to weigh, not a hard requirement that
     * blocks attendance.
     */
    public function up(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            $table->decimal('time_in_latitude', 10, 7)->nullable()->after('time_in');
            $table->decimal('time_in_longitude', 10, 7)->nullable()->after('time_in_latitude');
            $table->decimal('time_out_latitude', 10, 7)->nullable()->after('time_out');
            $table->decimal('time_out_longitude', 10, 7)->nullable()->after('time_out_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            $table->dropColumn(['time_in_latitude', 'time_in_longitude', 'time_out_latitude', 'time_out_longitude']);
        });
    }
};
