<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Coordinator;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * System Admin manages coordinator ACCOUNTS (technical role). One active coordinator per department;
 * replacing a coordinator = deactivate the old one, then create the new one.
 */
class CoordinatorAccountService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function create(User $admin, array $data): Coordinator
    {
        try {
            return DB::transaction(function () use ($admin, $data) {
                $department = Department::query()->lockForUpdate()->findOrFail($data['department_id']);

                abort_if(
                    Coordinator::query()->where('department_id', $department->id)->where('is_active', true)->exists(),
                    409,
                    $department->code . ' already has an active coordinator. Deactivate them first.'
                );

                $user = User::create([
                    'name' => $data['name'],
                    'email' => strtolower($data['email']),
                    'password' => $data['password'],
                    'role' => Role::TP_COORDINATOR,
                    'department_id' => $department->id,
                    'is_active' => true,
                ]);

                $coordinator = Coordinator::create([
                    'user_id' => $user->id,
                    'department_id' => $department->id,
                    'employee_id' => $data['employee_id'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'designation' => $data['designation'] ?? null,
                    'is_active' => true,
                    'active_from' => now()->toDateString(),
                ]);

                $this->audit->record('coordinator.created', $coordinator, null, null, [
                    'user_id' => $user->id, 'department_id' => $department->id, 'email' => $user->email,
                ], $admin);

                return $coordinator->load('user', 'department');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'The email or employee ID is already in use, or the department already has an active coordinator.');
        }
    }

    public function update(User $admin, Coordinator $coordinator, array $data): Coordinator
    {
        try {
            return DB::transaction(function () use ($admin, $coordinator, $data) {
                $coordinator = Coordinator::query()->whereKey($coordinator->id)->lockForUpdate()->firstOrFail();
                $user = $coordinator->user;
                $old = [];

                foreach (['phone', 'designation', 'employee_id'] as $field) {
                    if (array_key_exists($field, $data) && $coordinator->{$field} !== $data[$field]) {
                        $old[$field] = $coordinator->{$field};
                        $coordinator->{$field} = $data[$field];
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

                $coordinator->save();
                $user->save();

                if ($old !== [] || $passwordReset) {
                    $this->audit->record('coordinator.updated', $coordinator, null, $old, array_diff_key($data, ['password' => 1]), $admin, [
                        'password_reset' => $passwordReset,
                    ]);
                }

                return $coordinator->load('user', 'department');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'The email or employee ID is already in use.');
        }
    }

    public function deactivate(User $admin, Coordinator $coordinator, ?string $reason = null): Coordinator
    {
        return DB::transaction(function () use ($admin, $coordinator, $reason) {
            $coordinator = Coordinator::query()->whereKey($coordinator->id)->lockForUpdate()->firstOrFail();
            abort_unless($coordinator->is_active, 409, 'This coordinator is already inactive.');

            $until = now()->toDateString();
            if ($coordinator->active_from->toDateString() > $until) {
                $until = $coordinator->active_from->toDateString();
            }

            $coordinator->forceFill(['is_active' => false, 'active_until' => $until])->save();
            $coordinator->user->forceFill(['is_active' => false])->save();
            $coordinator->user->tokens()->delete();

            $this->audit->record('coordinator.deactivated', $coordinator, null, ['is_active' => true], ['is_active' => false], $admin, ['reason' => $reason]);

            return $coordinator->load('user', 'department');
        });
    }

    public function reactivate(User $admin, Coordinator $coordinator): Coordinator
    {
        try {
            return DB::transaction(function () use ($admin, $coordinator) {
                $coordinator = Coordinator::query()->whereKey($coordinator->id)->lockForUpdate()->firstOrFail();
                abort_if($coordinator->is_active, 409, 'This coordinator is already active.');
                abort_if(
                    Coordinator::query()->where('department_id', $coordinator->department_id)->where('is_active', true)->exists(),
                    409,
                    'This department already has an active coordinator.'
                );

                $coordinator->forceFill(['is_active' => true, 'active_until' => null, 'active_from' => now()->toDateString()])->save();
                $coordinator->user->forceFill(['is_active' => true])->save();

                $this->audit->record('coordinator.reactivated', $coordinator, null, ['is_active' => false], ['is_active' => true], $admin);

                return $coordinator->load('user', 'department');
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'This department already has an active coordinator.');
        }
    }
}
