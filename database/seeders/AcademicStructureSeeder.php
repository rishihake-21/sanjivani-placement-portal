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
                        ['Final Year CSE-A', 'FINAL-CSE-A'], ['Final Year CSE-B', 'FINAL-CSE-B'],
                    ]],
                    'AIML' => ['name' => 'Artificial Intelligence and Machine Learning', 'divisions' => [
                        ['FY AI-ML-A', 'FY-AIML-A'], ['SY AI-ML-A', 'SY-AIML-A'], ['SY AI-ML-B', 'SY-AIML-B'],
                        ['TY AI-ML-A', 'TY-AIML-A'], ['DSY AI-ML', 'DSY-AIML'],
                    ]],
                    'AIDS' => ['name' => 'Artificial Intelligence and Data Science', 'divisions' => [
                        ['FY AIDS-A', 'FY-AIDS-A'], ['SY AIDS-A', 'SY-AIDS-A'], ['SY AIDS-B', 'SY-AIDS-B'],
                        ['TY AIDS-A', 'TY-AIDS-A'], ['DSY AIDS', 'DSY-AIDS'],
                    ]],
                    'ME' => ['name' => 'Mechanical Engineering', 'divisions' => [
                        ['FY Mechanical-A', 'FY-ME-A'], ['SY Mechanical-A', 'SY-ME-A'], ['SY Mechanical-B', 'SY-ME-B'],
                        ['TY Mechanical-A', 'TY-ME-A'], ['DSY Mechanical', 'DSY-ME'],
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
                // B.Tech codes preserve the existing API and sample-roster references.
                'CSE' => ['BTECH', 'CSE', 'Computer Engineering'],
                'CSE-AIML' => ['BTECH', 'AIML', 'CSE (AI & ML)'],
                'AIDS' => ['BTECH', 'AIDS', 'Artificial Intelligence and Data Science'],
                'ME' => ['BTECH', 'ME', 'Mechanical Engineering'],
                'CYBERSEC' => ['BTECH', 'CYBERSEC', 'Cyber Security'],

                // Programme-qualified aliases avoid collisions where the same
                // department code (for example CSE) appears in multiple programmes.
                'BBA-BBA' => ['BBA', 'BBA', 'Business Administration'],
                'MBA-MBA' => ['MBA', 'MBA', 'Management Studies'],
                'MTECH-CSE' => ['MTECH', 'CSE', 'Computer Science and Engineering'],
                'MTECH-VLSI' => ['MTECH', 'VLSI', 'VLSI Design'],
                'MTECH-STRUCT' => ['MTECH', 'STRUCT', 'Structural Engineering'],
                'IBTECH-CSE' => ['IBTECH', 'CSE', 'Computer Science and Engineering'],
                'IBTECH-AIML' => ['IBTECH', 'AIML', 'Artificial Intelligence and Machine Learning'],
                'IMTECH-CSE' => ['IMTECH', 'CSE', 'Computer Science and Engineering'],
                'IMTECH-DS' => ['IMTECH', 'DS', 'Data Science'],
            ];

            foreach ($legacyBranches as $branchCode => [$programCode, $departmentCode, $branchName]) {
                $department = $departments[$programCode . ':' . $departmentCode];

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

        $this->command?->info('Seeded 6 programmes, 14 departments, programme-specific divisions, and legacy branch aliases.');
    }
}
