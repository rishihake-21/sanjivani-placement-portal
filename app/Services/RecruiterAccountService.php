<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Recruiter;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The TPO manages recruiter ACCOUNTS: who may look at a company's applicants. A recruiter belongs to one
 * company for life; to move them, deactivate and create a new account. Deactivating signs them out
 * everywhere at once.
 */
class RecruiterAccountService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function create(User $tpo, array $data): Recruiter
    {
        try {
            return DB::transaction(function () use ($tpo, $data) {
                $company = Company::query()->lockForUpdate()->findOrFail($data['company_id']);
                abort_unless($company->is_active, 409, 'This company is inactive. Reactivate it before adding recruiters.');

                $user = User::create([
                    'name' => $data['name'],
                    'email' => strtolower($data['email']),
                    'password' => $data['password'],
                    'role' => Role::RECRUITER,
                    'is_active' => true,
                ]);

                $recruiter = Recruiter::create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'designation' => $data['designation'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'is_active' => true,
                    'active_from' => now()->toDateString(),
                    'created_by' => $tpo->id,
                ]);

                $this->audit->record('recruiter.created', $recruiter, null, null, [
                    'user_id' => $user->id, 'company_id' => $company->id, 'email' => $user->email,
                ], $tpo);

                return $recruiter->load('user', 'company');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'That email is already in use.');
        }
    }

    public function update(User $tpo, Recruiter $recruiter, array $data): Recruiter
    {
        try {
            return DB::transaction(function () use ($tpo, $recruiter, $data) {
                $recruiter = Recruiter::query()->whereKey($recruiter->id)->lockForUpdate()->firstOrFail();
                $user = $recruiter->user;
                $old = [];

                foreach (['phone', 'designation'] as $field) {
                    if (array_key_exists($field, $data) && $recruiter->{$field} !== $data[$field]) {
                        $old[$field] = $recruiter->{$field};
                        $recruiter->{$field} = $data[$field];
                    }
                }
                foreach (['name', 'email'] as $field) {
                    if (array_key_exists($field, $data) && $user->{$field} !== $data[$field]) {
                        $old[$field] = $user->{$field};
                        $user->{$field} = $field === 'email' ? strtolower($data[$field]) : $data[$field];
                    }
                }

                $passwordReset = ! empty($data['password']);
                if ($passwordReset) {
                    $user->password = $data['password'];
                    $user->tokens()->delete(); // force re-login everywhere
                }

                $recruiter->save();
                $user->save();

                if ($old !== [] || $passwordReset) {
                    $this->audit->record('recruiter.updated', $recruiter, null, $old, array_diff_key($data, ['password' => 1]), $tpo, [
                        'password_reset' => $passwordReset,
                    ]);
                }

                return $recruiter->load('user', 'company');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'That email is already in use.');
        }
    }

    public function deactivate(User $tpo, Recruiter $recruiter, ?string $reason = null): Recruiter
    {
        return DB::transaction(function () use ($tpo, $recruiter, $reason) {
            $recruiter = Recruiter::query()->whereKey($recruiter->id)->lockForUpdate()->firstOrFail();
            abort_unless($recruiter->is_active, 409, 'This recruiter is already inactive.');

            $until = max(now()->toDateString(), $recruiter->active_from->toDateString());

            $recruiter->forceFill(['is_active' => false, 'active_until' => $until])->save();
            $recruiter->user->forceFill(['is_active' => false])->save();
            $recruiter->user->tokens()->delete();

            $this->audit->record('recruiter.deactivated', $recruiter, null, ['is_active' => true], ['is_active' => false], $tpo, ['reason' => $reason]);

            return $recruiter->load('user', 'company');
        });
    }

    public function reactivate(User $tpo, Recruiter $recruiter): Recruiter
    {
        return DB::transaction(function () use ($tpo, $recruiter) {
            $recruiter = Recruiter::query()->whereKey($recruiter->id)->lockForUpdate()->firstOrFail();
            abort_if($recruiter->is_active, 409, 'This recruiter is already active.');
            abort_unless($recruiter->company->is_active, 409, 'This company is inactive. Reactivate it first.');

            $recruiter->forceFill(['is_active' => true, 'active_until' => null, 'active_from' => now()->toDateString()])->save();
            $recruiter->user->forceFill(['is_active' => true])->save();

            $this->audit->record('recruiter.reactivated', $recruiter, null, ['is_active' => false], ['is_active' => true], $tpo);

            return $recruiter->load('user', 'company');
        });
    }
}
