<?php

use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DocumentRequirement::where('code', 'PARENT_CONSENT')
            ->get()
            ->each(function (DocumentRequirement $requirement): void {
                $fields = [
                    ['field_key' => 'signed_city', 'label' => 'Signed at', 'field_type' => 'text', 'group_label' => 'Signing', 'sort_order' => 6],
                    ['field_key' => 'signed_date', 'label' => 'This day of', 'field_type' => 'date', 'group_label' => 'Signing', 'sort_order' => 7],
                ];

                foreach ($fields as $field) {
                    DocumentFieldDefinition::firstOrCreate(
                        [
                            'document_requirement_id' => $requirement->id,
                            'field_key' => $field['field_key'],
                        ],
                        array_merge($field, [
                            'is_required' => true,
                            'validation_rule' => null,
                        ])
                    );
                }
            });
    }

    public function down(): void
    {
        DocumentRequirement::where('code', 'PARENT_CONSENT')
            ->get()
            ->each(function (DocumentRequirement $requirement): void {
                DocumentFieldDefinition::where('document_requirement_id', $requirement->id)
                    ->whereIn('field_key', ['signed_city', 'signed_date'])
                    ->delete();
            });
    }
};
