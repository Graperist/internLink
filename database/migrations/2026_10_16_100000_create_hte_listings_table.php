<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partner Agency repository: which HTEs a program works with in a given
 * academic year + semester. The HTE itself (name, address, contacts, MOA,
 * DTR location) stays one shared campus record; a listing ties it to a
 * program for one term. Entered by that program's subject instructor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hte_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignId('hte_id')->constrained('htes')->cascadeOnDelete();
            $table->string('academic_year', 9);   // e.g. 2026-2027
            $table->string('semester', 20);       // 1st Semester | 2nd Semester | Midyear
            $table->unsignedInteger('slots')->nullable(); // how many interns the HTE can take
            $table->string('remarks', 500)->nullable();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['program_id', 'hte_id', 'academic_year', 'semester'], 'hte_listings_unique_term');
            $table->index(['campus_id', 'academic_year', 'semester']);
        });

        // Backfill: every HTE that already has a deployment is listed for
        // the deployed student's program in the term the deployment started
        // (or was created), so existing data shows up in the repository.
        $rows = DB::table('deployments')
            ->join('internships', 'internships.id', '=', 'deployments.internship_id')
            ->select('internships.campus_id', 'internships.program_id', 'deployments.hte_id', 'deployments.start_date', 'deployments.created_at')
            ->get();

        $seen = [];
        foreach ($rows as $row) {
            $date = CarbonImmutable::parse($row->start_date ?? $row->created_at ?? now());
            $month = (int) $date->format('n');
            $startYear = $month >= 8 ? (int) $date->format('Y') : (int) $date->format('Y') - 1;
            $semester = $month >= 8 ? '1st Semester' : ($month <= 5 ? '2nd Semester' : 'Midyear');
            $ay = $startYear.'-'.($startYear + 1);

            $key = "{$row->program_id}|{$row->hte_id}|{$ay}|{$semester}";
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            DB::table('hte_listings')->insert([
                'campus_id' => $row->campus_id,
                'program_id' => $row->program_id,
                'hte_id' => $row->hte_id,
                'academic_year' => $ay,
                'semester' => $semester,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hte_listings');
    }
};
