<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedInteger('required_hours')->nullable()->default(null)->change();
        });

        Schema::table('internships', function (Blueprint $table) {
            $table->unsignedInteger('required_hours')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Existing configured values are preserved. A rollback restores the old
        // schema default for future inserts but does not overwrite current data.
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedInteger('required_hours')->default(486)->change();
        });

        Schema::table('internships', function (Blueprint $table) {
            $table->unsignedInteger('required_hours')->default(486)->change();
        });
    }
};
