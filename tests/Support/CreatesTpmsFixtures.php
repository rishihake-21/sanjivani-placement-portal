<?php

namespace Tests\Support;

use App\Enums\AdmissionType;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Department;
use App\Models\MasterListEntry;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait CreatesTpmsFixtures
{
    protected Department $cse;
    protected Department $mech;
    protected Branch $cseBranch;
    protected Branch $mechBranch;

    protected function setUpTpms(): void
    {
        config(['tpms.documents.disk' => 'tpms_test']);
        config(['filesystems.disks.tpms_test' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/tpms_test')]]);
        Storage::fake('tpms_test');

        $this->cse = Department::create(['name' => 'Computer Science', 'code' => 'CSE']);
        $this->mech = Department::create(['name' => 'Mechanical', 'code' => 'ME']);
        $this->cseBranch = Branch::create(['department_id' => $this->cse->id, 'name' => 'Computer Engineering', 'code' => 'CSE-B']);
        $this->mechBranch = Branch::create(['department_id' => $this->mech->id, 'name' => 'Mechanical', 'code' => 'ME-B']);
    }

    protected function makeStudent(
        string $universityId = 'S001',
        AdmissionType $type = AdmissionType::REGULAR,
        int $semester = 3,
        ?Department $department = null,
    ): Student {
        $department ??= $this->cse;
        $branch = $department->id === $this->cse->id ? $this->cseBranch : $this->mechBranch;

        $user = User::create([
            'name' => "Student $universityId", 'email' => strtolower($universityId) . '@college.test',
            'password' => 'Password123', 'role' => Role::STUDENT,
        ]);
        $entry = MasterListEntry::create([
            'university_id' => $universityId, 'full_name' => "Student $universityId",
            'institutional_email' => $user->email, 'department_id' => $department->id, 'branch_id' => $branch->id,
            'admission_type' => $type, 'admission_year' => 2026, 'graduation_year' => 2029,
            'current_semester' => $semester, 'claimed_at' => now(),
        ]);

        $student = new Student();
        $student->forceFill([
            'user_id' => $user->id, 'master_list_id' => $entry->id, 'university_id' => $universityId,
            'full_name' => $entry->full_name, 'department_id' => $department->id, 'branch_id' => $branch->id,
            'admission_type' => $type, 'admission_year' => 2026, 'graduation_year' => 2029, 'current_semester' => $semester,
        ])->save();

        return $student->load('user');
    }

    protected function makeCoordinator(?Department $department = null): User
    {
        $department ??= $this->cse;

        return User::create([
            'name' => 'Coordinator ' . $department->code, 'email' => strtolower($department->code) . '.coord@college.test',
            'password' => 'Password123', 'role' => Role::TP_COORDINATOR, 'department_id' => $department->id,
        ]);
    }

    protected function pdf(string $name = 'marksheet.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    /** Diploma record payload (percentage = 1630/1800). */
    protected function diplomaPayload(array $override = []): array
    {
        return array_merge([
            'level' => 'DIPLOMA', 'institution_name' => 'Govt Polytechnic', 'board_or_university' => 'MSBTE',
            'passing_year' => 2026, 'percentage' => 90.56, 'obtained_marks' => 1630, 'total_marks' => 1800,
        ], $override);
    }
}
