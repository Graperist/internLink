<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained('deployments')->cascadeOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('hte_representatives')->nullOnDelete();
            $table->date('log_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->decimal('hours_logged', 5, 2)->default(0);
            $table->string('status')->default('pending'); // pending | approved | flagged
            $table->timestamps();

            $table->unique(['deployment_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtrs');
    }
};
