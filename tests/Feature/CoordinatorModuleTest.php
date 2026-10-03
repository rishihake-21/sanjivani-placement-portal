<?php

namespace Tests\Feature;

use App\Enums\AdmissionType;
use App\Enums\Role;
use App\Models\Application;
use App\Models\Company;
use App\Models\Coordinator;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\ReuploadRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTpmsFixtures;
use Tests\TestCase;

/** Needs the student-module overlay + CoordinatorServiceProvider registered, on PostgreSQL. */
class CoordinatorModuleTest extends TestCase
{
    use CreatesTpmsFixtures, RefreshDatabase;

    private User $tpo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTpms();
        $this->tpo = User::create(['name' => 'TPO', 'email' => 'tpo@college.test', 'password' => 'Password123', 'role' => Role::TPO]);
    }

    private function makeDrive(array $branches, string $status = 'PUBLISHED'): PlacementDrive
    {
        $company = Company::create(['name' => 'Acme ' . uniqid(), 'created_by' => $this->tpo->id]);
        $drive = PlacementDrive::create([
            'company_id' => $company->id, 'title' => 'Software Engineer', 'graduation_year' => 2029, 'status' => $status,
            'published_at' => $status === 'DRAFT' ? null : now(), 'application_deadline' => $status === 'DRAFT' ? null : now()->addWeek(),
            'created_by' => $this->tpo->id,
        ]);
        $drive->branches()->attach(collect($branches)->pluck('id')->all());

        return $drive;
    }

    private function apply(Student $student, PlacementDrive $drive, string $stage = 'TECHNICAL'): Application
    {
        return Application::create([
            'drive_id' => $drive->id, 'student_id' => $student->id, 'stage' => $stage,
            'applied_at' => now(), 'stage_updated_at' => now(), 'data_snapshot' => ['cgpa' => 8.1],
        ]);
    }

    private function place(Student $student, PlacementDrive $drive, string $status = 'PLACED', float $ctc = 6.5): Placement
    {
        return Placement::create([
            'student_id' => $student->id, 'company_id' => $drive->company_id, 'role_title' => 'SDE', 'ctc_lpa' => $ctc,
            'status' => $status, 'offer_date' => now()->toDateString(),
            'verified_by' => $status === 'PLACED' ? $this->tpo->id : null, 'verified_at' => $status === 'PLACED' ? now() : null,
        ]);
    }

    public function test_dashboard_counts_only_the_coordinators_own_department(): void
    {
        $a = $this->makeStudent('C1');
        $b = $this->makeStudent('C2');
        $m = $this->makeStudent('M1', AdmissionType::REGULAR, 3, $this->mech);
        $drive = $this->makeDrive([$this->cseBranch, $this->mechBranch]);

        $this->apply($a, $drive);
        $this->apply($m, $drive);
        $this->place($a, $drive);
        $this->place($m, $drive);

        Sanctum::actingAs($this->makeCoordinator($this->cse));
        $this->getJson('/api/coordinator/dashboard')->assertOk()
            ->assertJsonPath('data.students.total', 2)
            ->assertJsonPath('data.applications.total', 1)
            ->assertJsonPath('data.applications.in_recruitment', 1)
            ->assertJsonPath('data.placements.placed_students', 1)
            ->assertJsonPath('data.placements.unplaced_students', 1)
            ->assertJsonPath('data.active_drives', 1);
        $this->assertEquals(50, $this->getJson('/api/coordinator/dashboard')->json('data.placements.placement_percentage'));

        $summary = $this->getJson('/api/coordinator/reports/branch-summary')->assertOk()->json('data');
        $this->assertCount(1, $summary);
        $this->assertSame(1, $summary[0]['placed']);
        $this->assertSame(6.5, $summary[0]['highest_ctc_lpa']);
    }

    public function test_opted_out_students_are_excluded_from_the_placement_percentage(): void
    {
        $a = $this->makeStudent('C1');
        $b = $this->makeStudent('C2');
        $b->forceFill(['opted_out_of_placement' => true, 'opt_out_reason' => 'Higher studies'])->save();
        $this->place($a, $this->makeDrive([$this->cseBranch]));

        Sanctum::actingAs($this->makeCoordinator($this->cse));
        $response = $this->getJson('/api/coordinator/dashboard')->assertOk()
            ->assertJsonPath('data.placements.unplaced_students', 0);
        $this->assertEquals(100, $response->json('data.placements.placement_percentage'));
    }

    public function test_drives_are_visible_only_when_published_and_targeting_the_department(): void
    {
        $visible = $this->makeDrive([$this->cseBranch]);
        $draft = $this->makeDrive([$this->cseBranch], 'DRAFT');
        $mechOnly = $this->makeDrive([$this->mechBranch]);

        Sanctum::actingAs($this->makeCoordinator($this->cse));
        $ids = collect($this->getJson('/api/coordinator/drives')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($draft->id));
        $this->assertFalse($ids->contains($mechOnly->id));
        $this->getJson("/api/coordinator/drives/{$mechOnly->id}")->assertNotFound();
        $this->getJson("/api/coordinator/drives/{$visible->id}")->assertOk()->assertJsonPath('data.id', $visible->id);
    }

    public function test_applications_and_progress_are_department_scoped(): void
    {
        $own = $this->makeStudent('C1');
        $other = $this->makeStudent('M1', AdmissionType::REGULAR, 3, $this->mech);
        $drive = $this->makeDrive([$this->cseBranch, $this->mechBranch]);
        $ownApp = $this->apply($own, $drive);
        $otherApp = $this->apply($other, $drive);

        Sanctum::actingAs($this->makeCoordinator($this->cse));
        $this->getJson('/api/coordinator/applications')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/coordinator/applications/{$ownApp->id}")->assertOk()->assertJsonPath('data.data_snapshot.cgpa', 8.1);
        $this->getJson("/api/coordinator/applications/{$otherApp->id}")->assertNotFound();
        $this->getJson("/api/coordinator/students/{$own->id}/progress")->assertOk();
        $this->getJson("/api/coordinator/students/{$other->id}/progress")->assertNotFound();
    }

    public function test_reupload_request_on_a_verified_record_unlocks_it_and_closes_when_the_student_resubmits(): void
    {
        $student = $this->makeStudent('L1', AdmissionType::LATERAL, 3);
        $coordinator = $this->makeCoordinator();

        Sanctum::actingAs($student->user);
        $id = $this->postJson('/api/student/academic-records', $this->diplomaPayload())->json('data.id');
        $this->postJson("/api/student/academic-records/$id/document", ['document' => $this->pdf()]);
        $this->postJson("/api/student/academic-records/$id/submit");

        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/academic-records/$id/approve")->assertOk()->assertJsonPath('data.locked', true);

        $payload = ['subject_type' => 'ACADEMIC_RECORD', 'subject_id' => $id, 'reason' => 'Marksheet scan is cropped, please upload again'];
        $this->postJson("/api/coordinator/students/{$student->id}/reupload-requests", $payload)->assertCreated()->assertJsonPath('data.status', 'OPEN');
        $this->postJson("/api/coordinator/students/{$student->id}/reupload-requests", $payload)->assertStatus(409); // duplicate

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/reupload-requests')->assertOk()->assertJsonPath('data.0.status', 'OPEN');

        // unlocked by the request, so the student can now start a correction
        $revision = $this->patchJson("/api/student/academic-records/$id", ['percentage' => 90.56])->assertOk()->json('data.id');
        $this->postJson("/api/student/academic-records/$revision/document", ['document' => $this->pdf('rescan.pdf')]);
        $this->postJson("/api/student/academic-records/$revision/submit")->assertOk();

        $this->assertSame('FULFILLED', ReuploadRequest::query()->firstOrFail()->status->value);
    }

    public function test_reupload_requests_are_scoped_and_only_for_accepted_items(): void
    {
        $student = $this->makeStudent('L2', AdmissionType::LATERAL, 3);
        $mechCoordinator = $this->makeCoordinator($this->mech);

        Sanctum::actingAs($student->user);
        $id = $this->postJson('/api/student/academic-records', $this->diplomaPayload())->json('data.id');
        $payload = ['subject_type' => 'ACADEMIC_RECORD', 'subject_id' => $id, 'reason' => 'Please upload a clearer copy'];

        Sanctum::actingAs($mechCoordinator);
        $this->postJson("/api/coordinator/students/{$student->id}/reupload-requests", $payload)->assertNotFound(); // other department

        Sanctum::actingAs($this->makeCoordinator($this->cse));
        $this->postJson("/api/coordinator/students/{$student->id}/reupload-requests", $payload)->assertStatus(409); // still a draft
        $this->postJson("/api/coordinator/students/{$student->id}/reupload-requests", ['reason' => 'short'])->assertStatus(422);
    }

    public function test_admin_manages_coordinators_one_active_per_department(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@college.test', 'password' => 'Password123', 'role' => Role::SYSTEM_ADMIN]);
        Sanctum::actingAs($admin);

        $payload = [
            'department_id' => $this->cse->id, 'name' => 'Dr. Rao', 'email' => 'rao@college.test',
            'password' => 'LongPassword123', 'employee_id' => 'E-101', 'phone' => '9876543210',
        ];

        $id = $this->postJson('/api/admin/coordinators', $payload)->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');
        $this->postJson('/api/admin/coordinators', array_merge($payload, ['email' => 'second@college.test', 'employee_id' => 'E-102']))->assertStatus(409);

        $coordinatorUser = Coordinator::findOrFail($id)->user;
        $this->postJson("/api/admin/coordinators/$id/deactivate", ['reason' => 'Retired'])->assertOk()->assertJsonPath('data.is_active', false);

        Sanctum::actingAs($coordinatorUser->fresh());
        $this->getJson('/api/coordinator/dashboard')->assertForbidden(); // inactive accounts are refused

        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/coordinators', array_merge($payload, ['email' => 'second@college.test', 'employee_id' => 'E-102']))->assertCreated();
        $this->postJson("/api/admin/coordinators/$id/reactivate")->assertStatus(409); // the new one holds the seat
    }

    public function test_admin_cannot_reach_coordinator_or_student_data(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@college.test', 'password' => 'Password123', 'role' => Role::SYSTEM_ADMIN]);
        $student = $this->makeStudent('C1');

        Sanctum::actingAs($admin);
        $this->getJson('/api/coordinator/dashboard')->assertForbidden();
        $this->getJson("/api/tpo/students/{$student->id}")->assertForbidden();

        Sanctum::actingAs($this->makeCoordinator());
        $this->getJson('/api/admin/coordinators')->assertForbidden();
    }

    public function test_csv_export_is_scoped_and_neutralises_formula_injection(): void
    {
        $student = $this->makeStudent('C1');
        $student->forceFill(['full_name' => '=HYPERLINK("http://evil.test")'])->save();
        $this->makeStudent('M1', AdmissionType::REGULAR, 3, $this->mech);

        Sanctum::actingAs($this->makeCoordinator());
        $csv = $this->get('/api/coordinator/reports/students.csv')->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('C1', $csv);
        $this->assertStringNotContainsString('M1', $csv);
        $this->assertStringContainsString('UNPLACED', $csv);
    }

    public function test_database_refuses_an_unverified_placed_record(): void
    {
        $student = $this->makeStudent('C1');
        $drive = $this->makeDrive([$this->cseBranch]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::transaction(fn () => $this->place($student, $drive, 'OFFERED')->forceFill(['status' => 'PLACED'])->save());
    }
}
