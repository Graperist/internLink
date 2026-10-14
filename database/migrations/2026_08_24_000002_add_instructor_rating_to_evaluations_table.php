<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            // Previously required - but the HTE Portal isn't built yet, so
            // no hte_representative_id can exist for a new row. Nullable so
            // an instructor can start (and own) an evaluation before the HTE
            // side has submitted theirs; the HTE fields fill in later once
            // that portal exists.
            $table->foreignId('hte_representative_id')->nullable()->change();

            $table->decimal('instructor_rating', 4, 2)->nullable()->after('rating');
            $table->foreignId('instructor_id')->nullable()->after('instructor_rating')
                ->constrained('instructors')->nullOnDelete();
            $table->text('instructor_remarks')->nullable()->after('remarks');
            $table->dateTime('instructor_submitted_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instructor_id');
            $table->dropColumn(['instructor_rating', 'instructor_remarks', 'instructor_submitted_at']);
            $table->foreignId('hte_representative_id')->nullable(false)->change();
        });
    }
};
