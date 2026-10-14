<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Nullable because documents already exist without it, and a
            // student's own future upload flow may not always route through
            // an authenticated "uploader" the same way a coordinator's does.
            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->after('document_requirement_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by_user_id');
        });
    }
};
