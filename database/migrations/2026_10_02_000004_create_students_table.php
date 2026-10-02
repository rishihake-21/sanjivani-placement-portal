<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sections A (identity), B (contact), D (skills & links) and F (preferences) live here.
 * Academic data, experience and documents are in their own tables.
 * Section completion status is COMPUTED (ProfileCompletenessService), never stored,
 * so it can't go stale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('master_list_id')->unique()->constrained('student_master_list')->restrictOnDelete();

            // Section A — identity & admission (copied from master list; read-only for the student)
            $table->string('university_id', 30)->unique();
            $table->string('full_name', 150);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('admission_type', 10);
            $table->unsignedSmallInteger('admission_year');
            $table->unsignedSmallInteger('graduation_year');
            $table->unsignedSmallInteger('current_semester');
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->timestampTz('identity_confirmed_at')->nullable();

            // Section B — contact
            $table->string('personal_email', 190)->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('current_city', 100)->nullable();
            $table->string('permanent_city', 100)->nullable();

            // Section D — skills & links (self-declared)
            $table->jsonb('skills')->default('[]');
            $table->jsonb('languages')->default('[]');
            $table->string('github_url', 255)->nullable();
            $table->string('linkedin_url', 255)->nullable();
            $table->string('portfolio_url', 255)->nullable();

            // Section F — placement preferences (self-declared)
            $table->jsonb('preferred_roles')->default('[]');
            $table->jsonb('preferred_locations')->default('[]');
            $table->boolean('opted_out_of_placement')->default(false);
            $table->text('opt_out_reason')->nullable();

            // Lifecycle
            $table->timestampTz('profile_locked_at')->nullable(); // set after graduation/batch end

            $table->timestampsTz();

            $table->index(['department_id', 'branch_id']);
            $table->index('graduation_year');
            $table->index('current_semester');
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_admission_type_check
            CHECK (admission_type IN ('REGULAR','LATERAL'))");
        DB::statement('ALTER TABLE students ADD CONSTRAINT students_semester_check
            CHECK (current_semester BETWEEN 1 AND 8)');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_lateral_semester_check
            CHECK (admission_type <> 'LATERAL' OR current_semester >= 3)");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_opt_out_reason_check
            CHECK (NOT opted_out_of_placement OR opt_out_reason IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
