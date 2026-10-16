<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Photocopy of Parent/Guardian Valid ID is no longer a separate
 * requirement: the student attaches it to Form 3 (Consent and Waiver).
 * IDs already uploaded are copied onto the student's Form 3, and the old
 * requirement is switched off (kept so its history stays readable).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $old = DB::table('documents')
            ->join('document_requirements', 'document_requirements.id', '=', 'documents.document_requirement_id')
            ->where('document_requirements.code', 'PARENT_GUARDIAN_ID')
            ->whereNotNull('documents.file_path')
            ->where('documents.file_path', '!=', '')
            ->select('documents.internship_id', 'documents.file_path', 'documents.original_filename', 'documents.uploaded_by_user_id')
            ->get();

        foreach ($old as $row) {
            $form3 = DB::table('documents')
                ->join('document_requirements', 'document_requirements.id', '=', 'documents.document_requirement_id')
                ->where('document_requirements.code', 'PARENT_CONSENT')
                ->where('documents.internship_id', $row->internship_id)
                ->value('documents.id');

            if (! $form3 || DB::table('document_attachments')->where('document_id', $form3)->where('kind', 'parent_guardian_id')->exists()) {
                continue;
            }

            DB::table('document_attachments')->insert([
                'document_id' => $form3,
                'kind' => 'parent_guardian_id',
                'file_path' => $row->file_path,
                'original_filename' => $row->original_filename,
                'uploaded_by_user_id' => $row->uploaded_by_user_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('document_requirements')
            ->where('code', 'PARENT_GUARDIAN_ID')
            ->update(['is_active' => false, 'is_required' => false]);
    }

    public function down(): void
    {
        DB::table('document_attachments')->where('kind', 'parent_guardian_id')->delete();

        DB::table('document_requirements')
            ->where('code', 'PARENT_GUARDIAN_ID')
            ->update(['is_active' => true, 'is_required' => true]);
    }
};
