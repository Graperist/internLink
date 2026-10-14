<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defines the structured fields a given document_requirement asks for
     * when it uses the "fill in-system, print, sign, mark complete, upload
     * once" workflow, rather than a plain file upload. A requirement with
     * no rows here simply keeps behaving as a plain upload - this is
     * additive, not a replacement for every requirement.
     */
    public function up(): void
    {
        Schema::create('document_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_requirement_id')->constrained('document_requirements')->cascadeOnDelete();
            $table->string('field_key');
            $table->string('label');
            $table->string('field_type')->default('text'); // text | textarea | date
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['document_requirement_id', 'field_key'], 'doc_field_defs_requirement_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_field_definitions');
    }
};
