<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Metadata only. The bytes live in the private file-storage disk (see config/tpms.php).
 * A re-upload creates a NEW row (version + 1, replaces_id set); the old row becomes SUPERSEDED.
 * Rows are never deleted, so the audit trail stays intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 30);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('replaces_id')->nullable()->constrained('documents')->restrictOnDelete();

            $table->string('disk', 30);
            $table->string('path', 512)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);

            $table->string('status', 15)->default('PENDING');
            $table->boolean('is_primary')->default(false); // only meaningful for RESUME

            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('rejection_reason_code', 30)->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestampsTz();

            $table->index(['student_id', 'document_type', 'status']);
            $table->index(['status', 'created_at']);
            // Needed so other tables can use a composite FK (id, student_id) that
            // guarantees a record can only point at a document of the SAME student.
            $table->unique(['id', 'student_id']);
        });

        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_type_check CHECK (document_type IN (
            'RESUME','MARKSHEET_10TH','MARKSHEET_12TH','MARKSHEET_DIPLOMA','MARKSHEET_SEMESTER',
            'CERT_INTERNSHIP','CERT_EXPERIENCE','CERT_OTHER'))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check
            CHECK (status IN ('PENDING','APPROVED','REJECTED','SUPERSEDED'))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_primary_resume_check
            CHECK (NOT is_primary OR document_type = 'RESUME')");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_reviewed_check
            CHECK (status NOT IN ('APPROVED','REJECTED') OR (reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_rejection_check
            CHECK (status <> 'REJECTED' OR rejection_reason_code IS NOT NULL)");
        DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_size_check
            CHECK (size_bytes > 0)');

        // At most one live primary resume per student.
        DB::statement("CREATE UNIQUE INDEX documents_one_primary_resume
            ON documents (student_id) WHERE is_primary AND status <> 'SUPERSEDED'");
        // A document can be replaced only once (no forked version chains).
        DB::statement('CREATE UNIQUE INDEX documents_single_replacement
            ON documents (replaces_id) WHERE replaces_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
