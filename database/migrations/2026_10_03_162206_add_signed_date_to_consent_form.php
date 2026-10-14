<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $requirementId = DB::table('document_requirements')
            ->where('code', 'TRAINING_LIABILITY')
            ->value('id');

        if (! $requirementId) {
            return;
        }

        $exists = DB::table('document_field_definitions')
            ->where('document_requirement_id', $requirementId)
            ->where('field_key', 'signed_date')
            ->exists();

        if (! $exists) {
            DB::table('document_field_definitions')->insert([
                'document_requirement_id' => $requirementId,
                'field_key' => 'signed_date',
                'label' => 'This day of',
                'field_type' => 'date',
                'is_required' => true,
                'sort_order' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $requirementId = DB::table('document_requirements')
            ->where('code', 'TRAINING_LIABILITY')
            ->value('id');

        if ($requirementId) {
            DB::table('document_field_definitions')
                ->where('document_requirement_id', $requirementId)
                ->where('field_key', 'signed_date')
                ->delete();
        }
    }
};
