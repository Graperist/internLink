<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();   // e.g. ACC, BC, LC, UC
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('status')->default('active'); // active | inactive
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campuses');
    }
};
