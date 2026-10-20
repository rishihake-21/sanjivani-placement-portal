<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a representative Programme -> Department -> Division catalogue.
 *
 * Legacy Branch rows are kept only for compatibility with older code paths.
 * New coordinator and placement-drive behaviour is department-scoped.
 */
class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = [
            'BTECH' => [
                'name' => 'B.Tech',
                'departments' => [
                    'CSE' => ['name' => 'Computer Science and Engineering', 'divisions' => [
                        ['FY CSE-A', 'FY-CSE-A'], ['SY CSE-A', 'SY-CSE-A'], ['SY CSE-B', 'SY-CSE-B'],
                        ['TY CSE-A', 'TY-CSE-A'], ['DSY CSE', 'DSY-CSE'],
                    ]],
                    'AIML' => ['name' => 'Artificial Intelligence and Machine Learning', 'divisions' => [
                        ['FY AI-ML-A', 'FY-AIML-A'], ['SY AI-ML-A', 'SY-AIML-A'], ['SY AI-ML-B', 'SY-AIML-B'],
                        ['TY AI-ML-A', 'TY-AIML-A'], ['DSY AI-ML', 'DSY-AIML'],
                    ]],
                    'AIDS' => ['name' => 'Artificial Intelligence and Data Science', 'divisions' => [
                        ['FY AIDS-A', 'FY-AIDS-A'], ['SY AIDS-A', 'SY-AIDS-A'], ['SY AIDS-B', 'SY-AIDS-B'],
                        ['TY AIDS-A', 'TY-AIDS-A'], ['DSY AIDS', 'DSY-AIDS'],
                    ]],
                    'IT' => ['name' => 'Information Technology', 'divisions' => [
                        ['FY IT-A', 'FY-IT-A'], ['SY IT-A', 'SY-IT-A'], ['SY IT-B', 'SY-IT-B'],
                        ['TY IT-A', 'TY-IT-A'], ['DSY IT', 'DSY-IT'],
                    ]],
                    'ECE' => ['name' => 'Electronics and Telecommunication', 'divisions' => [
                        ['FY ECE-A', 'FY-ECE-A'], ['SY ECE-A', 'SY-ECE-A'], ['SY ECE-B', 'SY-ECE-B'],
                        ['TY ECE-A', 'TY-ECE-A'], ['DSY ECE', 'DSY-ECE'],
                    ]],
                    'ME' => ['name' => 'Mechanical Engineering', 'divisions' => [
                        ['FY Mechanical-A', 'FY-ME-A'], ['SY Mechanical-A', 'SY-ME-A'], ['SY Mechanical-B', 'SY-ME-B'],
                        ['TY Mechanical-A', 'TY-ME-A'], ['DSY Mechanical', 'DSY-ME'],
                    ]],
                    'CE' => ['name' => 'Civil Engineering', 'divisions' => [
                        ['FY Civil-A', 'FY-CE-A'], ['SY Civil-A', 'SY-CE-A'], ['SY Civil-B', 'SY-CE-B'],
                        ['TY Civil-A', 'TY-CE-A'], ['DSY Civil', 'DSY-CE'],
                    ]],
                    'CYBERSEC' => ['name' => 'Cyber Security', 'divisions' => [
                        ['FY Cyber Security-A', 'FY-CYBERSEC-A'], ['SY Cyber Security-A', 'SY-CYBERSEC-A'],
                        ['SY Cyber Security-B', 'SY-CYBERSEC-B'], ['TY Cyber Security-A', 'TY-CYBERSEC-A'],
                        ['DSY Cyber Security', 'DSY-CYBERSEC'],
                    ]],
                ],
            ],
            'BBA' => [
                'name' => 'BBA',
                'departments' => [
                    'BBA' => ['name' => 'Business Administration', 'divisions' => [
                        ['FY BBA-A', 'FY-BBA-A'], ['SY BBA-A', 'SY-BBA-A'], ['TY BBA-A', 'TY-BBA-A'],
                    ]],
                ],
            ],
            'MBA' => [
                'name' => 'MBA',
                'departments' => [
                    'MBA' => ['name' => 'Management Studies', 'divisions' => [
                        ['FY MBA-A', 'FY-MBA-A'], ['FY MBA-B', 'FY-MBA-B'], ['SY MBA-A', 'SY-MBA-A'],
                    ]],
                ],
            ],
            'MTECH' => [
                'name' => 'M.Tech',
                'departments' => [
                    'CSE' => ['name' => 'Computer Science and Engineering', 'divisions' => [
                        ['M.Tech CSE-A', 'MTECH-CSE-A'], ['M.Tech CSE-B', 'MTECH-CSE-B'],
                    ]],
                    'VLSI' => ['name' => 'VLSI Design', 'divisions' => [
                        ['M.Tech VLSI-A', 'MTECH-VLSI-A'], ['M.Tech VLSI-B', 'MTECH-VLSI-B'],
                    ]],
                    'STRUCT' => ['name' => 'Structural Engineering', 'divisions' => [
                        ['M.Tech Structural-A', 'MTECH-STRUCT-A'], ['M.Tech Structural-B', 'MTECH-STRUCT-B'],
                    ]],
                ],
            ],
            'IBTECH' => [
                'name' => 'Integrated B.Tech',
                'departments' => [
                    'CSE' => ['name' => 'Computer Science and Engineering', 'divisions' => [
                        ['Integrated CSE-A', 'IBTECH-CSE-A'], ['Integrated CSE-B', 'IBTECH-CSE-B'],
                        ['Integrated DSY CSE', 'IBTECH-DSY-CSE'],
                    ]],
                    'AIML' => ['name' => 'Artificial Intelligence and Machine Learning', 'divisions' => [
                        ['Integrated AI-ML-A', 'IBTECH-AIML-A'], ['Integrated AI-ML-B', 'IBTECH-AIML-B'],
                    ]],
                ],
            ],
            'IMTECH' => [
                'name' => 'Integrated M.Tech',
                'departments' => [
                    'CSE' => ['name' => 'Computer Science and Engineering', 'divisions' => [
                        ['Integrated M.Tech CSE-A', 'IMTECH-CSE-A'], ['Integrated M.Tech CSE-B', 'IMTECH-CSE-B'],
                    ]],
                    'DS' => ['name' => 'Data Science', 'divisions' => [
                        ['Integrated M.Tech DS-A', 'IMTECH-DS-A'], ['Integrated M.Tech DS-B', 'IMTECH-DS-B'],
                    ]],
                ],
            ],
        ];

        DB::transaction(function () use ($catalogue): void {
            $departments = [];

            foreach ($catalogue as $programCode => $programData) {
                $program = Program::query()->firstOrCreate(
                    ['code' => $programCode],
                    ['name' => $programData['name'], 'is_active' => true]
                );

                foreach ($programData['departments'] as $departmentCode => $departmentData) {
                    $department = Department::query()->firstOrCreate(
                        ['program_id' => $program->id, 'code' => $departmentCode],
                        ['name' => $departmentData['name'], 'is_active' => true]
                    );
                    $departments[$programCode . ':' . $departmentCode] = $department;

                    foreach ($departmentData['divisions'] as [$divisionName, $divisionCode]) {
                        Division::query()->firstOrCreate(
                            ['department_id' => $department->id, 'code' => $divisionCode],
                            ['name' => $divisionName, 'is_active' => true]
                        );
                    }
                }
            }

            // These branch rows are compatibility data for older APIs and existing demo records.
            // The canonical academic hierarchy and placement-drive targeting use departments.
            $legacyBranches = [
                'CSE' => ['CSE', 'Computer Engineering'],
                'CSE-AIML' => ['AIML', 'CSE (AI & ML)'],
                'AIDS' => ['AIDS', 'Artificial Intelligence and Data Science'],
                'IT' => ['IT', 'Information Technology'],
                'ECE' => ['ECE', 'Electronics and Telecommunication'],
                'ME' => ['ME', 'Mechanical Engineering'],
                'CE' => ['CE', 'Civil Engineering'],
                'CYBERSEC' => ['CYBERSEC', 'Cyber Security'],
            ];

            foreach ($legacyBranches as $branchCode => [$departmentCode, $branchName]) {
                $department = $departments['BTECH:' . $departmentCode];

                Branch::query()->updateOrCreate(
                    ['code' => $branchCode],
                    ['department_id' => $department->id, 'name' => $branchName, 'is_active' => true]
                );
            }

            // The migration adds temporary UNASSIGNED divisions to preserve legacy rows.
            // Remove an unused placeholder from a fresh demo database, but never delete one
            // while a student or master-list entry still references it.
            $placeholderIds = Division::query()
                ->where('code', 'like', '%-UNASSIGNED')
                ->pluck('id');

            foreach ($placeholderIds as $divisionId) {
                $studentsUseIt = DB::table('students')->where('division_id', $divisionId)->exists();
                $masterListUsesIt = DB::table('student_master_list')->where('division_id', $divisionId)->exists();

                if (! $studentsUseIt && ! $masterListUsesIt) {
                    Division::query()->whereKey($divisionId)->delete();
                }
            }
        });

        $this->command?->info('Seeded 6 programmes, 17 departments, programme-specific divisions, and legacy branch aliases.');
    }
}
