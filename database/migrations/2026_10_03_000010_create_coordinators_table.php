<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinator profile + assignment. users.role = 'tp_coordinator' stays the login identity;
 * this table holds the staff details and WHICH department they coordinate, with history.
 *
 * Integrity:
 *  - at most ONE active coordinator per department (partial unique index)
 *  - coordinators.department_id must equal users.department_id (composite FK), so the
 *    department used for access scoping can never drift from the assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_id_department_unique UNIQUE (id, department_id)');

        Schema::create('coordinators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('department_id');
            $table->string('employee_id', 30)->nullable()->unique();
            $table->string('phone', 15)->nullable();
            $table->string('designation', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('active_from');
            $table->date('active_until')->nullable();
            $table->timestampsTz();

            $table->index('department_id');
        });

        DB::statement('ALTER TABLE coordinators ADD CONSTRAINT coordinators_user_dept_fk
            FOREIGN KEY (user_id, department_id) REFERENCES users (id, department_id)');
        DB::statement('ALTER TABLE coordinators ADD CONSTRAINT coordinators_department_fk
            FOREIGN KEY (department_id) REFERENCES departments (id)');
        DB::statement('ALTER TABLE coordinators ADD CONSTRAINT coordinators_period_check
            CHECK (active_until IS NULL OR active_until >= active_from)');
        DB::statement('ALTER TABLE coordinators ADD CONSTRAINT coordinators_inactive_has_end_check
            CHECK (is_active OR active_until IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX coordinators_one_active_per_department
            ON coordinators (department_id) WHERE is_active');

        // Coordinators created by the student-module seeder/admin before this table existed.
        DB::statement("INSERT INTO coordinators (user_id, department_id, is_active, active_from, active_until, created_at, updated_at)
            SELECT id, department_id, is_active, CURRENT_DATE, CASE WHEN is_active THEN NULL ELSE CURRENT_DATE END, now(), now()
            FROM users WHERE role = 'tp_coordinator'");
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinators');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_id_department_unique');
    }
};
