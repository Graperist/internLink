<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Form 10 - Memorandum of Agreement becomes a pre-deployment requirement
 * the student uploads: the MOA itself is processed outside InternLink, so
 * the student only uploads scans of the signed MOA (several files and
 * several submissions are allowed). The instructor reviews it like any
 * other upload.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_requirements')->where('code', 'MOA')->update([
            'name' => 'Form 10 – Memorandum of Agreement',
            'form_number' => '10',
            'stage' => 'pre_deployment',
            'audience' => 'student',
            'workflow_mode' => 'upload',
            'is_active' => true,
            'is_required' => true,
            'display_order' => 80,
        ]);
    }

    public function down(): void
    {
        DB::table('document_requirements')->where('code', 'MOA')->update([
            'name' => 'Form 10 – Memorandum of Agreement (Outside InternLink Scope)',
            'audience' => 'coordinator',
            'is_active' => false,
            'is_required' => false,
        ]);
    }
};
