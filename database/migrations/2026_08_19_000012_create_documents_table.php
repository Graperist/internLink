<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
            $table->foreignId('document_requirement_id')->constrained('document_requirements')->restrictOnDelete();
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->string('status')->default('pending'); // pending | submitted | for_review | returned | approved
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
