<?php

namespace Tests\Feature;

use App\Enums\AcademicLevel;
use App\Enums\AdmissionType;
use App\Enums\Role;
use App\Jobs\NotifyEligibleStudents;
use App\Models\AcademicRecord;
use App\Models\Application;
use App\Models\Company;
use App\Models\Document;
use App\Models\DriveEligibility;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\Student;
use App\Models\User;
use App\Services\EligibilityEngine;
use App\Support\EligibilityResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTpmsFixtures;
use Tests\TestCase;

/** Needs the student + coordinator overlays and TpoServiceProvider registered, on PostgreSQL. */
class TpoModuleTest extends TestCase
{
    use CreatesTpmsFixtures, RefreshDatabase;

    private User $tpo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTpms();
        $this->tpo = User::create(['name' => 'TPO', 'email' => 'tpo@college.test', 'password' => 'Password123', 'role' => Role::TPO]);
    }

    // ---- fixtures ---------------------------------------------------------------------------------

    private function verified(Student $s, string $level, ?int $semester, array $values): AcademicRecord
    {
        $doc = Document::create([
            'uuid' => (string) Str::uuid(), 'student_id' => $s->id, 'document_type' => AcademicLevel::from($level)->documentType()->value,
            'version' => 1, 'disk' => 'tpms_test', 'path' => 't/' . Str::uuid() . '.pdf', 'original_name' => 'm.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64), 'status' => 'APPROVED',
            'uploaded_by' => $s->user_id, 'reviewed_by' => $this->tpo->id, 'reviewed_at' => now(),
        ]);

        return AcademicRecord::create(array_merge([
            'student_id' => $s->id, 'level' => $level, 'semester' => $semester, 'status' => 'VERIFIED', 'version' => 1,
            'document_id' => $doc->id, 'reviewed_by' => $this->tpo->id, 'reviewed_at' => now(), 'locked_at' => now(),
        ], $values));
    }

    /** Everything a student needs for can_apply: identity, approved resume, all required academics verified. */
    private function makeReady(Student $s, float $sgpa = 8.0, int $activeBacklogs = 0, float $percentage = 80): Student
    {
        $s->forceFill(['identity_confirmed_at' => now(), 'date_of_birth' => '2005-01-01'])->save();
        Document::create([
            'uuid' => (string) Str::uuid(), 'student_id' => $s->id, 'document_type' => 'RESUME', 'version' => 1, 'disk' => 'tpms_test',
            'path' => 'r/' . Str::uuid() . '.pdf', 'original_name' => 'cv.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10,
            'sha256' => str_repeat('b', 64), 'status' => 'APPROVED', 'is_primary' => true, 'uploaded_by' => $s->user_id,
            'reviewed_by' => $this->tpo->id, 'reviewed_at' => now(),
        ]);

        foreach ($s->requiredAcademicKeys() as [$level, $semester]) {
            $values = $level === AcademicLevel::DEGREE_SEM
                ? ['sgpa' => $sgpa, 'cgpa' => $sgpa, 'backlogs_in_term' => 0, 'active_backlogs_after_term' => $activeBacklogs]
                : ['percentage' => $percentage];
            $this->verified($s, $level->value, $semester, $values);
        }

        return $s->fresh();
    }

    /** Creates a PUBLISHED drive directly (not through the API). */
    private function drive(array $eligibility = ['min_cgpa' => 7, 'max_active_backlogs' => 0], array $override = [], ?array $branches = null): PlacementDrive
    {
        $company = Company::create(['name' => 'Acme ' . uniqid(), 'created_by' => $this->tpo->id]);
        $drive = PlacementDrive::create(array_merge([
            'company_id' => $company->id, 'title' => 'Software Engineer', 'graduation_year' => 2029, 'ctc_lpa' => 6,
            'status' => 'PUBLISHED', 'published_at' => now(), 'application_deadline' => now()->addWeek(), 'created_by' => $this->tpo->id,
        ], $override));
        DriveEligibility::create(['drive_id' => $drive->id] + $eligibility);
        $drive->branches()->attach(collect($branches ?? [$this->cseBranch])->pluck('id')->all());

        return $drive;
    }

    private function engine(): EligibilityEngine
    {
        return app(EligibilityEngine::class);
    }

    // ---- companies and drive lifecycle --------------------------------------------------------------

    public function test_company_names_are_unique_case_insensitively_and_drive_lifecycle_locks_criteria(): void
    {
        Sanctum::actingAs($this->tpo);

        $companyId = $this->postJson('/api/tpo/companies', ['name' => 'Juspay'])->assertCreated()->json('data.id');
        $this->postJson('/api/tpo/companies', ['name' => 'JUSPAY'])->assertStatus(422);

        $payload = [
            'company_id' => $companyId, 'title' => 'Backend Engineer', 'graduation_year' => 2029, 'ctc_lpa' => 12, 'ctc_max_lpa' => 18,
            'application_deadline' => now()->addWeek()->toDateTimeString(),
            'eligibility' => ['min_cgpa' => 7.5, 'max_active_backlogs' => 0],
            'additional_requirements' => 'Must be willing to relocate.',
        ];

        // a draft without target branches cannot be published
        $noBranches = $this->postJson('/api/tpo/drives', $payload)->assertCreated()->assertJsonPath('data.status', 'DRAFT')->json('data.id');
        $this->postJson("/api/tpo/drives/$noBranches/publish")->assertStatus(422);

        $id = $this->postJson('/api/tpo/drives', $payload + ['branch_ids' => [$this->cseBranch->id]])->assertCreated()->json('data.id');
        $this->patchJson("/api/tpo/drives/$id", ['eligibility' => ['min_cgpa' => 6]])->assertOk(); // draft: free to edit

        $this->postJson("/api/tpo/drives/$id/publish")->assertOk()->assertJsonPath('data.status', 'PUBLISHED');
        $this->patchJson("/api/tpo/drives/$id", ['eligibility' => ['min_cgpa' => 5]])->assertStatus(422);            // criteria locked
        $this->patchJson("/api/tpo/drives/$id", ['title' => 'Other'])->assertStatus(422);
        $this->patchJson("/api/tpo/drives/$id", ['application_deadline' => now()->addDays(10)->toDateTimeString()])->assertOk();
        $this->deleteJson("/api/tpo/drives/$id")->assertStatus(409);                                                  // published drives are cancelled, not deleted

        $this->postJson("/api/tpo/drives/$id/cancel", ['reason' => 'Company postponed hiring'])->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->deleteJson("/api/tpo/drives/$noBranches")->assertNoContent();                                           // never-published draft
    }

    public function test_publishing_notifies_eligible_and_pending_students_only(): void
    {
        $eligible = $this->makeReady($this->makeStudent('E1'));
        $pending = $this->makeStudent('P1');
        $ineligible = $this->makeReady($this->makeStudent('N1'), sgpa: 5.0);
        $drive = $this->drive();

        (new NotifyEligibleStudents($drive->id))->handle($this->engine());

        $this->assertSame(1, $eligible->user->notifications()->count());
        $this->assertSame(1, $pending->user->notifications()->count());
        $this->assertSame(0, $ineligible->user->notifications()->count());
    }

    // ---- eligibility engine ----------------------------------------------------------------------------

    public function test_engine_uses_verified_data_and_explains_every_outcome(): void
    {
        $drive = $this->drive(['min_cgpa' => 7, 'max_active_backlogs' => 0]);

        $good = $this->makeReady($this->makeStudent('A1'));
        $lowCgpa = $this->makeReady($this->makeStudent('A2'), sgpa: 6.5);
        $backlog = $this->makeReady($this->makeStudent('A3'), sgpa: 8, activeBacklogs: 1);
        $unverified = $this->makeStudent('A4');
        $optedOut = $this->makeReady($this->makeStudent('A5'));
        $optedOut->forceFill(['opted_out_of_placement' => true, 'opt_out_reason' => 'Higher studies'])->save();
        $otherBranch = $this->makeReady($this->makeStudent('M1', AdmissionType::REGULAR, 3, $this->mech));

        $this->assertSame(EligibilityResult::ELIGIBLE, $this->engine()->evaluate($good, $drive)->status);

        $r = $this->engine()->evaluate($lowCgpa, $drive);
        $this->assertSame(EligibilityResult::NOT_ELIGIBLE, $r->status);
        $this->assertContains('min_cgpa', $r->failedCriteria());
        $this->assertNotEmpty($r->reasons());

        $this->assertContains('max_active_backlogs', $this->engine()->evaluate($backlog, $drive)->failedCriteria());
        $this->assertSame(EligibilityResult::PENDING, $this->engine()->evaluate($unverified, $drive)->status); // nothing verified yet
        $this->assertContains('placement_opt_out', $this->engine()->evaluate($optedOut, $drive)->failedCriteria());
        $this->assertContains('branch', $this->engine()->evaluate($otherBranch, $drive)->failedCriteria());

        // bulk evaluation agrees with single evaluation
        $bulk = $this->engine()->evaluateMany(collect([$good, $lowCgpa, $unverified]), $drive);
        $this->assertSame(EligibilityResult::ELIGIBLE, $bulk[$good->id]->status);
        $this->assertSame(EligibilityResult::NOT_ELIGIBLE, $bulk[$lowCgpa->id]->status);
        $this->assertSame(EligibilityResult::PENDING, $bulk[$unverified->id]->status);
    }

    public function test_engine_handles_lateral_entry_diploma_and_placed_students(): void
    {
        $lateral = $this->makeReady($this->makeStudent('L1', AdmissionType::LATERAL, 3), percentage: 80);

        $accepts = $this->drive(['min_twelfth_percentage' => 75, 'diploma_counts_as_twelfth' => true]);
        $strict = $this->drive(['min_twelfth_percentage' => 75, 'diploma_counts_as_twelfth' => false]);
        $diplomaOnly = $this->drive(['min_diploma_percentage' => 85]);

        $this->assertSame(EligibilityResult::ELIGIBLE, $this->engine()->evaluate($lateral, $accepts)->status);
        $this->assertContains('min_twelfth_percentage', $this->engine()->evaluate($lateral, $strict)->failedCriteria());
        $this->assertContains('min_diploma_percentage', $this->engine()->evaluate($lateral, $diplomaOnly)->failedCriteria());

        // a diploma criterion does not apply to a regular student
        $regular = $this->makeReady($this->makeStudent('R1'));
        $check = collect($this->engine()->evaluate($regular, $diplomaOnly)->checks)->firstWhere('criterion', 'min_diploma_percentage');
        $this->assertSame('NA', $check['result']);

        // already-placed students are blocked unless the drive allows them
        Placement::create(['student_id' => $regular->id, 'company_id' => $accepts->company_id, 'role_title' => 'SDE', 'status' => 'PLACED', 'verified_by' => $this->tpo->id, 'verified_at' => now()]);
        $this->assertContains('already_placed', $this->engine()->evaluate($regular, $accepts)->failedCriteria());
        $accepts->forceFill(['allow_placed_students' => true])->save();
        $this->assertSame(EligibilityResult::ELIGIBLE, $this->engine()->evaluate($regular->fresh(), $accepts->fresh())->status);
    }

    public function test_tpo_can_list_eligible_students_and_see_a_summary(): void
    {
        $drive = $this->drive(['min_cgpa' => 7]);
        $this->makeReady($this->makeStudent('A1'));
        $this->makeReady($this->makeStudent('A2'), sgpa: 6);
        $this->makeStudent('A3');
        $this->makeReady($this->makeStudent('M1', AdmissionType::REGULAR, 3, $this->mech)); // not a candidate (other branch)

        Sanctum::actingAs($this->tpo);
        $this->getJson("/api/tpo/drives/{$drive->id}/eligible-students")->assertOk()->assertJsonCount(3, 'data');

        $eligible = $this->getJson("/api/tpo/drives/{$drive->id}/eligible-students?status=ELIGIBLE")->assertOk()->json('data');
        $this->assertSame(['A1'], array_column($eligible, 'university_id'));

        $summary = $this->getJson("/api/tpo/drives/{$drive->id}/eligibility-summary")->assertOk()->json('data');
        $this->assertSame(3, $summary['candidates']);
        $this->assertSame(1, $summary['eligible']);
        $this->assertSame(1, $summary['verification_pending']);
        $this->assertSame(1, $summary['not_eligible']);
        $this->assertSame(1, $summary['failed_criteria']['min_cgpa']);
    }

    // ---- student applications ------------------------------------------------------------------------------

    public function test_student_applies_once_with_a_frozen_snapshot_and_can_withdraw(): void
    {
        $student = $this->makeReady($this->makeStudent('A1'));
        $drive = $this->drive();

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/drives')->assertOk()->assertJsonPath('data.0.can_apply', true);
        $id = $this->postJson("/api/student/drives/{$drive->id}/apply")->assertCreated()->assertJsonPath('data.stage', 'APPLIED')->json('data.id');
        $this->postJson("/api/student/drives/{$drive->id}/apply")->assertStatus(409);

        $application = Application::findOrFail($id);
        $this->assertEquals(8, $application->data_snapshot['verified_academics']['cgpa']);
        $this->assertSame('ELIGIBLE', $application->data_snapshot['eligibility']['status']);

        $this->postJson("/api/student/applications/$id/withdraw")->assertOk()->assertJsonPath('data.stage', 'WITHDRAWN');
        $this->getJson('/api/student/applications')->assertOk()->assertJsonPath('data.0.stage', 'WITHDRAWN');
    }

    public function test_ineligible_unready_and_late_students_cannot_apply(): void
    {
        $drive = $this->drive();

        $low = $this->makeReady($this->makeStudent('A1'), sgpa: 6);
        Sanctum::actingAs($low->user);
        $this->postJson("/api/student/drives/{$drive->id}/apply")->assertStatus(422);
        $this->getJson('/api/student/drives')->assertOk()->assertJsonCount(0, 'data');                       // default: eligible only
        $this->getJson('/api/student/drives?scope=all')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.eligibility.status', 'NOT_ELIGIBLE');

        // eligible on marks, but no approved resume -> profile is not ready
        $noResume = $this->makeReady($this->makeStudent('A2'));
        Document::query()->where('student_id', $noResume->id)->where('document_type', 'RESUME')->update(['status' => 'PENDING']);
        Sanctum::actingAs($noResume->user);
        $this->postJson("/api/student/drives/{$drive->id}/apply")->assertStatus(422)->assertJsonValidationErrors('profile');

        $late = $this->makeReady($this->makeStudent('A3'));
        $drive->forceFill(['application_deadline' => now()->subMinute()])->save();
        Sanctum::actingAs($late->user);
        $this->postJson("/api/student/drives/{$drive->id}/apply")->assertStatus(409);
    }

    // ---- recruitment stages ---------------------------------------------------------------------------------

    public function test_stage_changes_follow_the_rules_and_bulk_updates_are_all_or_nothing(): void
    {
        $drive = $this->drive();
        $apps = collect(['A1', 'A2', 'A3'])->map(function ($id) use ($drive) {
            $s = $this->makeReady($this->makeStudent($id));
            Sanctum::actingAs($s->user);
            $this->postJson("/api/student/drives/{$drive->id}/apply")->assertCreated();

            return Application::where('student_id', $s->id)->firstOrFail();
        });

        Sanctum::actingAs($this->tpo);
        $a = $apps[0]->id;
        $this->postJson("/api/tpo/applications/$a/stage", ['stage' => 'TECHNICAL'])->assertStatus(422);                 // skips SHORTLISTED
        $this->postJson("/api/tpo/applications/$a/stage", ['stage' => 'OFFER_RECEIVED'])->assertStatus(422);            // placement endpoints only
        $this->postJson("/api/tpo/applications/$a/stage", ['stage' => 'SHORTLISTED', 'remarks' => 'Cleared screening'])->assertOk()->assertJsonPath('data.stage', 'SHORTLISTED');
        $this->postJson("/api/tpo/applications/$a/stage", ['stage' => 'APPLIED', 'force' => true])->assertStatus(422);   // correction needs remarks
        $this->postJson("/api/tpo/applications/$a/stage", ['stage' => 'APPLIED', 'force' => true, 'remarks' => 'Shortlisted by mistake'])->assertOk();

        $this->assertSame(['APPLIED', 'SHORTLISTED', 'APPLIED'], Application::findOrFail($a)->history->pluck('to_stage')->all());

        // bulk: one application is REJECTED (terminal) -> nothing changes
        $this->postJson("/api/tpo/applications/{$apps[2]->id}/stage", ['stage' => 'REJECTED'])->assertOk();
        $ids = $apps->pluck('id')->all();
        $this->postJson("/api/tpo/drives/{$drive->id}/applications/bulk-stage", ['application_ids' => $ids, 'stage' => 'SHORTLISTED'])->assertStatus(422);
        $this->assertSame('APPLIED', Application::findOrFail($apps[1]->id)->stage->value);

        $this->postJson("/api/tpo/drives/{$drive->id}/applications/bulk-stage", ['application_ids' => [$apps[0]->id, $apps[1]->id], 'stage' => 'SHORTLISTED'])
            ->assertOk()->assertJsonPath('updated', 2);

        // the student was told
        $this->assertGreaterThan(0, $apps[1]->student->user->notifications()->count());
    }

    // ---- offers and official placements ----------------------------------------------------------------------

    public function test_offer_then_verification_makes_a_placement_official_and_blocks_further_applications(): void
    {
        $student = $this->makeReady($this->makeStudent('A1'));
        $drive = $this->drive();
        $other = $this->drive();

        Sanctum::actingAs($student->user);
        $appId = $this->postJson("/api/student/drives/{$drive->id}/apply")->assertCreated()->json('data.id');

        Sanctum::actingAs($this->tpo);
        $this->postJson("/api/tpo/applications/$appId/offer")->assertStatus(409);                                  // not SELECTED yet
        $this->postJson("/api/tpo/applications/$appId/stage", ['stage' => 'SHORTLISTED'])->assertOk();
        $this->postJson("/api/tpo/applications/$appId/stage", ['stage' => 'SELECTED'])->assertOk();

        $placementId = $this->postJson("/api/tpo/applications/$appId/offer", ['ctc_lpa' => 7.5])->assertCreated()
            ->assertJsonPath('data.status', 'OFFERED')->json('data.id');
        $this->assertSame('OFFER_RECEIVED', Application::findOrFail($appId)->stage->value);

        Sanctum::actingAs($this->makeCoordinator());
        $this->assertSame(0, $this->getJson('/api/coordinator/dashboard')->json('data.placements.placed_students'));    // offer is not yet official

        Sanctum::actingAs($this->tpo);
        $this->postJson("/api/tpo/placements/$placementId/verify")->assertOk()->assertJsonPath('data.status', 'PLACED');
        $this->assertSame('PLACED', Application::findOrFail($appId)->stage->value);
        $this->patchJson("/api/tpo/placements/$placementId", ['ctc_lpa' => 99])->assertStatus(422);               // verified record is frozen
        $this->patchJson("/api/tpo/placements/$placementId", ['joining_date' => now()->addMonths(3)->toDateString()])->assertOk();

        Sanctum::actingAs($this->makeCoordinator());
        $this->assertSame(1, $this->getJson('/api/coordinator/dashboard')->json('data.placements.placed_students'));

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/placements')->assertOk()->assertJsonPath('data.0.is_official', true);
        $this->postJson("/api/student/drives/{$other->id}/apply")->assertStatus(422);                              // already placed
    }

    public function test_declined_offer_rejects_the_application_and_manual_placements_are_verified_immediately(): void
    {
        $student = $this->makeReady($this->makeStudent('A1'));
        $drive = $this->drive();

        Sanctum::actingAs($student->user);
        $appId = $this->postJson("/api/student/drives/{$drive->id}/apply")->json('data.id');

        Sanctum::actingAs($this->tpo);
        $this->postJson("/api/tpo/applications/$appId/stage", ['stage' => 'SHORTLISTED']);
        $this->postJson("/api/tpo/applications/$appId/stage", ['stage' => 'SELECTED']);
        $placementId = $this->postJson("/api/tpo/applications/$appId/offer")->json('data.id');
        $this->postJson("/api/tpo/placements/$placementId/decline", ['remarks' => 'Student chose another offer'])->assertOk()->assertJsonPath('data.status', 'DECLINED');
        $this->assertSame('REJECTED', Application::findOrFail($appId)->stage->value);

        // off-campus offer, no application
        $this->postJson('/api/tpo/placements', [
            'student_id' => $student->id, 'company_id' => $drive->company_id, 'role_title' => 'Developer',
            'ctc_lpa' => 9, 'offer_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.status', 'PLACED');
    }

    // ---- access control and statistics ------------------------------------------------------------------------

    public function test_only_the_tpo_reaches_tpo_endpoints_and_students_only_touch_their_own_applications(): void
    {
        $student = $this->makeReady($this->makeStudent('A1'));
        $other = $this->makeReady($this->makeStudent('A2'));
        $drive = $this->drive();

        Sanctum::actingAs($this->makeCoordinator());
        $this->getJson('/api/tpo/drives')->assertForbidden();
        $this->postJson('/api/tpo/companies', ['name' => 'X'])->assertForbidden();

        Sanctum::actingAs($student->user);
        $this->getJson('/api/tpo/dashboard')->assertForbidden();
        $this->postJson("/api/student/drives/{$drive->id}/apply")->assertCreated();

        Sanctum::actingAs($other->user);
        $mine = Application::where('student_id', $student->id)->firstOrFail();
        $this->postJson("/api/student/applications/{$mine->id}/withdraw")->assertNotFound();
    }

    public function test_tpo_dashboard_and_company_report_count_only_verified_placements(): void
    {
        $placed = $this->makeStudent('A1');
        $offered = $this->makeStudent('A2');
        $this->makeStudent('A3');
        $drive = $this->drive();

        Placement::create(['student_id' => $placed->id, 'company_id' => $drive->company_id, 'role_title' => 'SDE', 'ctc_lpa' => 8, 'status' => 'PLACED', 'verified_by' => $this->tpo->id, 'verified_at' => now()]);
        Placement::create(['student_id' => $offered->id, 'company_id' => $drive->company_id, 'role_title' => 'SDE', 'ctc_lpa' => 20, 'status' => 'OFFERED']);

        Sanctum::actingAs($this->tpo);
        $data = $this->getJson('/api/tpo/dashboard')->assertOk()->json('data');
        $this->assertSame(3, $data['students']['total']);
        $this->assertSame(1, $data['placements']['placed_students']);
        $this->assertSame(1, $data['placements']['open_offers']);
        $this->assertEquals(8, $data['placements']['highest_ctc_lpa']);            // the unverified 20 LPA offer does not count
        $this->assertEquals(33.33, $data['placements']['placement_percentage']);

        $companies = $this->getJson('/api/tpo/reports/companies')->assertOk()->json('data');
        $this->assertSame(1, $companies[0]['students_placed']);

        $departments = $this->getJson('/api/tpo/reports/departments')->assertOk()->json('data');
        $this->assertSame(1, $departments[0]['placed']);

        $csv = $this->get('/api/tpo/reports/placements.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('A1', $csv);
    }

    public function test_database_refuses_an_unverified_placed_record(): void
    {
        $student = $this->makeStudent('A1');
        $drive = $this->drive();

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::transaction(fn () => Placement::create(['student_id' => $student->id, 'company_id' => $drive->company_id, 'role_title' => 'SDE', 'status' => 'PLACED']));
    }
}
