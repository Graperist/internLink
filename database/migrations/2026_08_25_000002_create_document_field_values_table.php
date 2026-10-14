<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The student's actual entered values for a document's structured
     * fields (e.g., Parent Name for the Application for OJT). One row per
     * field per document - overwritten in place on resubmission, matching
     * how plain file uploads already behave elsewhere in the app (no
     * version history).
     */
    public function up(): void
    {
        Schema::create('document_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('field_key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_field_values');
    }
};
