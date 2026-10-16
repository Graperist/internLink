<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Signatory names printed automatically on the student forms:
 *   - campuses.registrar_name          (Campus Settings)
 *   - programs.department_chair_name   (Programs page, one per program)
 * The Certificate of Units Earned used to have the Alaminos registrar's
 * name typed into the template; it is moved into the setting here so that
 * form keeps printing it until the coordinator changes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->string('registrar_name')->nullable()->after('college_dean_name');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->string('department_chair_name')->nullable()->after('major');
        });

        DB::table('campuses')->where('code', 'ACC')->whereNull('registrar_name')
            ->update(['registrar_name' => 'Rosel L. Dela Criz, MIT']);
    }

    public function down(): void
    {
        Schema::table('campuses', fn (Blueprint $table) => $table->dropColumn('registrar_name'));
        Schema::table('programs', fn (Blueprint $table) => $table->dropColumn('department_chair_name'));
    }
};
