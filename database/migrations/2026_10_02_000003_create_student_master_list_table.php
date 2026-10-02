<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source of truth imported by TPO / Admin (CSV).
 * A student can register only if their university ID exists here and is unclaimed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_master_list', function (Blueprint $table) {
            $table->id();
            $table->string('university_id', 30)->unique();
            $table->string('full_name', 150);
            $table->string('institutional_email', 190)->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('admission_type', 10);
            $table->unsignedSmallInteger('admission_year');
            $table->unsignedSmallInteger('graduation_year');
            $table->unsignedSmallInteger('current_semester');
            $table->timestampTz('claimed_at')->nullable();
            $table->string('import_batch', 60)->nullable();
            $table->timestampsTz();

            $table->index(['department_id', 'branch_id']);
        });

        DB::statement("ALTER TABLE student_master_list ADD CONSTRAINT master_admission_type_check
            CHECK (admission_type IN ('REGULAR','LATERAL'))");
        DB::statement('ALTER TABLE student_master_list ADD CONSTRAINT master_semester_check
            CHECK (current_semester BETWEEN 1 AND 8)');
        DB::statement("ALTER TABLE student_master_list ADD CONSTRAINT master_lateral_semester_check
            CHECK (admission_type <> 'LATERAL' OR current_semester >= 3)");
        DB::statement('ALTER TABLE student_master_list ADD CONSTRAINT master_grad_after_admission_check
            CHECK (graduation_year > admission_year)');

        // Case-insensitive uniqueness of the institutional email (when present).
        DB::statement('CREATE UNIQUE INDEX master_email_unique ON student_master_list (lower(institutional_email))
            WHERE institutional_email IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('student_master_list');
    }
};
