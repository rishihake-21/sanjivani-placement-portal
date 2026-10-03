<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drive_id')->constrained('placement_drives')->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('stage', 20)->default('APPLIED');
            $table->timestampTz('applied_at');
            $table->timestampTz('stage_updated_at');
            $table->jsonb('data_snapshot');            // verified academics at apply time
            $table->timestampsTz();

            $table->unique(['drive_id', 'student_id']); // one application per student per drive
            $table->index(['drive_id', 'stage']);
            $table->index(['student_id', 'stage']);
        });
        DB::statement("ALTER TABLE applications ADD CONSTRAINT applications_stage_check CHECK (stage IN (
            'APPLIED','SHORTLISTED','APTITUDE','TECHNICAL','HR','SELECTED','REJECTED','OFFER_RECEIVED','PLACED','WITHDRAWN'))");

        Schema::create('application_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('from_stage', 20)->nullable();
            $table->string('to_stage', 20);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['application_id', 'created_at']);
        });

        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('role_title', 150);
            $table->decimal('ctc_lpa', 6, 2)->nullable();
            $table->string('location', 150)->nullable();
            $table->date('offer_date')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('status', 10)->default('OFFERED');
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();

            $table->index(['student_id', 'status']);
            $table->index(['company_id', 'status']);
        });
        DB::statement("ALTER TABLE placements ADD CONSTRAINT placements_status_check
            CHECK (status IN ('OFFERED','PLACED','DECLINED'))");
        // The official PLACED outcome is always TPO-verified (students never set it).
        DB::statement("ALTER TABLE placements ADD CONSTRAINT placements_verified_check
            CHECK (status <> 'PLACED' OR (verified_by IS NOT NULL AND verified_at IS NOT NULL))");
        DB::statement('ALTER TABLE placements ADD CONSTRAINT placements_ctc_check CHECK (ctc_lpa IS NULL OR ctc_lpa >= 0)');
        DB::statement('CREATE UNIQUE INDEX placements_one_per_application
            ON placements (application_id) WHERE application_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('placements');
        Schema::dropIfExists('application_status_history');
        Schema::dropIfExists('applications');
    }
};
