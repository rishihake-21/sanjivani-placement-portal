<?php

namespace Tests\Feature;

use App\Enums\AdmissionType;
use App\Models\AcademicRecord;
use App\Models\AuditLog;
use App\Models\MasterListEntry;
use App\Services\VerifiedAcademicProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTpmsFixtures;
use Tests\TestCase;

/** Needs PostgreSQL (partial indexes, CHECK constraints, trigger). Point phpunit.xml at a pgsql test DB. */
class StudentModuleTest extends TestCase
{
    use CreatesTpmsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTpms();
    }

    public function test_registration_requires_an_unclaimed_master_list_entry(): void
    {
        MasterListEntry::create([
            'university_id' => 'S900', 'full_name' => 'Asha Patil', 'institutional_email' => 'asha@college.test',
            'department_id' => $this->cse->id, 'branch_id' => $this->cseBranch->id,
            'admission_type' => AdmissionType::LATERAL, 'admission_year' => 2026, 'graduation_year' => 2029, 'current_semester' => 3,
        ]);

        $payload = ['university_id' => 's900', 'email' => 'asha@college.test', 'password' => 'Password123', 'password_confirmation' => 'Password123'];

        $this->postJson('/api/auth/register', array_merge($payload, ['university_id' => 'NOPE']))->assertStatus(422);
        $this->postJson('/api/auth/register', array_merge($payload, ['email' => 'other@college.test']))->assertStatus(422);
        $this->postJson('/api/auth/register', $payload)->assertCreated();
        $this->postJson('/api/auth/register', array_merge($payload, ['email' => 'again@college.test']))->assertStatus(422); // already claimed

        $this->assertDatabaseHas('students', ['university_id' => 'S900', 'admission_type' => 'LATERAL', 'department_id' => $this->cse->id]);
    }

    public function test_lateral_student_rules_for_diploma_and_semesters(): void
    {
        $lateral = $this->makeStudent('L001', AdmissionType::LATERAL, 3);
        $regular = $this->makeStudent('R001', AdmissionType::REGULAR, 3);

        Sanctum::actingAs($regular->user);
        $this->postJson('/api/student/academic-records', $this->diplomaPayload())->assertStatus(422); // diploma = lateral only

        Sanctum::actingAs($lateral->user);
        $this->postJson('/api/student/academic-records', ['level' => 'DEGREE_SEM', 'semester' => 1, 'sgpa' => 8])->assertStatus(422); // before sem 3
        $this->postJson('/api/student/academic-records', ['level' => 'DEGREE_SEM', 'semester' => 4, 'sgpa' => 8])->assertStatus(422); // future
        $this->postJson('/api/student/academic-records', $this->diplomaPayload())->assertCreated();
        $this->postJson('/api/student/academic-records', $this->diplomaPayload())->assertStatus(409); // duplicate key
    }

    public function test_submit_needs_a_document_and_review_is_department_scoped(): void
    {
        $student = $this->makeStudent('L002', AdmissionType::LATERAL, 3);
        $own = $this->makeCoordinator($this->cse);
        $other = $this->makeCoordinator($this->mech);

        Sanctum::actingAs($student->user);
        $id = $this->postJson('/api/student/academic-records', $this->diplomaPayload())->json('data.id');
        $this->postJson("/api/student/academic-records/$id/submit")->assertStatus(422); // no marksheet yet
        $this->postJson("/api/student/academic-records/$id/document", ['document' => $this->pdf()])->assertOk();
        $this->postJson("/api/student/academic-records/$id/submit")->assertOk()->assertJsonPath('data.status', 'PENDING');

        Sanctum::actingAs($other);
        $this->postJson("/api/coordinator/academic-records/$id/approve")->assertNotFound(); // other department

        Sanctum::actingAs($own);
        $this->postJson("/api/coordinator/academic-records/$id/approve")->assertOk()->assertJsonPath('data.status', 'VERIFIED');

        $snapshot = app(VerifiedAcademicProfileService::class)->forStudent($student->fresh());
        $this->assertSame(90.56, $snapshot->diplomaPercentage);
        $this->assertContains('10th', $snapshot->missing); // 10th is still unverified
    }

    public function test_unverified_values_never_reach_the_verified_snapshot(): void
    {
        $student = $this->makeStudent('L003', AdmissionType::LATERAL, 3);
        $coordinator = $this->makeCoordinator();

        Sanctum::actingAs($student->user);
        $id = $this->postJson('/api/student/academic-records', $this->diplomaPayload(['percentage' => 80]))->json('data.id');
        $this->postJson("/api/student/academic-records/$id/document", ['document' => $this->pdf()]);
        $this->postJson("/api/student/academic-records/$id/submit");

        $this->assertNull(app(VerifiedAcademicProfileService::class)->forStudent($student->fresh())->diplomaPercentage); // pending

        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/academic-records/$id/approve")->assertOk();

        // Editing the verified record starts a revision; the old verified value stays in force.
        Sanctum::actingAs($student->user);
        $this->patchJson("/api/student/academic-records/$id", ['percentage' => 95])->assertOk();
        $this->assertSame(80.0, app(VerifiedAcademicProfileService::class)->forStudent($student->fresh())->diplomaPercentage);
    }

    public function test_verified_past_term_is_locked_until_coordinator_unlocks(): void
    {
        $student = $this->makeStudent('L004', AdmissionType::LATERAL, 3);
        $coordinator = $this->makeCoordinator();

        Sanctum::actingAs($student->user);
        $id = $this->postJson('/api/student/academic-records', $this->diplomaPayload())->json('data.id');
        $this->postJson("/api/student/academic-records/$id/document", ['document' => $this->pdf()]);
        $this->postJson("/api/student/academic-records/$id/submit");

        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/academic-records/$id/approve")->assertOk()->assertJsonPath('data.locked', true);

        Sanctum::actingAs($student->user);
        $this->patchJson("/api/student/academic-records/$id", ['percentage' => 91])->assertStatus(423);

        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/academic-records/$id/unlock", ['reason' => 'Typo in marks'])->assertOk()->assertJsonPath('data.locked', false);

        Sanctum::actingAs($student->user);
        $revisionId = $this->patchJson("/api/student/academic-records/$id", ['percentage' => 91])->assertOk()->json('data.id');
        $this->assertNotSame($id, $revisionId);
        $this->assertSame('VERIFIED', AcademicRecord::find($id)->status->value);
        $this->assertSame('DRAFT', AcademicRecord::find($revisionId)->status->value);
    }

    public function test_approving_a_revision_supersedes_the_old_row_and_rejection_keeps_it(): void
    {
        $student = $this->makeStudent('L005', AdmissionType::LATERAL, 3);
        $coordinator = $this->makeCoordinator();

        Sanctum::actingAs($student->user);
        $id = $this->postJson('/api/student/academic-records', $this->diplomaPayload(['percentage' => 80]))->json('data.id');
        $this->postJson("/api/student/academic-records/$id/document", ['document' => $this->pdf()]);
        $this->postJson("/api/student/academic-records/$id/submit");
        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/academic-records/$id/approve");
        $this->postJson("/api/coordinator/academic-records/$id/unlock", ['reason' => 'Correction approved']);

        Sanctum::actingAs($student->user);
        $rev = $this->patchJson("/api/student/academic-records/$id", ['percentage' => 85])->json('data.id');
        $this->postJson("/api/student/academic-records/$rev/document", ['document' => $this->pdf('new.pdf')]);
        $this->postJson("/api/student/academic-records/$rev/submit");

        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/academic-records/$rev/reject", ['reason_code' => 'DATA_MISMATCH'])->assertOk();
        $this->assertSame('VERIFIED', AcademicRecord::find($id)->status->value); // old value still in force

        Sanctum::actingAs($student->user);
        $this->patchJson("/api/student/academic-records/$rev", ['percentage' => 84])->assertOk();
        $this->postJson("/api/student/academic-records/$rev/submit")->assertOk(); // DATA_MISMATCH keeps the document usable

        Sanctum::actingAs($coordinator);
        $this->postJson("/api/coordinator/academic-records/$rev/approve")->assertOk();
        $this->assertSame('SUPERSEDED', AcademicRecord::find($id)->status->value);
        $this->assertSame(84.0, app(VerifiedAcademicProfileService::class)->forStudent($student->fresh())->diplomaPercentage);
    }

    public function test_documents_are_only_downloadable_by_owner_department_coordinator_and_tpo(): void
    {
        $student = $this->makeStudent('S100');
        Sanctum::actingAs($student->user);
        $uuid = $this->postJson('/api/student/documents', ['document_type' => 'RESUME', 'document' => $this->pdf('cv.pdf')])
            ->assertCreated()->json('data.uuid');

        $this->getJson("/api/documents/$uuid/download")->assertOk();

        Sanctum::actingAs($this->makeStudent('S101')->user);
        $this->getJson("/api/documents/$uuid/download")->assertForbidden();

        Sanctum::actingAs($this->makeCoordinator($this->mech));
        $this->getJson("/api/documents/$uuid/download")->assertForbidden();

        Sanctum::actingAs($this->makeCoordinator($this->cse));
        $this->getJson("/api/documents/$uuid/download")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.viewed', 'student_id' => $student->id]);
    }

    public function test_file_type_is_checked_and_audit_log_is_append_only(): void
    {
        $student = $this->makeStudent('S200');
        Sanctum::actingAs($student->user);

        $this->postJson('/api/student/documents', [
            'document_type' => 'RESUME',
            'document' => \Illuminate\Http\UploadedFile::fake()->create('virus.exe', 50, 'application/x-msdownload'),
        ])->assertStatus(422);

        $this->postJson('/api/student/documents', ['document_type' => 'RESUME', 'document' => $this->pdf()])->assertCreated();

        $log = AuditLog::query()->firstOrFail();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']);
    }
}
