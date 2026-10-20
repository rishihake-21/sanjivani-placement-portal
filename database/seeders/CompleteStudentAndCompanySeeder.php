<?php

namespace Database\Seeders;

use App\Enums\AdmissionType;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\DriveStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Division;
use App\Models\Program;
use App\Models\PlacementDrive;
use App\Models\Student;
use App\Models\StudentMasterList;
use App\Models\User;
use App\Models\AcademicRecord;
use App\Models\Document;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompleteStudentAndCompanySeeder extends Seeder
{
    public function run(): void
    {
        $password = env('TPMS_SEED_PASSWORD') ?: 'password';
        $hashedPassword = Hash::make($password);

        // Get departments and branches
        // The sample records below are B.Tech students; resolve departments within B.Tech.
        $btechId = Program::query()->where('code', 'BTECH')->value('id');
        $cseDept = Department::where('program_id', $btechId)->where('code', 'CSE')->first();
        $itDept = Department::where('program_id', $btechId)->where('code', 'IT')->first();
        $eceDept = Department::where('program_id', $btechId)->where('code', 'ECE')->first();
        $meDept = Department::where('program_id', $btechId)->where('code', 'ME')->first();
        $ceDept = Department::where('program_id', $btechId)->where('code', 'CE')->first();

        $cseBranch = Branch::where('code', 'CSE')->first();
        $cseAimlBranch = Branch::where('code', 'CSE-AIML')->first();
        $itBranch = Branch::where('code', 'IT')->first();
        $eceBranch = Branch::where('code', 'ECE')->first();
        $meBranch = Branch::where('code', 'ME')->first();
        $ceBranch = Branch::where('code', 'CE')->first();

        // ============================================
        // COMPLETE STUDENTS (all sections complete)
        // ============================================
        $completeStudents = [
            [
                'university_id' => '2126UCEM1001',
                'full_name' => 'Arjun Sharma',
                'institutional_email' => 'arjun.sharma@sanjivani.edu.in',
                'department_id' => $cseDept->id,
                'branch_id' => $cseBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 5,
                'user' => [
                    'name' => 'Arjun Sharma',
                    'email' => 'arjun.sharma@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2005-02-20',
                    'gender' => 'Male',
                    'personal_email' => 'arjun.sharma20@gmail.com',
                    'phone' => '+91 98765 44001',
                    'current_city' => 'Kopargaon',
                    'permanent_city' => 'Nashik',
                    'skills' => ['Java', 'Spring Boot', 'React', 'PostgreSQL', 'Docker', 'AWS', 'Git'],
                    'languages' => ['English', 'Hindi', 'Marathi'],
                    'github_url' => 'https://github.com/arjun-sharma',
                    'linkedin_url' => 'https://linkedin.com/in/arjun-sharma',
                    'portfolio_url' => 'https://arjunsharma.dev',
                    'preferred_roles' => ['Software Engineer', 'Full Stack Developer', 'Backend Developer'],
                    'preferred_locations' => ['Pune', 'Mumbai', 'Bangalore', 'Hyderabad'],
                    'identity_confirmed_at' => now(),
                ],
                // Academic records - all required verified
                'academic' => [
                    ['level' => 'TENTH', 'semester' => null, 'institution' => 'Sanjivani Vidyalaya', 'board' => 'Maharashtra State Board', 'year' => 2021, 'percentage' => 94.2, 'obtained' => 471, 'total' => 500, 'status' => 'VERIFIED'],
                    ['level' => 'TWELFTH', 'semester' => null, 'institution' => 'Sanjivani Junior College', 'board' => 'Maharashtra State Board', 'year' => 2023, 'percentage' => 89.5, 'obtained' => 537, 'total' => 600, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 1, 'sgpa' => 8.5, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 2, 'sgpa' => 8.7, 'cgpa' => 8.6, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 3, 'sgpa' => 8.8, 'cgpa' => 8.67, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 4, 'sgpa' => 9.0, 'cgpa' => 8.75, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                ],
                // Experience
                'experience' => [
                    ['type' => 'INTERNSHIP', 'org' => 'TechCorp Solutions', 'role' => 'Full Stack Intern', 'start' => '2026-01-05', 'end' => '2026-03-30', 'status' => 'VERIFIED', 'desc' => 'Built REST APIs with Spring Boot and React frontend for an e-commerce platform.'],
                    ['type' => 'PROJECT_WORK', 'org' => 'Sanjivani University', 'role' => 'Final Year Project', 'start' => '2026-07-01', 'end' => '2026-11-30', 'status' => 'SELF_DECLARED', 'desc' => 'AI-based recommendation system for student course selection using Python and TensorFlow.'],
                ],
                // Documents - resume approved
                'documents' => [
                    ['type' => 'RESUME', 'name' => 'Arjun_Sharma_Resume.pdf', 'status' => 'APPROVED', 'primary' => true],
                ],
            ],
            [
                'university_id' => '2126UITF1002',
                'full_name' => 'Priya Nair',
                'institutional_email' => 'priya.nair@sanjivani.edu.in',
                'department_id' => $itDept->id,
                'branch_id' => $itBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 5,
                'user' => [
                    'name' => 'Priya Nair',
                    'email' => 'priya.nair@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2004-11-15',
                    'gender' => 'Female',
                    'personal_email' => 'priya.nair2004@gmail.com',
                    'phone' => '+91 98765 44002',
                    'current_city' => 'Kopargaon',
                    'permanent_city' => 'Kochi',
                    'skills' => ['Python', 'Django', 'Machine Learning', 'PostgreSQL', 'Redis', 'Docker', 'Kubernetes'],
                    'languages' => ['English', 'Hindi', 'Malayalam'],
                    'github_url' => 'https://github.com/priya-nair',
                    'linkedin_url' => 'https://linkedin.com/in/priya-nair',
                    'preferred_roles' => ['Data Scientist', 'ML Engineer', 'Backend Developer'],
                    'preferred_locations' => ['Bangalore', 'Pune', 'Hyderabad', 'Remote'],
                    'identity_confirmed_at' => now(),
                ],
                'academic' => [
                    ['level' => 'TENTH', 'semester' => null, 'institution' => 'Kendriya Vidyalaya', 'board' => 'CBSE', 'year' => 2021, 'percentage' => 96.4, 'obtained' => 482, 'total' => 500, 'status' => 'VERIFIED'],
                    ['level' => 'TWELFTH', 'semester' => null, 'institution' => 'Kendriya Vidyalaya', 'board' => 'CBSE', 'year' => 2023, 'percentage' => 92.8, 'obtained' => 557, 'total' => 600, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 1, 'sgpa' => 9.2, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 2, 'sgpa' => 9.1, 'cgpa' => 9.15, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 3, 'sgpa' => 9.3, 'cgpa' => 9.2, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 4, 'sgpa' => 9.0, 'cgpa' => 9.15, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                ],
                'experience' => [
                    ['type' => 'INTERNSHIP', 'org' => 'DataMinds AI', 'role' => 'ML Intern', 'start' => '2025-12-01', 'end' => '2026-02-28', 'status' => 'VERIFIED', 'desc' => 'Developed NLP models for sentiment analysis using BERT and fine-tuned on custom datasets.'],
                ],
                'documents' => [
                    ['type' => 'RESUME', 'name' => 'Priya_Nair_Resume.pdf', 'status' => 'APPROVED', 'primary' => true],
                ],
            ],
            [
                'university_id' => '2126UECM1003',
                'full_name' => 'Rohit Patil',
                'institutional_email' => 'rohit.patil@sanjivani.edu.in',
                'department_id' => $eceDept->id,
                'branch_id' => $eceBranch->id,
                'admission_type' => AdmissionType::REGULAR,
                'admission_year' => 2024,
                'graduation_year' => 2028,
                'current_semester' => 5,
                'user' => [
                    'name' => 'Rohit Patil',
                    'email' => 'rohit.patil@sanjivani.edu.in',
                    'password' => $password,
                    'role' => Role::STUDENT,
                    'is_active' => true,
                ],
                'student' => [
                    'date_of_birth' => '2005-06-10',
                    'gender' => 'Male',
                    'personal_email' => 'rohit.patil.ece@gmail.com',
                    'phone' => '+91 98765 44003',
                    'current_city' => 'Kopargaon',
                    'permanent_city' => 'Sangamner',
                    'skills' => ['C', 'Embedded C', 'VHDL', 'Verilog', 'Arduino', 'Raspberry Pi', 'PCB Design', 'Python'],
                    'languages' => ['English', 'Hindi', 'Marathi'],
                    'github_url' => 'https://github.com/rohit-patil',
                    'linkedin_url' => 'https://linkedin.com/in/rohit-patil',
                    'preferred_roles' => ['Embedded Engineer', 'VLSI Design Engineer', 'IoT Developer'],
                    'preferred_locations' => ['Pune', 'Bangalore', 'Hyderabad'],
                    'identity_confirmed_at' => now(),
                ],
                'academic' => [
                    ['level' => 'TENTH', 'semester' => null, 'institution' => 'Z.P. High School', 'board' => 'Maharashtra State Board', 'year' => 2021, 'percentage' => 90.8, 'obtained' => 454, 'total' => 500, 'status' => 'VERIFIED'],
                    ['level' => 'TWELFTH', 'semester' => null, 'institution' => 'Sanjivani Junior College', 'board' => 'Maharashtra State Board', 'year' => 2023, 'percentage' => 85.6, 'obtained' => 514, 'total' => 600, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 1, 'sgpa' => 8.3, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 2, 'sgpa' => 8.5, 'cgpa' => 8.4, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 3, 'sgpa' => 8.6, 'cgpa' => 8.47, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                    ['level' => 'DEGREE_SEM', 'semester' => 4, 'sgpa' => 8.8, 'cgpa' => 8.55, 'backlogs' => 0, 'active_backlogs' => 0, 'status' => 'VERIFIED'],
                ],
                'experience' => [
                    ['type' => 'PROJECT_WORK', 'org' => 'Sanjivani University', 'role' => 'Smart Home Automation', 'start' => '2026-02-01', 'end' => '2026-05-30', 'status' => 'SELF_DECLARED', 'desc' => 'IoT-based home automation system using ESP32, MQTT, and a React Native app.'],
                ],
                'documents' => [
                    ['type' => 'RESUME', 'name' => 'Rohit_Patil_Resume.pdf', 'status' => 'APPROVED', 'primary' => true],
                ],
            ],
        ];

        // Create complete students with academic records, experience, and documents
        foreach ($completeStudents as $data) {
            $departmentCode = Department::query()->whereKey($data['department_id'])->value('code');
            $divisionId = Division::query()->where('department_id', $data['department_id'])->where('code', substr(strtoupper((string) $departmentCode), 0, 18) . '-UNASSIGNED')->value('id')
                ?? Division::query()->where('department_id', $data['department_id'])->where('is_active', true)->value('id');

            // Create master list entry
            $masterList = StudentMasterList::firstOrCreate(
                ['university_id' => $data['university_id']],
                [
                    'full_name' => $data['full_name'],
                    'institutional_email' => $data['institutional_email'],
                    'department_id' => $data['department_id'],
                    'branch_id' => $data['branch_id'],
                    'division_id' => $divisionId,
                    'admission_type' => $data['admission_type']->value,
                    'admission_year' => $data['admission_year'],
                    'graduation_year' => $data['graduation_year'],
                    'current_semester' => $data['current_semester'],
                    'claimed_at' => now(),
                    'import_batch' => 'seed-complete-' . date('Y-m-d'),
                ]
            );

            // Create user
            $user = User::firstOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name' => $data['user']['name'],
                    'password' => $hashedPassword,
                    'role' => $data['user']['role'],
                    'is_active' => $data['user']['is_active'],
                    'email_verified_at' => now(),
                ]
            );

            // Create student profile
            $student = Student::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'master_list_id' => $masterList->id,
                    'university_id' => $data['university_id'],
                    'full_name' => $data['full_name'],
                    'department_id' => $data['department_id'],
                    'branch_id' => $data['branch_id'],
                    'division_id' => $divisionId,
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

            // Create academic records with marksheet documents
            foreach ($data['academic'] as $acad) {
                // Create marksheet document first
                $levelDocType = match ($acad['level']) {
                    'TENTH' => DocumentType::MARKSHEET_10TH,
                    'TWELFTH' => DocumentType::MARKSHEET_12TH,
                    'DIPLOMA' => DocumentType::MARKSHEET_DIPLOMA,
                    'DEGREE_SEM' => DocumentType::MARKSHEET_SEMESTER,
                    default => DocumentType::MARKSHEET_SEMESTER,
                };

                $marksheetName = match ($acad['level']) {
                    'TENTH' => "{$data['full_name']}_10th_Marksheet.pdf",
                    'TWELFTH' => "{$data['full_name']}_12th_Marksheet.pdf",
                    'DIPLOMA' => "{$data['full_name']}_Diploma_Marksheet.pdf",
                    'DEGREE_SEM' => "{$data['full_name']}_Sem{$acad['semester']}_Marksheet.pdf",
                };

                $reviewedBy = User::where('role', Role::TP_COORDINATOR)->inRandomOrder()->first()?->id;

                $marksheetDoc = Document::create([
                    'student_id' => $student->id,
                    'uuid' => Str::uuid(),
                    'document_type' => $levelDocType->value,
                    'version' => 1,
                    'status' => DocumentStatus::APPROVED,
                    'is_primary' => false,
                    'original_name' => $marksheetName,
                    'mime_type' => 'application/pdf',
                    'size_bytes' => rand(150000, 400000),
                    'sha256' => Str::random(64),
                    'disk' => 'local',
                    'path' => "marksheets/{$student->id}/{$marksheetName}",
                    'uploaded_by' => $reviewedBy,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => now()->subDays(rand(1, 20)),
                ]);

                // Create academic record with document reference
                AcademicRecord::firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'level' => $acad['level'],
                        'semester' => $acad['semester'] ?? null,
                        'version' => 1,
                    ],
                    [
                        'institution_name' => $acad['institution'] ?? null,
                        'board_or_university' => $acad['board'] ?? null,
                        'passing_year' => $acad['year'] ?? null,
                        'percentage' => $acad['percentage'] ?? null,
                        'obtained_marks' => $acad['obtained'] ?? null,
                        'total_marks' => $acad['total'] ?? null,
                        'sgpa' => $acad['sgpa'] ?? null,
                        'cgpa' => $acad['cgpa'] ?? null,
                        'backlogs_in_term' => $acad['backlogs'] ?? 0,
                        'active_backlogs_after_term' => $acad['active_backlogs'] ?? 0,
                        'status' => $acad['status'],
                        'document_id' => $marksheetDoc->id,
                        'submitted_at' => now()->subDays(rand(30, 60)),
                        'reviewed_at' => now()->subDays(rand(1, 20)),
                        'reviewed_by' => User::where('role', Role::TP_COORDINATOR)->inRandomOrder()->first()?->id,
                        'locked_at' => in_array($acad['status'], ['VERIFIED']) ? now()->subDays(rand(1, 10)) : null,
                    ]
                );
            }

            // Create experience
            foreach ($data['experience'] as $exp) {
                $certDocId = null;
                $reviewedBy = null;
                $reviewedAt = null;
                $submittedAt = null;

                if (in_array($exp['status'], ['VERIFIED', 'PENDING'])) {
                    // Create certificate document
                    $certType = match ($exp['type']) {
                        'INTERNSHIP' => DocumentType::CERT_INTERNSHIP,
                        'JOB' => DocumentType::CERT_EXPERIENCE,
                        'PROJECT_WORK' => DocumentType::CERT_OTHER,
                        'TRAINING' => DocumentType::CERT_OTHER,
                        default => DocumentType::CERT_OTHER,
                    };

                    $certName = "{$data['full_name']}_{$exp['type']}_Certificate.pdf";

                    $reviewedBy = User::where('role', Role::TP_COORDINATOR)->inRandomOrder()->first()?->id;

                    $certDoc = Document::create([
                        'student_id' => $student->id,
                        'uuid' => Str::uuid(),
                        'document_type' => $certType->value,
                        'version' => 1,
                        'status' => DocumentStatus::APPROVED,
                        'is_primary' => false,
                        'original_name' => $certName,
                        'mime_type' => 'application/pdf',
                        'size_bytes' => rand(100000, 300000),
                        'sha256' => Str::random(64),
                        'disk' => 'local',
                        'path' => "certificates/{$student->id}/{$certName}",
                        'uploaded_by' => $reviewedBy,
                        'reviewed_by' => $reviewedBy,
                        'reviewed_at' => now()->subDays(rand(1, 20)),
                    ]);

                    $certDocId = $certDoc->id;
                    $reviewedAt = now()->subDays(rand(1, 20));
                    $submittedAt = now()->subDays(rand(30, 60));
                } elseif ($exp['status'] !== 'SELF_DECLARED') {
                    $reviewedBy = User::where('role', Role::TP_COORDINATOR)->inRandomOrder()->first()?->id;
                    $reviewedAt = $exp['status'] === 'VERIFIED' ? now()->subDays(rand(1, 20)) : null;
                    $submittedAt = now()->subDays(rand(30, 60));
                }

                $student->experiences()->create([
                    'type' => $exp['type'],
                    'organization' => $exp['org'],
                    'role_title' => $exp['role'],
                    'start_date' => $exp['start'],
                    'end_date' => $exp['end'] ?? null,
                    'is_ongoing' => empty($exp['end']),
                    'description' => $exp['desc'],
                    'status' => $exp['status'],
                    'certificate_document_id' => $certDocId,
                    'submitted_at' => $submittedAt,
                    'reviewed_at' => $reviewedAt,
                    'reviewed_by' => $reviewedBy,
                ]);
            }

            // Create documents (resume)
            foreach ($data['documents'] as $doc) {
                $reviewedBy = $doc['status'] === 'APPROVED' ? User::where('role', Role::TP_COORDINATOR)->inRandomOrder()->first()?->id : null;
                $reviewedAt = $doc['status'] === 'APPROVED' ? now()->subDays(rand(1, 10)) : null;

                Document::create([
                    'student_id' => $student->id,
                    'uuid' => Str::uuid(),
                    'document_type' => $doc['type'],
                    'version' => 1,
                    'status' => $doc['status'],
                    'is_primary' => $doc['primary'] ?? false,
                    'original_name' => $doc['name'],
                    'mime_type' => 'application/pdf',
                    'size_bytes' => rand(100000, 500000),
                    'sha256' => Str::random(64),
                    'disk' => 'local',
                    'path' => "resumes/{$student->id}/{$doc['name']}",
                    'uploaded_by' => $reviewedBy,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                ]);
            }
        }

        // ============================================
        // COMPANIES
        // ============================================
        $tpoUser = User::where('role', Role::TPO)->first();
        if (!$tpoUser) {
            $tpoUser = User::firstOrCreate(
                ['email' => 'tpo@tpms.local'],
                ['name' => 'Training & Placement Officer', 'password' => $hashedPassword, 'role' => Role::TPO, 'is_active' => true]
            );
        }

        $companies = [
            [
                'name' => 'Tata Consultancy Services (TCS)',
                'website' => 'https://www.tcs.com',
                'industry' => 'IT Services & Consulting',
                'contact_name' => 'Rajesh Kumar',
                'contact_email' => 'rajesh.kumar@tcs.com',
                'contact_phone' => '+91 22 6778 1234',
                'notes' => 'Strategic partner for campus hiring. Regular visitor for 2024-28 batches.',
                'is_active' => true,
                'created_by' => $tpoUser->id,
            ],
            [
                'name' => 'Infosys Limited',
                'website' => 'https://www.infosys.com',
                'industry' => 'IT Services & Consulting',
                'contact_name' => 'Sunita Reddy',
                'contact_email' => 'sunita.reddy@infosys.com',
                'contact_phone' => '+91 80 2852 0261',
                'notes' => 'Regular campus hiring partner. Focus on digital skills.',
                'is_active' => true,
                'created_by' => $tpoUser->id,
            ],
            [
                'name' => 'Amazon Development Center India',
                'website' => 'https://www.amazon.jobs',
                'industry' => 'E-commerce / Cloud Computing',
                'contact_name' => 'Amit Singh',
                'contact_email' => 'amit.singh@amazon.com',
                'contact_phone' => '+91 80 6724 7000',
                'notes' => 'High package offers. Prefers students with strong DSA and system design.',
                'is_active' => true,
                'created_by' => $tpoUser->id,
            ],
        ];

        foreach ($companies as $compData) {
            Company::firstOrCreate(
                ['name' => $compData['name']],
                $compData
            );
        }

        // ============================================
        // PLACEMENT DRIVES
        // ============================================
        $tcs = Company::where('name', 'Tata Consultancy Services (TCS)')->first();
        $infosys = Company::where('name', 'Infosys Limited')->first();
        $amazon = Company::where('name', 'Amazon Development Center India')->first();

        $drives = [
            [
                'company_id' => $tcs->id,
                'title' => 'Software Engineer - TCS Ninja',
                'description' => 'TCS Ninja is the flagship hiring program for fresh engineering graduates. Selected candidates undergo a comprehensive training program before being deployed on client projects.',
                'employment_type' => 'FULL_TIME',
                'ctc_lpa' => 7.00,
                'ctc_max_lpa' => 7.00,
                'location' => 'Pune, Mumbai, Bangalore, Hyderabad, Chennai',
                'drive_date' => '2026-11-15',
                'application_deadline' => '2026-11-01 23:59:59',
                'graduation_year' => 2028,
                'additional_requirements' => 'No active backlogs. 60% throughout academics (10th, 12th, UG). Gap in education not exceeding 2 years.',
                'status' => 'PUBLISHED',
                'published_at' => now()->subDays(30),
                'created_by' => $tpoUser->id,
                'eligibility' => [
                    'min_cgpa' => 6.5,
                    'min_tenth_percentage' => 60,
                    'min_twelfth_percentage' => 60,
                    'max_active_backlogs' => 0,
                    'max_total_backlogs' => 0,
                ],
                'branches' => ['CSE', 'CSE-AIML', 'IT', 'ECE'],
            ],
            [
                'company_id' => $infosys->id,
                'title' => 'Systems Engineer - Infosys',
                'description' => 'Infosys is hiring Systems Engineers for their 2028 batch. The role involves working on cutting-edge technologies across multiple domains including cloud, AI, and digital transformation.',
                'employment_type' => 'FULL_TIME',
                'ctc_lpa' => 6.50,
                'ctc_max_lpa' => 6.50,
                'location' => 'Mysore, Bangalore, Pune, Hyderabad',
                'drive_date' => '2026-11-20',
                'application_deadline' => '2026-11-05 23:59:59',
                'graduation_year' => 2028,
                'additional_requirements' => 'Minimum 60% in 10th, 12th, and graduation. No active backlogs. Maximum 2 years gap allowed.',
                'status' => 'PUBLISHED',
                'published_at' => now()->subDays(25),
                'created_by' => $tpoUser->id,
                'eligibility' => [
                    'min_cgpa' => 6.0,
                    'min_tenth_percentage' => 60,
                    'min_twelfth_percentage' => 60,
                    'max_active_backlogs' => 0,
                    'max_total_backlogs' => 0,
                ],
                'branches' => ['CSE', 'CSE-AIML', 'IT', 'ECE', 'ECE'],
            ],
            [
                'company_id' => $amazon->id,
                'title' => 'Software Development Engineer - Intern to Full Time',
                'description' => 'Amazon is looking for top talent for their SDE internship program with a conversion opportunity to full-time. The program includes challenging projects in distributed systems, cloud computing, and machine learning.',
                'employment_type' => 'INTERNSHIP_PPO',
                'ctc_lpa' => 44.00,
                'ctc_max_lpa' => 44.00,
                'location' => 'Bangalore, Hyderabad',
                'drive_date' => '2026-12-01',
                'application_deadline' => '2026-11-10 23:59:59',
                'graduation_year' => 2028,
                'additional_requirements' => 'Strong data structures and algorithms. Proficiency in at least one: Java, C++, Python. Prior internship/project experience preferred. CGPA 8.0+.',
                'status' => 'PUBLISHED',
                'published_at' => now()->subDays(20),
                'created_by' => $tpoUser->id,
                'eligibility' => [
                    'min_cgpa' => 8.0,
                    'min_tenth_percentage' => 70,
                    'min_twelfth_percentage' => 70,
                    'max_active_backlogs' => 0,
                    'max_total_backlogs' => 0,
                ],
                'branches' => ['CSE', 'CSE-AIML', 'IT'],
            ],
        ];

        foreach ($drives as $driveData) {
            $eligibility = $driveData['eligibility'];
            $branches = $driveData['branches'];
            unset($driveData['eligibility'], $driveData['branches']);

            $drive = PlacementDrive::firstOrCreate(
                ['company_id' => $driveData['company_id'], 'title' => $driveData['title']],
                $driveData
            );

            // Create eligibility
            $drive->eligibility()->updateOrCreate(
                ['drive_id' => $drive->id],
                $eligibility
            );

            // Attach branches
            $branchIds = Branch::whereIn('code', $branches)->pluck('id');
            $drive->branches()->sync($branchIds);
            $targetDepartmentIds = Branch::query()->whereIn('id', $branchIds)->get(['code', 'department_id'])->map(function ($branch) {
                if ($branch->code === 'CSE-AIML') {
                    return Department::query()->where('program_id', Program::query()->where('code', 'BTECH')->value('id'))->where('code', 'AIML')->value('id');
                }

                return $branch->department_id;
            })->filter()->unique()->values()->all();
            $drive->departments()->sync($targetDepartmentIds);
        }

        $this->command->info('Created ' . count($completeStudents) . ' complete students, ' . count($companies) . ' companies, and ' . count($drives) . ' drives.');
    }
}