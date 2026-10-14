<?php

use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DocumentRequirement::where('code', 'CERTIFICATE_OF_UNITS_EARNED')
            ->each(function ($requirement) {
                DocumentFieldDefinition::firstOrCreate(
                    [
                        'document_requirement_id' => $requirement->id,
                        'field_key' => 'record_total',
                    ],
                    [
                        'label' => 'Total Units',
                        'group_label' => 'Courses Earned',
                        'field_type' => 'text',
                        'is_required' => false,
                        'sort_order' => 19,
                    ]
                );
            });
    }

    public function down(): void
    {
        DocumentRequirement::where('code', 'CERTIFICATE_OF_UNITS_EARNED')
            ->with('fieldDefinitions')
            ->get()
            ->each(function ($requirement) {
                $requirement->fieldDefinitions()
                    ->where('field_key', 'record_total')
                    ->delete();
            });
    }
};
