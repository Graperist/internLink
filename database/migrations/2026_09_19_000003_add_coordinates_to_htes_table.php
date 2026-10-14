<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The HTE's own reference location, resolved automatically from its
     * street address (see GeocodingService) whenever a coordinator
     * creates or edits an HTE - not entered separately by hand. Nullable:
     * geocoding can fail (address not found, service unreachable), and an
     * HTE record must still be usable even if its coordinates are
     * unknown - the DTR distance check simply skips comparison in that
     * case rather than blocking anything.
     */
    public function up(): void
    {
        Schema::table('htes', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('htes', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
