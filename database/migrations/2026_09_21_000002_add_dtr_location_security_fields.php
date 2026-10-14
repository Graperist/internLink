<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('htes', function (Blueprint $table) {
            if (! Schema::hasColumn('htes', 'latitude')) $table->decimal('latitude', 10, 7)->nullable();
            if (! Schema::hasColumn('htes', 'longitude')) $table->decimal('longitude', 10, 7)->nullable();
            if (! Schema::hasColumn('htes', 'geofence_radius_m')) $table->unsignedInteger('geofence_radius_m')->default(100);
            if (! Schema::hasColumn('htes', 'max_gps_accuracy_m')) $table->decimal('max_gps_accuracy_m', 8, 2)->default(75);
        });

        Schema::table('dtrs', function (Blueprint $table) {
            // Preserve the original GPS evidence already used by the existing
            // browser-based DTR feature. These checks make the migration safe
            // even if the earlier GPS migration has not been run yet.
            if (! Schema::hasColumn('dtrs', 'clock_in_latitude')) $table->decimal('clock_in_latitude', 10, 7)->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_in_longitude')) $table->decimal('clock_in_longitude', 10, 7)->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_in_accuracy')) $table->decimal('clock_in_accuracy', 8, 2)->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_out_latitude')) $table->decimal('clock_out_latitude', 10, 7)->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_out_longitude')) $table->decimal('clock_out_longitude', 10, 7)->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_out_accuracy')) $table->decimal('clock_out_accuracy', 8, 2)->nullable();

            if (! Schema::hasColumn('dtrs', 'original_time_in')) $table->time('original_time_in')->nullable();
            if (! Schema::hasColumn('dtrs', 'original_time_out')) $table->time('original_time_out')->nullable();
            if (! Schema::hasColumn('dtrs', 'flagged_by')) $table->foreignId('flagged_by')->nullable()->constrained('hte_representatives')->nullOnDelete();
            if (! Schema::hasColumn('dtrs', 'flagged_at')) $table->timestamp('flagged_at')->nullable();
            if (! Schema::hasColumn('dtrs', 'flag_reason')) $table->string('flag_reason')->nullable();
            if (! Schema::hasColumn('dtrs', 'flag_note')) $table->text('flag_note')->nullable();
            if (! Schema::hasColumn('dtrs', 'resolved_by')) $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('dtrs', 'resolved_at')) $table->timestamp('resolved_at')->nullable();

            if (! Schema::hasColumn('dtrs', 'clock_in_distance_m')) $table->decimal('clock_in_distance_m', 9, 2)->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_in_location_status')) $table->string('clock_in_location_status')->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_in_location_flags')) $table->text('clock_in_location_flags')->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_in_captured_at')) $table->timestamp('clock_in_captured_at')->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_out_distance_m')) $table->decimal('clock_out_distance_m', 9, 2)->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_out_location_status')) $table->string('clock_out_location_status')->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_out_location_flags')) $table->text('clock_out_location_flags')->nullable();
            if (! Schema::hasColumn('dtrs', 'clock_out_captured_at')) $table->timestamp('clock_out_captured_at')->nullable();
        });
    }

    public function down(): void
    {
        // Deliberately conservative: existing installations may also have the
        // earlier flag/audit migration. Do not drop shared columns here.
    }
};
