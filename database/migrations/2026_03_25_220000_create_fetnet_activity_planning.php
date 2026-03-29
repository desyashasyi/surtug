<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create fetnet_activity_planning
        Schema::create('fetnet_activity_planning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')
                  ->constrained('fetnet_subject')
                  ->cascadeOnDelete();
            $table->foreignId('program_id')
                  ->constrained('institution_program')
                  ->cascadeOnDelete();
            $table->foreignId('semester_id')
                  ->constrained('fetnet_semester')
                  ->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['subject_id', 'program_id', 'semester_id']);
        });

        // 2. Add planning_id to fetnet_activity (after program_id)
        Schema::table('fetnet_activity', function (Blueprint $table) {
            $table->unsignedBigInteger('planning_id')
                  ->nullable()
                  ->after('program_id');
            $table->foreign('planning_id')
                  ->references('id')
                  ->on('fetnet_activity_planning')
                  ->cascadeOnDelete();
        });

        // 3. Migrate existing data: create planning records for activities that have subject_id AND semester_id
        DB::statement('
            INSERT INTO fetnet_activity_planning (subject_id, program_id, semester_id, created_at, updated_at)
            SELECT DISTINCT subject_id, program_id, semester_id, NOW(), NOW()
            FROM fetnet_activity
            WHERE subject_id IS NOT NULL AND semester_id IS NOT NULL
            ON CONFLICT (subject_id, program_id, semester_id) DO NOTHING
        ');

        // Update planning_id on activities via join
        DB::statement('
            UPDATE fetnet_activity a
            SET planning_id = p.id
            FROM fetnet_activity_planning p
            WHERE a.subject_id = p.subject_id
              AND a.program_id = p.program_id
              AND a.semester_id = p.semester_id
        ');

        // 4. Drop subject_id and semester_id from fetnet_activity
        Schema::table('fetnet_activity', function (Blueprint $table) {
            // Drop foreign keys first, then columns
            try {
                $table->dropConstrainedForeignId('subject_id');
            } catch (\Exception $e) {
                $table->dropForeign(['subject_id']);
                $table->dropColumn('subject_id');
            }

            try {
                $table->dropConstrainedForeignId('semester_id');
            } catch (\Exception $e) {
                $table->dropForeign(['semester_id']);
                $table->dropColumn('semester_id');
            }
        });
    }

    public function down(): void
    {
        // Add back subject_id and semester_id (nullable)
        Schema::table('fetnet_activity', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->after('program_id');
            $table->unsignedBigInteger('semester_id')->nullable()->after('subject_id');
        });

        // Restore subject_id and semester_id from planning records
        DB::statement('
            UPDATE fetnet_activity a
            SET subject_id = p.subject_id,
                semester_id = p.semester_id
            FROM fetnet_activity_planning p
            WHERE a.planning_id = p.id
        ');

        // Drop planning_id
        Schema::table('fetnet_activity', function (Blueprint $table) {
            $table->dropForeign(['planning_id']);
            $table->dropColumn('planning_id');
        });

        // Drop the planning table
        Schema::dropIfExists('fetnet_activity_planning');
    }
};
