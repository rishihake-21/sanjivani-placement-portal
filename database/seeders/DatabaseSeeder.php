<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local/demo seed entry point. For a clean test database, run migrate:fresh --seed.
 * Set TPMS_SEED_PASSWORD to control credentials for every seeded account.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('TPMS_SEED_PASSWORD')
            ?: throw new \RuntimeException('Set TPMS_SEED_PASSWORD before seeding.');

        $this->call(AcademicStructureSeeder::class);

        User::query()->firstOrCreate(['email' => 'admin@tpms.local'], [
            'name' => 'System Admin',
            'password' => $password,
            'role' => Role::SYSTEM_ADMIN,
            'is_active' => true,
        ]);

        User::query()->firstOrCreate(['email' => 'tpo@tpms.local'], [
            'name' => 'Training & Placement Officer',
            'password' => $password,
            'role' => Role::TPO,
            'is_active' => true,
        ]);

        // Exactly one seeded T&P coordinator account per department, not per division.
        // Programme is included in the email because codes such as CSE can repeat
        // across B.Tech, M.Tech, and integrated programmes.
        Department::query()->with('program')->orderBy('program_id')->orderBy('code')->get()
            ->each(function (Department $department) use ($password): void {
                $programCode = $department->program?->code ?? 'PROGRAM';
                $emailKey = strtolower($programCode . '-' . $department->code);

                User::query()->firstOrCreate(['email' => $emailKey . '.coordinator@tpms.local'], [
                    'name' => $department->name . ' (' . $programCode . ') Coordinator',
                    'password' => $password,
                    'role' => Role::TP_COORDINATOR,
                    'department_id' => $department->id,
                    'is_active' => true,
                ]);
            });

        // A student roster across multiple divisions plus complete student profiles,
        // company data, and published drives targeting departments.
        $this->call(StudentSeeder::class);
        $this->call(CoordinatorSeeder::class);
        $this->call(CompleteStudentAndCompanySeeder::class);

        $this->command?->info('TPMS hierarchy and demo accounts seeded.');
    }
}
