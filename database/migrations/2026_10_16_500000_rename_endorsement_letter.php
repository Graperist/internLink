<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Form 6 is shown as "Form 6 – Endorsement Letter", without "(Rev. 2, 2024)". */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_requirements')
            ->where('code', 'ENDORSEMENT_LETTER')
            ->where('name', 'like', '%(Rev. 2, 2024)%')
            ->update(['name' => 'Form 6 – Endorsement Letter']);
    }

    public function down(): void
    {
        DB::table('document_requirements')
            ->where('code', 'ENDORSEMENT_LETTER')
            ->where('name', 'Form 6 – Endorsement Letter')
            ->update(['name' => 'Form 6 – Endorsement Letter (Rev. 2, 2024)']);
    }
};
