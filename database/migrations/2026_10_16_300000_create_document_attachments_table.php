<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Medical Certificate and the Psychological Test Result are no longer
 * separate requirements: the student attaches both to Form 1 (Application
 * for Internship) and they are reviewed together with it.
 *
 * - document_attachments holds the files attached to a form.
 * - The two old requirements are switched off (kept, not deleted, so the
 *   history stays readable).
 * - Files a student already uploaded under the old requirements are copied
 *   onto their Form 1 as attachments, so nobody has to upload them again.
 */
return new class extends Migration
{
    private const MOVED = [
        'MEDICAL_CERTIFICATE' => 'medical_certificate',
        'PSYCHOLOGICAL_TEST' => 'psychological_test',
    ];

    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 50);
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'kind']);
        });

        $now = now();

        foreach (self::MOVED as $code => $kind) {
            $old = DB::table('documents')
                ->join('document_requirements', 'document_requirements.id', '=', 'documents.document_requirement_id')
                ->where('document_requirements.code', $code)
                ->whereNotNull('documents.file_path')
                ->where('documents.file_path', '!=', '')
                ->select('documents.internship_id', 'documents.file_path', 'documents.original_filename', 'documents.uploaded_by_user_id')
                ->get();

            foreach ($old as $row) {
                $form1 = DB::table('documents')
                    ->join('document_requirements', 'document_requirements.id', '=', 'documents.document_requirement_id')
                    ->where('document_requirements.code', 'APPLICATION_FOR_OJT')
                    ->where('documents.internship_id', $row->internship_id)
                    ->value('documents.id');

                if (! $form1 || DB::table('document_attachments')->where('document_id', $form1)->where('kind', $kind)->exists()) {
                    continue;
                }

                DB::table('document_attachments')->insert([
                    'document_id' => $form1,
                    'kind' => $kind,
                    'file_path' => $row->file_path,
                    'original_filename' => $row->original_filename,
                    'uploaded_by_user_id' => $row->uploaded_by_user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('document_requirements')
            ->whereIn('code', array_keys(self::MOVED))
            ->update(['is_active' => false, 'is_required' => false]);
    }

    public function down(): void
    {
        DB::table('document_requirements')
            ->whereIn('code', array_keys(self::MOVED))
            ->update(['is_active' => true, 'is_required' => true]);

        Schema::dropIfExists('document_attachments');
    }
};
