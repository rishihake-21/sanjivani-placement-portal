<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\MasterListEntry;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registration succeeds only for IDs present (and unclaimed) in the imported student master list.
 * Department, branch, admission type and semester come from the master list, never from the form.
 */
class RegistrationService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $universityId = strtoupper(trim($data['university_id']));
            $email = strtolower(trim($data['email']));

            $entry = MasterListEntry::query()->where('university_id', $universityId)->lockForUpdate()->first();

            // One message for every failure so IDs and emails cannot be probed.
            $refuse = fn () => ValidationException::withMessages([
                'university_id' => ['This university ID cannot be registered. Check the ID and email, or contact the T&P office.'],
            ]);

            if ($entry === null || $entry->isClaimed()) {
                throw $refuse();
            }
            if ($entry->institutional_email !== null && strtolower($entry->institutional_email) !== $email) {
                throw $refuse();
            }

            $user = User::create([
                'name' => $entry->full_name,
                'email' => $email,
                'password' => $data['password'],
                'role' => Role::STUDENT,
                'is_active' => true,
            ]);

            $student = new Student();
            $student->forceFill([
                'user_id' => $user->id,
                'master_list_id' => $entry->id,
                'university_id' => $entry->university_id,
                'full_name' => $entry->full_name,
                'department_id' => $entry->department_id,
                'branch_id' => $entry->branch_id,
                'admission_type' => $entry->admission_type,
                'admission_year' => $entry->admission_year,
                'graduation_year' => $entry->graduation_year,
                'current_semester' => $entry->current_semester,
                'personal_email' => $email,
            ])->save();

            $entry->forceFill(['claimed_at' => now()])->save();

            $this->audit->record('student.registered', $student, $student->id, null, [
                'university_id' => $student->university_id,
            ], $user);

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
