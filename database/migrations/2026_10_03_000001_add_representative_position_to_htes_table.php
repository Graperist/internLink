<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('htes', 'representative_position')) {
            Schema::table('htes', function (Blueprint $table) {
                $table->string('representative_position')->nullable()->after('contact_person');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('htes', 'representative_position')) {
            Schema::table('htes', function (Blueprint $table) {
                $table->dropColumn('representative_position');
            });
        }
    }
};
