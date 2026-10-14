<?php

use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('htes', 'representative_position')) {
            Schema::table('htes', function (Blueprint $table) {
                $table->string('representative_position')->nullable()->after('contact_person');
            });
        }

        $agreements = DocumentRequirement::where('code', 'INTERNSHIP_AGREEMENT')->get();

        foreach ($agreements as $agreement) {
            DocumentFieldDefinition::updateOrCreate(
                [
                    'document_requirement_id' => $agreement->id,
                    'field_key' => 'required_hours',
                ],
                [
                    'label' => 'Required Hours',
                    'group_label' => null,
                    'field_type' => 'text',
                    'validation_rule' => 'numeric',
                    'is_required' => true,
                    'sort_order' => 8,
                ]
            );
        }
    }

    public function down(): void
    {
        $agreements = DocumentRequirement::where('code', 'INTERNSHIP_AGREEMENT')->get();

        foreach ($agreements as $agreement) {
            DocumentFieldDefinition::where('document_requirement_id', $agreement->id)
                ->where('field_key', 'required_hours')
                ->delete();
        }

        if (Schema::hasColumn('htes', 'representative_position')) {
            Schema::table('htes', function (Blueprint $table) {
                $table->dropColumn('representative_position');
            });
        }
    }
};
