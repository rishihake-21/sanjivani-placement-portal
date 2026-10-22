<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 20)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        $now = now();
        DB::table('programs')->insertOrIgnore([
            ['name' => 'B.Tech', 'code' => 'BTECH', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'BBA', 'code' => 'BBA', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'MBA', 'code' => 'MBA', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'M.Tech', 'code' => 'MTECH', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Integrated B.Tech', 'code' => 'IBTECH', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Integrated M.Tech', 'code' => 'IMTECH', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $btechId = DB::table('programs')->where('code', 'BTECH')->value('id');

        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->constrained('programs')->restrictOnDelete();
            $table->index('program_id');
        });

        // Preserve the existing department records and put them under the existing B.Tech programme.
        DB::table('departments')->update(['program_id' => $btechId]);
        DB::statement('ALTER TABLE departments ALTER COLUMN program_id SET NOT NULL');

        // Department names/codes are scoped to a programme; CSE can exist in B.Tech and
        // Integrated B.Tech as separate academic departments.
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_name_unique');
            $table->dropUnique('departments_code_unique');
            $table->unique(['program_id', 'name']);
            $table->unique(['program_id', 'code']);
        });

        // AI/ML was previously represented as a child branch of CSE in the legacy schema.
        // Promote it to a real department and later remap those legacy roster records to it.
        $aiml = DB::table('departments')->where('code', 'AIML')->first();
        if ($aiml === null) {
            $aimlId = DB::table('departments')->insertGetId([
                'name' => 'Artificial Intelligence and Machine Learning',
                'code' => 'AIML',
                'is_active' => true,
                'program_id' => $btechId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $aimlId = (int) $aiml->id;
            DB::table('departments')->where('id', $aimlId)->update(['program_id' => $btechId, 'updated_at' => $now]);
        }

        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('code', 30);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->unique(['department_id', 'name']);
            $table->unique(['department_id', 'code']);
            $table->index('department_id');
        });

        Schema::table('student_master_list', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->constrained('divisions')->restrictOnDelete();
            $table->index('division_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->constrained('divisions')->restrictOnDelete();
            $table->index('division_id');
        });

        // branch_id stays temporarily as a nullable compatibility column for legacy clients.
        DB::statement('ALTER TABLE student_master_list ALTER COLUMN branch_id DROP NOT NULL');
        DB::statement('ALTER TABLE students ALTER COLUMN branch_id DROP NOT NULL');

        // Keep legacy branch_id intact for backwards compatibility, but promote legacy CSE-AIML
        // roster rows to the dedicated AI/ML department so coordinator scope matches the domain.
        $legacyAimlBranchId = DB::table('branches')->where('code', 'CSE-AIML')->value('id');
        $cseId = DB::table('departments')->where('code', 'CSE')->value('id');
        if ($legacyAimlBranchId !== null) {
            // Move the legacy branch first so any database guard that checks branch/department
            // consistency accepts the subsequent roster and student reassignment.
            DB::table('branches')->where('id', $legacyAimlBranchId)->update([
                'department_id' => $aimlId,
                'updated_at' => $now,
            ]);

            if ($cseId !== null) {
                DB::table('student_master_list')
                    ->where('branch_id', $legacyAimlBranchId)
                    ->where('department_id', $cseId)
                    ->update(['department_id' => $aimlId, 'updated_at' => $now]);
                DB::table('students')
                    ->where('branch_id', $legacyAimlBranchId)
                    ->where('department_id', $cseId)
                    ->update(['department_id' => $aimlId, 'updated_at' => $now]);
            }
        }

        // We cannot infer class sections (SY CSE-A, SY CSE-B, DSY, etc.) from the
        // old Branch rows. Create an explicit unassigned division per department.
        // Admin/TPO can replace this assignment from the master-list import without losing IDs.
        $defaultDivisionByDepartment = [];
        foreach (DB::table('departments')->orderBy('id')->get(['id', 'code']) as $department) {
            $code = substr(strtoupper((string) $department->code), 0, 18) . '-UNASSIGNED';
            $existing = DB::table('divisions')->where('department_id', $department->id)->where('code', $code)->first();
            if ($existing) {
                $divisionId = (int) $existing->id;
            } else {
                $divisionId = DB::table('divisions')->insertGetId([
                    'department_id' => $department->id,
                    'name' => 'Unassigned (migrated records)',
                    'code' => $code,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $defaultDivisionByDepartment[(int) $department->id] = $divisionId;
        }

        foreach ($defaultDivisionByDepartment as $departmentId => $divisionId) {
            DB::table('student_master_list')->where('department_id', $departmentId)
                ->whereNull('division_id')->update(['division_id' => $divisionId]);
            DB::table('students')->where('department_id', $departmentId)
                ->whereNull('division_id')->update(['division_id' => $divisionId]);
        }

        // Drives now target departments. Keep drive_branches unchanged for old clients/data,
        // and migrate each existing target into the new department-level pivot.
        Schema::create('drive_departments', function (Blueprint $table) {
            $table->foreignId('drive_id')->constrained('placement_drives')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->primary(['drive_id', 'department_id']);
            $table->index('department_id');
        });

        $branches = DB::table('branches')->get(['id', 'code', 'department_id'])->keyBy('id');
        foreach (DB::table('drive_branches')->get(['drive_id', 'branch_id']) as $target) {
            $legacyBranch = $branches->get($target->branch_id);
            if ($legacyBranch === null) {
                continue;
            }
            $departmentId = $legacyBranch->code === 'CSE-AIML'
                ? $aimlId
                : (int) $legacyBranch->department_id;

            DB::table('drive_departments')->insertOrIgnore([
                'drive_id' => $target->drive_id,
                'department_id' => $departmentId,
            ]);
        }
    }

    public function down(): void
    {
        // This migration changes existing academic assignments (notably CSE-AIML) and
        // introduces nullable legacy branch IDs. The original values are not stored, so
        // an automatic rollback cannot restore the previous state without guessing.
        // Fail before making any schema or data changes rather than assigning students
        // to an arbitrary branch or silently corrupting academic history.
        throw new \\RuntimeException(
            'This academic hierarchy migration is not automatically reversible. Restore a verified database backup or create a reviewed forward migration.'
        );
    }
};
