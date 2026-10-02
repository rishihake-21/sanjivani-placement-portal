<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Level-based academic rows (10th / 12th / Diploma / Degree semester N).
 *
 * Revision model
 * --------------
 *  - first entry            : one row, DRAFT -> PENDING -> VERIFIED (or REJECTED -> fix -> PENDING)
 *  - editing a VERIFIED row : a NEW row (version+1, supersedes_id = old row) starts as DRAFT.
 *                             The old VERIFIED row stays in force until the new row is approved,
 *                             at which point the old row becomes SUPERSEDED.
 * Eligibility reads ONLY status = 'VERIFIED' rows, so unverified edits never leak into it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();

            $table->string('level', 15);
            $table->unsignedSmallInteger('semester')->nullable(); // 1..8, DEGREE_SEM only

            $table->string('institution_name', 150)->nullable();
            $table->string('board_or_university', 150)->nullable();
            $table->unsignedSmallInteger('passing_year')->nullable();

            $table->decimal('percentage', 5, 2)->nullable();       // 10th / 12th / diploma
            $table->decimal('obtained_marks', 8, 2)->nullable();
            $table->decimal('total_marks', 8, 2)->nullable();
            $table->decimal('sgpa', 4, 2)->nullable();             // degree semesters
            $table->decimal('cgpa', 4, 2)->nullable();             // as printed on the marksheet, if any
            $table->unsignedSmallInteger('backlogs_in_term')->default(0);
            $table->unsignedSmallInteger('active_backlogs_after_term')->default(0);

            $table->unsignedBigInteger('document_id')->nullable();

            // Review lifecycle
            $table->string('status', 15)->default('DRAFT');
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('academic_records')->restrictOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('rejection_reason_code', 30)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('locked_at')->nullable(); // set on verification of past terms

            $table->timestampsTz();

            $table->index(['student_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        // A record can only reference a document that belongs to the SAME student.
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT academic_records_document_fk
            FOREIGN KEY (document_id, student_id) REFERENCES documents (id, student_id)');

        DB::statement("ALTER TABLE academic_records ADD CONSTRAINT academic_level_check
            CHECK (level IN ('TENTH','TWELFTH','DIPLOMA','DEGREE_SEM'))");
        DB::statement("ALTER TABLE academic_records ADD CONSTRAINT academic_semester_check
            CHECK ((level = 'DEGREE_SEM' AND semester BETWEEN 1 AND 8) OR (level <> 'DEGREE_SEM' AND semester IS NULL))");
        DB::statement("ALTER TABLE academic_records ADD CONSTRAINT academic_status_check
            CHECK (status IN ('DRAFT','PENDING','VERIFIED','REJECTED','SUPERSEDED'))");
        DB::statement("ALTER TABLE academic_records ADD CONSTRAINT academic_core_value_check
            CHECK ((level = 'DEGREE_SEM' AND sgpa IS NOT NULL) OR (level <> 'DEGREE_SEM' AND percentage IS NOT NULL))");
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT academic_ranges_check CHECK (
            (percentage IS NULL OR percentage BETWEEN 0 AND 100)
            AND (sgpa IS NULL OR sgpa BETWEEN 0 AND 10)
            AND (cgpa IS NULL OR cgpa BETWEEN 0 AND 10)
            AND (obtained_marks IS NULL OR total_marks IS NOT NULL)
            AND (obtained_marks IS NULL OR obtained_marks <= total_marks))');
        DB::statement("ALTER TABLE academic_records ADD CONSTRAINT academic_verified_check
            CHECK (status <> 'VERIFIED' OR (reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND document_id IS NOT NULL))");
        DB::statement("ALTER TABLE academic_records ADD CONSTRAINT academic_pending_check
            CHECK (status NOT IN ('PENDING','VERIFIED') OR document_id IS NOT NULL)");
        DB::statement("ALTER TABLE academic_records ADD CONSTRAINT academic_rejected_check
            CHECK (status <> 'REJECTED' OR rejection_reason_code IS NOT NULL)");

        // One VERIFIED row per (student, level, semester) ...
        DB::statement("CREATE UNIQUE INDEX academic_one_verified_per_key
            ON academic_records (student_id, level, (COALESCE(semester, 0))) WHERE status = 'VERIFIED'");
        // ... and at most one open (draft / under review / rejected) row per key.
        DB::statement("CREATE UNIQUE INDEX academic_one_open_per_key
            ON academic_records (student_id, level, (COALESCE(semester, 0)))
            WHERE status IN ('DRAFT','PENDING','REJECTED')");
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_records');
    }
};
