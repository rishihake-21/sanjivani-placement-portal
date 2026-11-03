<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Recruiter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Idempotent: one demo recruiter login per company that already exists (e.g. infosys.recruiter@tpms.local),
 * so the recruiter API can be tried straight after seeding. Real recruiters are created by the TPO via
 * POST /api/tpo/recruiters. Uses the same TPMS_SEED_PASSWORD as the staff accounts.
 */
class RecruiterSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('TPMS_SEED_PASSWORD') ?: throw new \RuntimeException('Set TPMS_SEED_PASSWORD before seeding.');
        $tpo = User::query()->where('role', Role::TPO->value)->firstOrFail();

        Company::query()->where('is_active', true)->orderBy('id')->each(function (Company $company) use ($password, $tpo) {
            $user = User::firstOrCreate(
                ['email' => Str::slug(Str::before($company->name, ' ')) . '.recruiter@tpms.local'],
                ['name' => $company->name . ' Recruiter', 'password' => $password, 'role' => Role::RECRUITER, 'is_active' => true],
            );

            Recruiter::firstOrCreate(['user_id' => $user->id], [
                'company_id' => $company->id, 'designation' => 'Campus Hiring', 'is_active' => true,
                'active_from' => now()->toDateString(), 'created_by' => $tpo->id,
            ]);
        });
    }
}
