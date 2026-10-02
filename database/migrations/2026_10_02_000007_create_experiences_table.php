<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Internships & experience (Section E).
 *  SELF_DECLARED : visible to T&P and on reports but labelled unverified (no certificate).
 *  DRAFT         : being prepared / a revision of a VERIFIED entry (needs fresh proof).
 *  PENDING       : submitted with certificate, awaiting coordinator.
 * Same revision model as academic_records (supersedes_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();

            $table->string('type', 20);
            $table->string('organization', 150);
            $table->string('role_title', 150);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_ongoing')->default(false);
            $table->text('description')->nullable();

            $table->unsignedBigInteger('certificate_document_id')->nullable();

            $table->string('status', 15)->default('SELF_DECLARED');
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('experiences')->restrictOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('rejection_reason_code', 30)->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestampsTz();

            $table->index(['student_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        DB::statement('ALTER TABLE experiences ADD CONSTRAINT experiences_certificate_fk
            FOREIGN KEY (certificate_document_id, student_id) REFERENCES documents (id, student_id)');
        DB::statement("ALTER TABLE experiences ADD CONSTRAINT experiences_type_check
            CHECK (type IN ('INTERNSHIP','JOB','PROJECT_WORK','TRAINING'))");
        DB::statement("ALTER TABLE experiences ADD CONSTRAINT experiences_status_check
            CHECK (status IN ('SELF_DECLARED','DRAFT','PENDING','VERIFIED','REJECTED','SUPERSEDED'))");
        DB::statement('ALTER TABLE experiences ADD CONSTRAINT experiences_dates_check CHECK (
            (is_ongoing AND end_date IS NULL) OR (NOT is_ongoing AND end_date IS NOT NULL AND end_date >= start_date))');
        DB::statement("ALTER TABLE experiences ADD CONSTRAINT experiences_pending_check
            CHECK (status NOT IN ('PENDING','VERIFIED') OR certificate_document_id IS NOT NULL)");
        DB::statement("ALTER TABLE experiences ADD CONSTRAINT experiences_verified_check
            CHECK (status <> 'VERIFIED' OR (reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL))");
        DB::statement("ALTER TABLE experiences ADD CONSTRAINT experiences_rejected_check
            CHECK (status <> 'REJECTED' OR rejection_reason_code IS NOT NULL)");

        // Only one open revision may point at a given verified entry.
        DB::statement("CREATE UNIQUE INDEX experiences_single_open_revision
            ON experiences (supersedes_id)
            WHERE supersedes_id IS NOT NULL AND status IN ('DRAFT','PENDING','REJECTED')");
    }

    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
