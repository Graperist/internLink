<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained('deployments')->cascadeOnDelete();
            $table->foreignId('hte_representative_id')->constrained('hte_representatives')->restrictOnDelete();
            $table->decimal('rating', 4, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();

            $table->unique('deployment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
