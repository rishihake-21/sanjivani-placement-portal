<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Reference data + one account per staff role.
 * Staff passwords come from the environment so nothing predictable is committed:
 *   TPMS_SEED_PASSWORD=...  php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('TPMS_SEED_PASSWORD') ?: throw new \RuntimeException('Set TPMS_SEED_PASSWORD before seeding.');

        $programs = [];
        foreach ([
            ['BTECH', 'B.Tech'],
            ['BBA', 'BBA'],
            ['MBA', 'MBA'],
            ['MTECH', 'M.Tech'],
            ['IBTECH', 'Integrated B.Tech'],
            ['IMTECH', 'Integrated M.Tech'],
        ] as [$code, $name]) {
            $programs[$code] = Program::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }

        $structure = [
            ['CSE', 'Computer Science and Engineering', [['CSE', 'Computer Engineering']]],
            ['IT', 'Information Technology', [['IT', 'Information Technology']]],
            ['ECE', 'Electronics and Telecommunication', [['ECE', 'Electronics and Telecommunication']]],
            ['ME', 'Mechanical Engineering', [['ME', 'Mechanical Engineering']]],
            ['CE', 'Civil Engineering', [['CE', 'Civil Engineering']]],
            ['AIDS', 'Artificial Intelligence and Data Science', [['AIDS', 'Artificial Intelligence and Data Science']]],
            ['CYBERSEC', 'Cyber Security', [['CYBERSEC', 'Cyber Security']]],
        ];

        $departments = [];
        foreach ($structure as [$code, $name, $branches]) {
            $departments[$code] = Department::firstOrCreate(
                ['program_id' => $programs['BTECH']->id, 'code' => $code],
                ['name' => $name, 'program_id' => $programs['BTECH']->id, 'is_active' => true]
            );
            foreach ($branches as [$branchCode, $branchName]) {
                Branch::firstOrCreate(
                    ['code' => $branchCode],
                    ['department_id' => $departments[$code]->id, 'name' => $branchName]
                );
            }
        }

        // The legacy seed grouped CSE (AI & ML) under CSE. Keep its old branch row for
        // compatibility, but create AI/ML as its own B.Tech department in the new hierarchy.
        $departments['AIML'] = Department::firstOrCreate(
            ['program_id' => $programs['BTECH']->id, 'code' => 'AIML'],
            ['name' => 'Artificial Intelligence and Machine Learning', 'program_id' => $programs['BTECH']->id, 'is_active' => true]
        );

        // Compatibility alias for older branch_id/branch_ids clients belongs to AI/ML,
        // not to the CSE department. The canonical admin hierarchy uses the AIML department.
        Branch::updateOrCreate(
            ['code' => 'CSE-AIML'],
            ['department_id' => $departments['AIML']->id, 'name' => 'CSE (AI & ML)', 'is_active' => true]
        );

        // Seed a neutral placeholder for legacy students. The real section/division cannot be
        // inferred from their old branch row; the master-list import can assign SY/DSY divisions.
        foreach (Department::query()->with('program')->get() as $department) {
            Division::firstOrCreate(
                ['department_id' => $department->id, 'code' => substr(strtoupper($department->code), 0, 18) . '-UNASSIGNED'],
                ['department_id' => $department->id, 'name' => 'Unassigned (migrated records)', 'is_active' => true]
            );
        }

        User::firstOrCreate(['email' => 'admin@tpms.local'], [
            'name' => 'System Admin', 'password' => $password, 'role' => Role::SYSTEM_ADMIN, 'is_active' => true,
        ]);
        User::firstOrCreate(['email' => 'tpo@tpms.local'], [
            'name' => 'Training & Placement Officer', 'password' => $password, 'role' => Role::TPO, 'is_active' => true,
        ]);
        foreach ($departments as $code => $department) {
            User::firstOrCreate(['email' => strtolower($code) . '.coordinator@tpms.local'], [
                'name' => $code . ' T&P Coordinator', 'password' => $password,
                'role' => Role::TP_COORDINATOR, 'department_id' => $department->id, 'is_active' => true,
            ]);
        }

        // Seed students
        $this->call(StudentSeeder::class);
        $this->call(CoordinatorSeeder::class);

        // Seed complete students, companies, and drives
        $this->call(CompleteStudentAndCompanySeeder::class);
    }
}