<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_entry_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_entry_id')->constrained('document_entries')->cascadeOnDelete();
            $table->string('field_key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['document_entry_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_entry_values');
    }
};
