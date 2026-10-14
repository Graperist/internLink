<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per log entry (one per day for Daily Journal / Internship
     * Time Frame, one per week for Weekly Report) under a parent
     * document. The parent document's own status (submitted -> printed
     * -> completed -> approved) still governs the overall print/sign/
     * upload workflow - entries just accumulate underneath it while
     * status allows editing.
     */
    public function up(): void
    {
        Schema::create('document_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->date('entry_date');
            $table->date('entry_end_date')->nullable(); // set only for weekly-cadence entries
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_entries');
    }
};
