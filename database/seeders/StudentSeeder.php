<?php

namespace Database\Seeders;

use App\Enums\AdmissionType;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Student;
use App\Models\StudentMasterList;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('TPMS_SEED_PASSWORD') ?: 'password';

        // Get departments and branches
        $cseDept = Department::where('code', 'CSE')->first();
        $itDept = Department::where('code', 'IT')->first();
        $eceDept = Department::where('code', 'ECE')->first();
        $meDept = Department::where('code', 'ME')->first();
        $ceDept = Department::where('code', 'CE')->first();

        $cseBranch = Branch::where('code', 'CSE')->first();
        $cseAimlBranch = Branch::where('code', 'CSE-AIML')->first();
        $itBranch = Branch::where('code', 'IT')->first();
        $eceBranch = Branch::where('code', 'ECE')->first();
        $meBranch = Branch::where('code', 'ME')->first();
        $ceBranch = Branch::where('code', 'CE')->first();

        $students = [
            // CSE Students (Branch code: CE)
            [
                'university_id' => '2126UCEM2564',
                'full_name' => 'Rishi Kumar',
                'institutional_email' => 'rishi.kumar@sanjivani.edu.in',
                'department_id' => $cseDept->id,
                'branch_id' => $cseBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 3,
                'user' => [
                    'name' => 'Rishi Kumar',
                    'email' => 'rishi.kumar@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2003-05-15',
                    'gender' => 'Male',
                    'personal_email' => 'rishi.kumar@gmail.com',
                    'phone' => '+91 98765 43210',
                    'current_city' => 'Pune',
                    'permanent_city' => 'Mumbai',
                    'skills' => ['JavaScript', 'React', 'Node.js', 'Python', 'SQL', 'Git'],
                    'languages' => ['English', 'Hindi', 'Marathi'],
                    'github_url' => 'https://github.com/rishikumar',
                    'linkedin_url' => 'https://linkedin.com/in/rishikumar',
                    'portfolio_url' => 'https://rishikumar.dev',
                    'preferred_roles' => ['Software Engineer', 'Full Stack Developer', 'Frontend Developer'],
                    'preferred_locations' => ['Pune', 'Mumbai', 'Bangalore', 'Hyderabad'],
                    'identity_confirmed_at' => now(),
                ],
            ],
            [
                'university_id' => '2126UCEF2565',
                'full_name' => 'Priya Sharma',
                'institutional_email' => 'priya.sharma@sanjivani.edu.in',
                'department_id' => $cseDept->id,
                'branch_id' => $cseBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 3,
                'user' => [
                    'name' => 'Priya Sharma',
                    'email' => 'priya.sharma@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2003-08-22',
                    'gender' => 'Female',
                    'personal_email' => 'priya.sharma@gmail.com',
                    'phone' => '+91 98765 43211',
                    'current_city' => 'Pune',
                    'permanent_city' => 'Delhi',
                    'skills' => ['Java', 'Spring Boot', 'MySQL', 'Docker', 'AWS'],
                    'languages' => ['English', 'Hindi'],
                    'github_url' => 'https://github.com/priyasharma',
                    'linkedin_url' => 'https://linkedin.com/in/priyasharma',
                    'preferred_roles' => ['Backend Developer', 'Java Developer', 'Software Engineer'],
                    'preferred_locations' => ['Pune', 'Bangalore', 'Hyderabad'],
                    'identity_confirmed_at' => now(),
                ],
            ],
            [
                'university_id' => '2126UCEM2566',
                'full_name' => 'Amit Patel',
                'institutional_email' => 'amit.patel@sanjivani.edu.in',
                'department_id' => $cseDept->id,
                'branch_id' => $cseAimlBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 3,
                'user' => [
                    'name' => 'Amit Patel',
                    'email' => 'amit.patel@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2003-11-10',
                    'gender' => 'Male',
                    'personal_email' => 'amit.patel@gmail.com',
                    'phone' => '+91 98765 43212',
                    'current_city' => 'Pune',
                    'permanent_city' => 'Ahmedabad',
                    'skills' => ['Python', 'Machine Learning', 'TensorFlow', 'Pandas', 'NumPy', 'Scikit-learn'],
                    'languages' => ['English', 'Hindi', 'Gujarati'],
                    'github_url' => 'https://github.com/amitpatel',
                    'linkedin_url' => 'https://linkedin.com/in/amitpatel',
                    'preferred_roles' => ['ML Engineer', 'Data Scientist', 'AI Researcher'],
                    'preferred_locations' => ['Bangalore', 'Hyderabad', 'Pune'],
                    'identity_confirmed_at' => now(),
                ],
            ],
            // IT Students (Branch code: IT)
            [
                'university_id' => '2126UITF2567',
                'full_name' => 'Sneha Reddy',
                'institutional_email' => 'sneha.reddy@sanjivani.edu.in',
                'department_id' => $itDept->id,
                'branch_id' => $itBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 3,
                'user' => [
                    'name' => 'Sneha Reddy',
                    'email' => 'sneha.reddy@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2004-01-18',
                    'gender' => 'Female',
                    'personal_email' => 'sneha.reddy@gmail.com',
                    'phone' => '+91 98765 43213',
                    'current_city' => 'Pune',
                    'permanent_city' => 'Hyderabad',
                    'skills' => ['C++', 'Data Structures', 'Algorithms', 'Competitive Programming', 'Linux'],
                    'languages' => ['English', 'Hindi', 'Telugu'],
                    'github_url' => 'https://github.com/snehareddy',
                    'linkedin_url' => 'https://linkedin.com/in/snehareddy',
                    'preferred_roles' => ['Software Engineer', 'Systems Engineer', 'DevOps Engineer'],
                    'preferred_locations' => ['Hyderabad', 'Bangalore', 'Pune'],
                    'identity_confirmed_at' => now(),
                ],
            ],
            [
                'university_id' => '2126UITM2568',
                'full_name' => 'Rahul Singh',
                'institutional_email' => 'rahul.singh@sanjivani.edu.in',
                'department_id' => $itDept->id,
                'branch_id' => $itBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 3,
                'user' => [
                    'name' => 'Rahul Singh',
                    'email' => 'rahul.singh@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2003-07-25',
                    'gender' => 'Male',
                    'personal_email' => 'rahul.singh@gmail.com',
                    'phone' => '+91 98765 43214',
                    'current_city' => 'Pune',
                    'permanent_city' => 'Lucknow',
                    'skills' => ['Python', 'Django', 'PostgreSQL', 'Redis', 'Docker', 'Kubernetes'],
                    'languages' => ['English', 'Hindi'],
                    'github_url' => 'https://github.com/rahulsingh',
                    'linkedin_url' => 'https://linkedin.com/in/rahulsingh',
                    'preferred_roles' => ['Full Stack Developer', 'Backend Developer', 'Cloud Engineer'],
                    'preferred_locations' => ['Pune', 'Mumbai', 'Bangalore'],
                    'identity_confirmed_at' => now(),
                ],
            ],
            // ECE Students (Branch code: EC)
            [
                'university_id' => '2126UECF2569',
                'full_name' => 'Kavya Nair',
                'institutional_email' => 'kavya.nair@sanjivani.edu.in',
                'department_id' => $eceDept->id,
                'branch_id' => $eceBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 3,
                'user' => [
                    'name' => 'Kavya Nair',
                    'email' => 'kavya.nair@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2003-09-30',
                    'gender' => 'Female',
                    'personal_email' => 'kavya.nair@gmail.com',
                    'phone' => '+91 98765 43215',
                    'current_city' => 'Pune',
                    'permanent_city' => 'Kochi',
                    'skills' => ['VHDL', 'Verilog', 'Embedded C', 'Arduino', 'Raspberry Pi', 'PCB Design'],
                    'languages' => ['English', 'Hindi', 'Malayalam'],
                    'github_url' => 'https://github.com/kavyanair',
                    'linkedin_url' => 'https://linkedin.com/in/kavyanair',
                    'preferred_roles' => ['Embedded Engineer', 'VLSI Engineer', 'IoT Developer'],
                    'preferred_locations' => ['Bangalore', 'Hyderabad', 'Pune'],
                    'identity_confirmed_at' => now(),
                ],
            ],
            // ME Students (Branch code: ME)
            [
                'university_id' => '2126UMEF2570',
                'full_name' => 'Vikram Deshmukh',
                'institutional_email' => 'vikram.deshmukh@sanjivani.edu.in',
                'department_id' => $meDept->id,
                'branch_id' => $meBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 3,
                'user' => [
                    'name' => 'Vikram Deshmukh',
                    'email' => 'vikram.deshmukh@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2003-03-12',
                    'gender' => 'Male',
                    'personal_email' => 'vikram.deshmukh@gmail.com',
                    'phone' => '+91 98765 43216',
                    'current_city' => 'Pune',
                    'permanent_city' => 'Nashik',
                    'skills' => ['SolidWorks', 'AutoCAD', 'ANSYS', 'MATLAB', '3D Printing'],
                    'languages' => ['English', 'Hindi', 'Marathi'],
                    'github_url' => 'https://github.com/vikramdeshmukh',
                    'linkedin_url' => 'https://linkedin.com/in/vikramdeshmukh',
                    'preferred_roles' => ['Design Engineer', 'Mechanical Engineer', 'R&D Engineer'],
                    'preferred_locations' => ['Pune', 'Mumbai', 'Chennai'],
                    'identity_confirmed_at' => now(),
                ],
            ],
            // CE Students (Branch code: CV for Civil)
            [
                'university_id' => '2126UCVF2571',
                'full_name' => 'Anjali Gupta',
                'institutional_email' => 'anjali.gupta@sanjivani.edu.in',
                'department_id' => $ceDept->id,
                'branch_id' => $ceBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 3,
                'user' => [
                    'name' => 'Anjali Gupta',
                    'email' => 'anjali.gupta@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2004-06-08',
                    'gender' => 'Female',
                    'personal_email' => 'anjali.gupta@gmail.com',
                    'phone' => '+91 98765 43217',
                    'current_city' => 'Pune',
                    'permanent_city' => 'Jaipur',
                    'skills' => ['AutoCAD', 'STAAD.Pro', 'Revit', 'ETABS', 'Project Management'],
                    'languages' => ['English', 'Hindi'],
                    'linkedin_url' => 'https://linkedin.com/in/anjaligupta',
                    'preferred_roles' => ['Structural Engineer', 'Site Engineer', 'Project Coordinator'],
                    'preferred_locations' => ['Pune', 'Mumbai', 'Delhi'],
                    'identity_confirmed_at' => now(),
                ],
            ],
        ];

        foreach ($students as $data) {
            // Create or update master list entry
            $masterList = StudentMasterList::firstOrCreate(
                ['university_id' => $data['university_id']],
                [
                    'full_name' => $data['full_name'],
                    'institutional_email' => $data['institutional_email'],
                    'department_id' => $data['department_id'],
                    'branch_id' => $data['branch_id'],
                    'admission_type' => $data['admission_type']->value,
                    'admission_year' => $data['admission_year'],
                    'graduation_year' => $data['graduation_year'],
                    'current_semester' => $data['current_semester'],
                    'claimed_at' => now(),
                    'import_batch' => 'seed-' . date('Y-m-d'),
                ]
            );

            // Create user
            $user = User::firstOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name' => $data['user']['name'],
                    'password' => $data['user']['password'],
                    'role' => $data['user']['role'],
                    'is_active' => $data['user']['is_active'],
                    'email_verified_at' => now(),
                ]
            );

            // Create student profile
            Student::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'master_list_id' => $masterList->id,
                    'university_id' => $data['university_id'],
                    'full_name' => $data['full_name'],
                    'department_id' => $data['department_id'],
                    'branch_id' => $data['branch_id'],
                    'admission_type' => $data['admission_type']->value,
                    'admission_year' => $data['admission_year'],
                    'graduation_year' => $data['graduation_year'],
                    'current_semester' => $data['current_semester'],
                    'date_of_birth' => $data['student']['date_of_birth'],
                    'gender' => $data['student']['gender'],
                    'personal_email' => $data['student']['personal_email'],
                    'phone' => $data['student']['phone'],
                    'current_city' => $data['student']['current_city'],
                    'permanent_city' => $data['student']['permanent_city'],
                    'skills' => $data['student']['skills'] ?? [],
                    'languages' => $data['student']['languages'] ?? [],
                    'github_url' => $data['student']['github_url'] ?? null,
                    'linkedin_url' => $data['student']['linkedin_url'] ?? null,
                    'portfolio_url' => $data['student']['portfolio_url'] ?? null,
                    'preferred_roles' => $data['student']['preferred_roles'] ?? [],
                    'preferred_locations' => $data['student']['preferred_locations'] ?? [],
                    'identity_confirmed_at' => $data['student']['identity_confirmed_at'],
                ]
            );
        }

        $this->command->info('Created ' . count($students) . ' student users with profiles and master list entries.');
    }
}