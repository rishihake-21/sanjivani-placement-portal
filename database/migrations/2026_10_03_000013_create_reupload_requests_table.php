<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Please re-upload / correct this" requests raised by a coordinator against something that was
 * already accepted (rejection of a pending item uses the normal reject flow instead).
 * Closed automatically (FULFILLED) when the student submits a new version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reupload_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 10)->default('OPEN');
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['student_id', 'status']);
            $table->index(['requested_by', 'status']);
        });

        DB::statement("ALTER TABLE reupload_requests ADD CONSTRAINT reupload_subject_check
            CHECK (subject_type IN ('ACADEMIC_RECORD','EXPERIENCE','DOCUMENT'))");
        DB::statement("ALTER TABLE reupload_requests ADD CONSTRAINT reupload_status_check
            CHECK (status IN ('OPEN','FULFILLED','CANCELLED'))");
        DB::statement("ALTER TABLE reupload_requests ADD CONSTRAINT reupload_closed_check
            CHECK (status = 'OPEN' OR closed_at IS NOT NULL)");
        DB::statement("CREATE UNIQUE INDEX reupload_one_open_per_subject
            ON reupload_requests (subject_type, subject_id) WHERE status = 'OPEN'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reupload_requests');
    }
};
