 <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->string('reviewer_role')->default('instructor')->after('audience');
            $table->string('source_reference')->nullable()->after('reviewer_role');
        });
    }

    public function down(): void
    {
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->dropColumn(['reviewer_role', 'source_reference']);
        });
    }
};
