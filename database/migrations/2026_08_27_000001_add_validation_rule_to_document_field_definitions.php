<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a field declare what "correct" means for it (e.g., a phone
     * number, a number within a sane age range, one of a fixed set of
     * values), so the system can check the actual content of an answer
     * rather than just whether it was filled in at all. Nullable - a
     * field with no rule simply isn't format-checked, same as today.
     */
    public function up(): void
    {
        Schema::table('document_field_definitions', function (Blueprint $table) {
            $table->string('validation_rule')->nullable()->after('field_type');
        });
    }

    public function down(): void
    {
        Schema::table('document_field_definitions', function (Blueprint $table) {
            $table->dropColumn('validation_rule');
        });
    }
};
