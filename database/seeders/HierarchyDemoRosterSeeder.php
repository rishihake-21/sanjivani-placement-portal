<?php

namespace Database\Seeders;

use App\Enums\AdmissionType;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Division;
use App\Models\Student;
use App\Models\StudentMasterList;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Small student roster for non-B.Tech programmes so the programme hierarchy,
 * department coordinator scopes, and division filters can be tested end-to-end.
 * Detailed B.Tech profiles and placement data are seeded by the existing seeders.
 */
class HierarchyDemoRosterSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('TPMS_SEED_PASSWORD')
            ?: throw new \RuntimeException('Set TPMS_SEED_PASSWORD before seeding.');

        $records = [
            [
                'university_id' => 'DEMO-BBA-0001',
                'name' => 'Sahana Kulkarni',
                'email' => 'sahana.kulkarni@bba.sanjivani.edu.in',
                'program_code' => 'BBA',
                'department_code' => 'BBA',
                'division_code' => 'SY-BBA-A',
                'admission_year' => 2025,
                'graduation_year' => 2028,
                'current_semester' => 3,
            ],
            [
                'university_id' => 'DEMO-MBA-0001',
                'name' => 'Aditya Deshmukh',
                'email' => 'aditya.deshmukh@mba.sanjivani.edu.in',
                'program_code' => 'MBA',
                'department_code' => 'MBA',
                'division_code' => 'FY-MBA-A',
                'admission_year' => 2026,
                'graduation_year' => 2028,
                'current_semester' => 1,
            ],
            [
                'university_id' => 'DEMO-MTECH-0001',
                'name' => 'Neha Joshi',
                'email' => 'neha.joshi@mtech.sanjivani.edu.in',
                'program_code' => 'MTECH',
                'department_code' => 'CSE',
                'division_code' => 'MTECH-CSE-A',
                'admission_year' => 2026,
                'graduation_year' => 2028,
                'current_semester' => 1,
            ],
            [
                'university_id' => 'DEMO-IBTECH-0001',
                'name' => 'Omkar Patil',
                'email' => 'omkar.patil@integrated-btech.sanjivani.edu.in',
                'program_code' => 'IBTECH',
                'department_code' => 'AIML',
                'division_code' => 'IBTECH-AIML-A',
                'admission_year' => 2024,
                'graduation_year' => 2029,
                'current_semester' => 5,
            ],
            [
                'university_id' => 'DEMO-IMTECH-0001',
                'name' => 'Mira Shah',
                'email' => 'mira.shah@integrated-mtech.sanjivani.edu.in',
                'program_code' => 'IMTECH',
                'department_code' => 'DS',
                'division_code' => 'IMTECH-DS-A',
                'admission_year' => 2024,
                'graduation_year' => 2030,
                'current_semester' => 5,
            ],
        ];

        foreach ($records as $record) {
            $department = Department::query()
                ->whereHas('program', fn ($query) => $query->where('code', $record['program_code']))
                ->where('code', $record['department_code'])
                ->firstOrFail();

            $division = Division::query()
                ->where('department_id', $department->id)
                ->where('code', $record['division_code'])
                ->where('is_active', true)
                ->firstOrFail();

            $user = User::query()->firstOrCreate(['email' => $record['email']], [
                'name' => $record['name'],
                'password' => $password,
                'role' => Role::STUDENT,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $masterList = StudentMasterList::query()->firstOrCreate(
                ['university_id' => $record['university_id']],
                [
                    'full_name' => $record['name'],
                    'institutional_email' => $record['email'],
                    'department_id' => $department->id,
                    'branch_id' => null,
                    'division_id' => $division->id,
                    'admission_type' => AdmissionType::REGULAR->value,
                    'admission_year' => $record['admission_year'],
                    'graduation_year' => $record['graduation_year'],
                    'current_semester' => $record['current_semester'],
                    'claimed_at' => now(),
                    'import_batch' => 'hierarchy-demo',
                ]
            );

            Student::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'master_list_id' => $masterList->id,
                    'university_id' => $record['university_id'],
                    'full_name' => $record['name'],
                    'department_id' => $department->id,
                    'branch_id' => null,
                    'division_id' => $division->id,
                    'admission_type' => AdmissionType::REGULAR->value,
                    'admission_year' => $record['admission_year'],
                    'graduation_year' => $record['graduation_year'],
                    'current_semester' => $record['current_semester'],
                    'identity_confirmed_at' => now(),
                ]
            );
        }

        $this->command?->info('Seeded demo students in BBA, MBA, M.Tech, Integrated B.Tech, and Integrated M.Tech.');
    }
}
