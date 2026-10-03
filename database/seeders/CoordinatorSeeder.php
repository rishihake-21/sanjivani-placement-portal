<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Coordinator;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Idempotent: gives every tp_coordinator user a `coordinators` row. Call it from DatabaseSeeder after the student-module seeding. */
class CoordinatorSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->where('role', Role::TP_COORDINATOR->value)->whereNotIn('id', Coordinator::query()->select('user_id'))->each(function (User $user) {
            Coordinator::create([
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'is_active' => $user->is_active,
                'active_from' => now()->toDateString(),
                'active_until' => $user->is_active ? null : now()->toDateString(),
            ]);
        });
    }
}
