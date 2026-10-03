<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared placement schema. The TPO module WRITES these tables; the coordinator module only READS them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('website', 255)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX companies_name_unique ON companies (lower(name))');

        Schema::create('placement_drives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);                       // job / position
            $table->text('description')->nullable();
            $table->string('employment_type', 20)->default('FULL_TIME');
            $table->decimal('ctc_lpa', 6, 2)->nullable();
            $table->decimal('ctc_max_lpa', 6, 2)->nullable();
            $table->string('location', 150)->nullable();
            $table->date('drive_date')->nullable();
            $table->timestampTz('application_deadline')->nullable();
            $table->unsignedSmallInteger('graduation_year');
            $table->text('additional_requirements')->nullable(); // free text, display-only
            $table->string('status', 15)->default('DRAFT');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['status', 'application_deadline']);
            $table->index(['company_id', 'graduation_year']);
        });
        DB::statement("ALTER TABLE placement_drives ADD CONSTRAINT drives_status_check
            CHECK (status IN ('DRAFT','PUBLISHED','CLOSED','CANCELLED'))");
        DB::statement("ALTER TABLE placement_drives ADD CONSTRAINT drives_type_check
            CHECK (employment_type IN ('FULL_TIME','INTERNSHIP','INTERNSHIP_PPO'))");
        DB::statement('ALTER TABLE placement_drives ADD CONSTRAINT drives_ctc_check
            CHECK (ctc_lpa IS NULL OR ctc_lpa >= 0) ');
        DB::statement('ALTER TABLE placement_drives ADD CONSTRAINT drives_ctc_range_check
            CHECK (ctc_max_lpa IS NULL OR (ctc_lpa IS NOT NULL AND ctc_max_lpa >= ctc_lpa))');
        DB::statement("ALTER TABLE placement_drives ADD CONSTRAINT drives_published_check
            CHECK (status NOT IN ('PUBLISHED','CLOSED') OR (published_at IS NOT NULL AND application_deadline IS NOT NULL))");

        Schema::create('drive_eligibility', function (Blueprint $table) {
            $table->foreignId('drive_id')->primary()->constrained('placement_drives')->restrictOnDelete();
            $table->decimal('min_cgpa', 4, 2)->nullable();
            $table->decimal('min_tenth_percentage', 5, 2)->nullable();
            $table->decimal('min_twelfth_percentage', 5, 2)->nullable();
            $table->decimal('min_diploma_percentage', 5, 2)->nullable();
            $table->unsignedSmallInteger('max_active_backlogs')->nullable();
            $table->unsignedSmallInteger('max_total_backlogs')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE drive_eligibility ADD CONSTRAINT eligibility_ranges_check CHECK (
            (min_cgpa IS NULL OR min_cgpa BETWEEN 0 AND 10)
            AND (min_tenth_percentage IS NULL OR min_tenth_percentage BETWEEN 0 AND 100)
            AND (min_twelfth_percentage IS NULL OR min_twelfth_percentage BETWEEN 0 AND 100)
            AND (min_diploma_percentage IS NULL OR min_diploma_percentage BETWEEN 0 AND 100))');

        Schema::create('drive_branches', function (Blueprint $table) {
            $table->foreignId('drive_id')->constrained('placement_drives')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->primary(['drive_id', 'branch_id']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_branches');
        Schema::dropIfExists('drive_eligibility');
        Schema::dropIfExists('placement_drives');
        Schema::dropIfExists('companies');
    }
};
