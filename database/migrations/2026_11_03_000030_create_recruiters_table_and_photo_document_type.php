<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recruiter module.
 *
 *  - recruiters: a recruiter is a login (users.role = 'recruiter') attached to ONE company. What they
 *    can see is derived from that company at query time (applications to its drives), never copied.
 *  - documents: a student photo is a new standalone document type, reviewed by the coordinator like a
 *    resume, so a recruiter only ever sees a photo somebody at the college has approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruiters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('designation', 80)->nullable();
            $table->string('phone', 15)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('active_from');
            $table->date('active_until')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['company_id', 'is_active']);
        });

        DB::statement('ALTER TABLE recruiters ADD CONSTRAINT recruiters_period_check
            CHECK (active_until IS NULL OR active_until >= active_from)');
        DB::statement('ALTER TABLE recruiters ADD CONSTRAINT recruiters_inactive_has_end_check
            CHECK (is_active OR active_until IS NOT NULL)');

        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_type_check');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_type_check CHECK (document_type IN (
            'RESUME','MARKSHEET_10TH','MARKSHEET_12TH','MARKSHEET_DIPLOMA','MARKSHEET_SEMESTER',
            'CERT_INTERNSHIP','CERT_EXPERIENCE','CERT_OTHER','PHOTO'))");
    }

    public function down(): void
    {
        // Photos cannot exist under the old constraint, so they go first.
        DB::statement("UPDATE documents SET replaces_id = NULL WHERE document_type = 'PHOTO'");
        DB::statement("DELETE FROM documents WHERE document_type = 'PHOTO'");
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_type_check');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_type_check CHECK (document_type IN (
            'RESUME','MARKSHEET_10TH','MARKSHEET_12TH','MARKSHEET_DIPLOMA','MARKSHEET_SEMESTER',
            'CERT_INTERNSHIP','CERT_EXPERIENCE','CERT_OTHER'))");

        Schema::dropIfExists('recruiters');
    }
};
