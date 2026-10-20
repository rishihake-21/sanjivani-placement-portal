<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Division;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTpmsFixtures;
use Tests\TestCase;

class AdminAcademicStructureTest extends TestCase
{
    use CreatesTpmsFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTpms();

        $this->admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin-structure@college.test',
            'password' => 'Password123',
            'role' => Role::SYSTEM_ADMIN,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_department_codes_are_unique_per_programme_not_globally(): void
    {
        // The hierarchy migration pre-seeds the standard programme catalogue.
        $integrated = Program::query()->where('code', 'IBTECH')->firstOrFail();

        $this->postJson("/api/admin/programs/{$integrated->id}/departments", [
            'name' => 'Computer Science',
            'code' => 'CSE',
        ])->assertCreated()
            ->assertJsonPath('data.program.code', 'IBTECH')
            ->assertJsonPath('data.code', 'CSE');

        $this->postJson("/api/admin/programs/{$integrated->id}/departments", [
            'name' => 'Computer Science and Computing',
            'code' => 'CSE',
        ])->assertUnprocessable();

        $this->assertSame(2, Department::query()->where('code', 'CSE')->count());
    }

    public function test_division_codes_are_unique_within_each_department(): void
    {
        $cseDivision = $this->postJson("/api/admin/departments/{$this->cse->id}/divisions", [
            'name' => 'SY CSE-A',
            'code' => 'COMMON-A',
        ])->assertCreated()->json('data.id');

        $mechDivision = $this->postJson("/api/admin/departments/{$this->mech->id}/divisions", [
            'name' => 'SY Mechanical-A',
            'code' => 'COMMON-A',
        ])->assertCreated()->json('data.id');

        $this->assertNotSame($cseDivision, $mechDivision);
        $this->postJson("/api/admin/departments/{$this->cse->id}/divisions", [
            'name' => 'Another CSE A',
            'code' => 'COMMON-A',
        ])->assertUnprocessable();

        $this->assertSame(2, Division::query()->where('code', 'COMMON-A')->count());
    }
}
