<?php

namespace Database\Seeders;

use App\Enums\AdmissionType;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Student;
use App\Models\StudentMasterList;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Seeds exactly 20 B.Tech demo students into Final Year CSE-A.
 * Final Year CSE-B is intentionally left available as a second section to test
 * department-level targeting across divisions. CompleteStudentAndCompanySeeder
 * adds three richer profiles used for application/eligibility testing.
 */
class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('TPMS_SEED_PASSWORD') ?: 'password';

        $program = \App\Models\Program::query()->where('code', 'BTECH')->firstOrFail();
        $department = Department::query()
            ->where('program_id', $program->id)
            ->where('code', 'CSE')
            ->firstOrFail();

        $divisionA = Division::query()
            ->where('department_id', $department->id)
            ->where('code', 'FINAL-CSE-A')
            ->where('is_active', true)
            ->firstOrFail();

        $branch = Branch::query()->where('code', 'CSE')->firstOrFail();

        $names = [
            'Aarav Sharma', 'Ananya Patil', 'Vivaan Kulkarni', 'Isha Deshmukh',
            'Aditya Joshi', 'Saanvi Pawar', 'Reyansh Shinde', 'Aditi Jadhav',
            'Arnav More', 'Kavya Chavan', 'Ishaan Bhosale', 'Myra Gaikwad',
            'Atharv Kadam', 'Diya Mahajan', 'Krish Nair', 'Sara Khan',
            'Vedant Sawant', 'Anika Gupta', 'Rudra Patil', 'Tanvi Kulkarni',
        ];

        Model::unguarded(function () use ($names, $password, $department, $divisionA, $branch): void {
            foreach ($names as $index => $name) {
                $number = $index + 1;
                $suffix = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                $universityId = 'CSE-FY-A-' . $suffix;
                $email = 'cse.finala.' . $suffix . '@sanjivani.edu.in';

                $user = User::query()->firstOrCreate(['email' => $email], [
                    'name' => $name,
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $masterList = StudentMasterList::query()->firstOrCreate(
                    ['university_id' => $universityId],
                    [
                        'full_name' => $name,
                        'institutional_email' => $email,
                        'department_id' => $department->id,
                        'branch_id' => $branch->id,
                        'division_id' => $divisionA->id,
                        'admission_type' => AdmissionType::REGULAR->value,
                        'admission_year' => 2022,
                        'graduation_year' => 2026,
                        'current_semester' => 8,
                        'claimed_at' => now(),
                        'import_batch' => 'demo-final-year-cse-a',
                    ]
                );

                Student::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'master_list_id' => $masterList->id,
                        'university_id' => $universityId,
                        'full_name' => $name,
                        'department_id' => $department->id,
                        'branch_id' => $branch->id,
                        'division_id' => $divisionA->id,
                        'admission_type' => AdmissionType::REGULAR->value,
                        'admission_year' => 2022,
                        'graduation_year' => 2026,
                        'current_semester' => 8,
                        'identity_confirmed_at' => now(),
                    ]
                );
            }
        });

        $this->command?->info('Seeded 20 demo students in B.Tech → CSE → Final Year CSE-A.');
    }
}
