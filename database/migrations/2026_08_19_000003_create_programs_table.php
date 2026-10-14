<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->string('code');   // e.g. BSIT, BSBA-OM, BSBA-FM, BSHM, BSTM
            $table->string('name');
            $table->unsignedInteger('required_hours')->default(486);
            $table->timestamps();

            $table->unique(['campus_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
