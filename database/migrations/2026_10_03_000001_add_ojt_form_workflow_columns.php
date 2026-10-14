<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->string('form_number')->nullable()->after('code');
            $table->unsignedInteger('display_order')->default(0)->after('name');
            $table->string('audience')->default('student')->after('display_order');
            $table->string('workflow_mode')->default('upload')->after('audience');
            $table->boolean('is_active')->default(true)->after('workflow_mode');

            $table->index(['campus_id', 'audience', 'is_active'], 'doc_req_scope_index');
            $table->index(['campus_id', 'display_order'], 'doc_req_display_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->dropIndex('doc_req_scope_index');
            $table->dropIndex('doc_req_display_order_index');
            $table->dropColumn([
                'form_number',
                'display_order',
                'audience',
                'workflow_mode',
                'is_active',
            ]);
        });
    }
};
