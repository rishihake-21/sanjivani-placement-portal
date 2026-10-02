<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends Laravel's default users table.
 * department_id is only set for T&P Coordinators (and the future HOD role).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('student')->after('password');
            $table->foreignId('department_id')->nullable()->after('role')
                ->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('department_id');

            $table->index('role');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check
            CHECK (role IN ('student','tp_coordinator','tpo','system_admin','hod','recruiter'))");

        // A coordinator or HOD without a department would see nothing / everything by mistake.
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_dept_required_check
            CHECK (role NOT IN ('tp_coordinator','hod') OR department_id IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_dept_required_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
