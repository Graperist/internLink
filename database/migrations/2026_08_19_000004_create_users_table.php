<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('id_number')->nullable(); // student/employee ID shown at login
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status')->default('active'); // active | inactive | suspended
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['campus_id', 'id_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
