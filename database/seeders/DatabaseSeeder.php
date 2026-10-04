<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Department;
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

        $structure = [
            ['CSE', 'Computer Science and Engineering', [['CSE', 'Computer Engineering'], ['CSE-AIML', 'CSE (AI & ML)']]],
            ['IT', 'Information Technology', [['IT', 'Information Technology']]],
            ['ECE', 'Electronics and Telecommunication', [['ECE', 'Electronics and Telecommunication']]],
            ['ME', 'Mechanical Engineering', [['ME', 'Mechanical Engineering']]],
            ['CE', 'Civil Engineering', [['CE', 'Civil Engineering']]],
        ];

        $departments = [];
        foreach ($structure as [$code, $name, $branches]) {
            $departments[$code] = Department::firstOrCreate(['code' => $code], ['name' => $name]);
            foreach ($branches as [$branchCode, $branchName]) {
                Branch::firstOrCreate(
                    ['code' => $branchCode],
                    ['department_id' => $departments[$code]->id, 'name' => $branchName]
                );
            }
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
    }
}