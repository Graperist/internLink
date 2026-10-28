<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The login page photos, managed by the OJT Coordinator (Campus Administration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_slides', function (Blueprint $table) {
            $table->id();
            $table->string('path');                 // under public/, e.g. uploads/login-slides/abc.jpg
            $table->string('caption')->nullable();  // shown on the photo, e.g. "San Carlos Campus"
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_slides');
    }
};
