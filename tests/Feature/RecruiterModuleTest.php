<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AcademicRecord;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\Experience;
use App\Models\PlacementDrive;
use App\Models\Recruiter;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTpmsFixtures;
use Tests\TestCase;

/** The recruiter sees student cards for the applicants of their OWN company, and only verified data. */
class RecruiterModuleTest extends TestCase
{
    use CreatesTpmsFixtures, RefreshDatabase;

    private User $tpo;
    private Company $acme;
    private Company $globex;
    private User $recruiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTpms();
        $this->tpo = User::create(['name' => 'TPO', 'email' => 'tpo@college.test', 'password' => 'Password123', 'role' => Role::TPO]);
        $this->acme = Company::create(['name' => 'Acme', 'created_by' => $this->tpo->id]);
        $this->globex = Company::create(['name' => 'Globex', 'created_by' => $this->tpo->id]);
        $this->recruiter = $this->makeRecruiter($this->acme, 'rec@acme.test');
    }

    // ---- fixtures -----------------------------------------------------------------------------------

    private function makeRecruiter(Company $company, string $email): User
    {
        $user = User::create(['name' => 'Recruiter ' . $email, 'email' => $email, 'password' => 'Password123', 'role' => Role::RECRUITER]);
        Recruiter::create([
            'user_id' => $user->id, 'company_id' => $company->id, 'designation' => 'Talent Lead',
            'is_active' => true, 'active_from' => now()->toDateString(), 'created_by' => $this->tpo->id,
        ]);

        return $user;
    }

    private function makeDrive(Company $company, string $status = 'PUBLISHED', string $title = 'Software Engineer'): PlacementDrive
    {
        $drive = PlacementDrive::create([
            'company_id' => $company->id, 'title' => $title, 'graduation_year' => 2029, 'status' => $status,
            'published_at' => $status === 'DRAFT' ? null : now(), 'application_deadline' => $status === 'DRAFT' ? null : now()->addWeek(),
            'created_by' => $this->tpo->id, 'cancel_reason' => $status === 'CANCELLED' ? 'Cancelled' : null,
        ]);
        $drive->branches()->attach([$this->cseBranch->id]);

        return $drive;
    }

    private function apply(Student $student, PlacementDrive $drive, string $stage = 'APPLIED'): Application
    {
        return Application::create([
            'drive_id' => $drive->id, 'student_id' => $student->id, 'stage' => $stage,
            'applied_at' => now(), 'stage_updated_at' => now(), 'data_snapshot' => ['cgpa' => 8.1],
        ]);
    }

    private function doc(Student $student, string $type, string $status = 'APPROVED', array $extra = []): Document
    {
        $uuid = (string) Str::uuid();
        $path = 'students/' . $student->id . '/' . strtolower($type) . '/' . $uuid . '.pdf';
        Storage::disk('tpms_test')->put($path, '%PDF-1.4 test');

        return Document::create($extra + [
            'uuid' => $uuid, 'student_id' => $student->id, 'document_type' => $type, 'version' => 1,
            'disk' => 'tpms_test', 'path' => $path, 'original_name' => strtolower($type) . '.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 13, 'sha256' => hash('sha256', $uuid), 'status' => $status, 'is_primary' => false,
            'uploaded_by' => $student->user_id,
            'reviewed_by' => in_array($status, ['APPROVED', 'REJECTED'], true) ? $this->tpo->id : null,
            'reviewed_at' => in_array($status, ['APPROVED', 'REJECTED'], true) ? now() : null,
        ]);
    }

    private function semester(Student $student, int $semester, string $status = 'VERIFIED', float $sgpa = 8.0): AcademicRecord
    {
        $document = $this->doc($student, 'MARKSHEET_SEMESTER', $status === 'VERIFIED' ? 'APPROVED' : 'PENDING');

        return AcademicRecord::create([
            'student_id' => $student->id, 'level' => 'DEGREE_SEM', 'semester' => $semester, 'institution_name' => 'Sanjivani',
            'board_or_university' => 'SPPU', 'passing_year' => 2027, 'sgpa' => $sgpa, 'cgpa' => $sgpa,
            'document_id' => $document->id, 'status' => $status,
            'reviewed_by' => $status === 'VERIFIED' ? $this->tpo->id : null, 'reviewed_at' => $status === 'VERIFIED' ? now() : null,
            'submitted_at' => now(),
        ]);
    }

    private function experience(Student $student, string $status = 'VERIFIED', string $org = 'Initech'): Experience
    {
        $certificate = $this->doc($student, 'CERT_INTERNSHIP', $status === 'VERIFIED' ? 'APPROVED' : 'PENDING');

        return Experience::create([
            'student_id' => $student->id, 'type' => 'INTERNSHIP', 'organization' => $org, 'role_title' => 'Backend Intern',
            'start_date' => '2026-05-01', 'end_date' => '2026-07-01', 'is_ongoing' => false, 'description' => 'Built APIs',
            'certificate_document_id' => $certificate->id, 'status' => $status,
            'reviewed_by' => $status === 'VERIFIED' ? $this->tpo->id : null, 'reviewed_at' => $status === 'VERIFIED' ? now() : null,
            'submitted_at' => now(),
        ]);
    }

    private function fillProfile(Student $student): void
    {
        $student->forceFill([
            'skills' => ['PHP', 'Laravel', 'SQL'], 'languages' => ['English', 'Hindi'],
            'preferred_roles' => ['Backend'], 'preferred_locations' => ['Pune'],
            'github_url' => 'https://github.com/' . $student->university_id, 'linkedin_url' => 'https://linkedin.com/in/' . $student->university_id,
            'portfolio_url' => 'https://' . strtolower($student->university_id) . '.dev',
            'personal_email' => strtolower($student->university_id) . '@gmail.test', 'phone' => '9876543210',
            'date_of_birth' => '2005-04-12', 'gender' => 'FEMALE', 'current_city' => 'Pune', 'opt_out_reason' => 'private',
        ])->save();
    }

    private function asRecruiter(?User $user = null): void
    {
        Sanctum::actingAs($user ?? $this->recruiter);
    }

    // ---- scope ---------------------------------------------------------------------------------------

    public function test_a_recruiter_sees_only_applicants_of_their_own_company(): void
    {
        $mine = $this->makeStudent('A1');
        $theirs = $this->makeStudent('B1');
        $both = $this->makeStudent('C1');

        $this->apply($mine, $this->makeDrive($this->acme));
        $this->apply($theirs, $this->makeDrive($this->globex));
        $this->apply($both, $this->makeDrive($this->acme, 'CLOSED', 'Analyst'));
        $this->apply($both, $this->makeDrive($this->globex));

        $this->asRecruiter();
        $response = $this->getJson('/api/recruiter/applicants')->assertOk();

        $ids = collect($response->json('data'))->pluck('student.university_id')->sort()->values()->all();
        $this->assertSame(['A1', 'C1'], $ids);
    }

    public function test_withdrawn_applications_and_draft_or_cancelled_drives_are_invisible(): void
    {
        $visible = $this->makeStudent('V1');
        $withdrawn = $this->makeStudent('W1');
        $onDraft = $this->makeStudent('D1');
        $onCancelled = $this->makeStudent('X1');

        $this->apply($visible, $this->makeDrive($this->acme));
        $this->apply($withdrawn, $this->makeDrive($this->acme, 'PUBLISHED', 'Second'), 'WITHDRAWN');
        $this->apply($onDraft, $this->makeDrive($this->acme, 'DRAFT', 'Third'));
        $this->apply($onCancelled, $this->makeDrive($this->acme, 'CANCELLED', 'Fourth'));

        $this->asRecruiter();
        $ids = collect($this->getJson('/api/recruiter/applicants')->assertOk()->json('data'))->pluck('student.university_id')->all();

        $this->assertSame(['V1'], $ids);
    }

    public function test_an_application_of_another_company_is_a_404_not_a_leak(): void
    {
        $student = $this->makeStudent('B1');
        $foreign = $this->apply($student, $this->makeDrive($this->globex));

        $this->asRecruiter();
        $this->getJson('/api/recruiter/applicants/' . $foreign->id)->assertNotFound();
    }

    public function test_drives_list_shows_only_the_companys_drives_with_stage_counts(): void
    {
        $drive = $this->makeDrive($this->acme);
        $this->makeDrive($this->globex, 'PUBLISHED', 'Not mine');
        $this->makeDrive($this->acme, 'DRAFT', 'Draft');

        $this->apply($this->makeStudent('A1'), $drive);
        $this->apply($this->makeStudent('A2'), $drive, 'SHORTLISTED');
        $this->apply($this->makeStudent('A3'), $drive, 'WITHDRAWN');

        $this->asRecruiter();
        $rows = $this->getJson('/api/recruiter/drives')->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['applicants_count']);
        $this->assertSame(['APPLIED' => 1, 'SHORTLISTED' => 1], $rows[0]['stages']);

        $this->getJson('/api/recruiter/drives/' . $drive->id)->assertOk()->assertJsonPath('data.title', 'Software Engineer');
        $this->getJson('/api/recruiter/drives/' . $this->makeDrive($this->globex)->id)->assertNotFound();
    }

    // ---- the card ------------------------------------------------------------------------------------

    public function test_a_card_carries_skills_links_photo_resume_and_verified_academics(): void
    {
        $student = $this->makeStudent('A1');
        $this->fillProfile($student);
        $this->semester($student, 1, 'VERIFIED', 8.0);
        $this->semester($student, 2, 'VERIFIED', 9.0);
        $photo = $this->doc($student, 'PHOTO');
        $resume = $this->doc($student, 'RESUME', 'APPROVED', ['is_primary' => true]);
        $application = $this->apply($student, $this->makeDrive($this->acme));

        $this->asRecruiter();
        $card = $this->getJson('/api/recruiter/applicants')->assertOk()->json('data.0');

        $this->assertSame($application->id, $card['application']['id']);
        $this->assertSame(['PHP', 'Laravel', 'SQL'], $card['student']['skills']);
        $this->assertSame('https://github.com/A1', $card['student']['links']['github']);
        $this->assertSame('https://linkedin.com/in/a1', strtolower($card['student']['links']['linkedin']));
        $this->assertSame('https://a1.dev', $card['student']['links']['portfolio']);
        $this->assertSame($photo->uuid, $card['student']['photo']['uuid']);
        $this->assertSame($resume->uuid, $card['resume']['uuid']);
        $this->assertEquals(9.0, $card['academics']['cgpa']);
        $this->assertSame('CSE', $card['student']['department']['code']);
    }

    public function test_the_card_never_exposes_private_fields(): void
    {
        $student = $this->makeStudent('A1');
        $this->fillProfile($student);
        $application = $this->apply($student, $this->makeDrive($this->acme), 'SHORTLISTED');

        $this->asRecruiter();
        foreach (['/api/recruiter/applicants', '/api/recruiter/applicants/' . $application->id] as $url) {
            $json = $this->getJson($url)->assertOk()->json();
            $body = json_encode($json['data']); // the pagination envelope's own `path` is not card content

            foreach (['date_of_birth', '2005-04-12', 'gender', 'opt_out_reason', 'current_city', 'rejection', '"path"', 'sha256', 'student_id', 'data_snapshot'] as $secret) {
                $this->assertStringNotContainsString($secret, $body, "$url leaked $secret");
            }
        }
    }

    public function test_contact_details_stay_hidden_until_the_applicant_is_shortlisted(): void
    {
        $student = $this->makeStudent('A1');
        $this->fillProfile($student);
        $application = $this->apply($student, $this->makeDrive($this->acme), 'APPLIED');

        $this->asRecruiter();
        $card = $this->getJson('/api/recruiter/applicants/' . $application->id)->assertOk()->json('data.student.contact');
        $this->assertSame(['visible' => false, 'email' => null, 'phone' => null], $card);

        foreach (['SHORTLISTED', 'TECHNICAL', 'SELECTED'] as $stage) {
            $application->update(['stage' => $stage]);
            $contact = $this->getJson('/api/recruiter/applicants/' . $application->id)->json('data.student.contact');
            $this->assertTrue($contact['visible'], $stage);
            $this->assertSame('a1@gmail.test', $contact['email']);
            $this->assertSame('9876543210', $contact['phone']);
        }

        $application->update(['stage' => 'REJECTED']);
        $this->assertFalse($this->getJson('/api/recruiter/applicants/' . $application->id)->json('data.student.contact.visible'));
    }

    public function test_the_full_card_lists_verified_records_with_their_documents(): void
    {
        $student = $this->makeStudent('A1');
        $verified = $this->semester($student, 1);
        $this->semester($student, 2, 'PENDING');
        $internship = $this->experience($student, 'VERIFIED', 'Initech');
        $this->experience($student, 'PENDING', 'Hooli');
        $other = $this->doc($student, 'CERT_OTHER');
        $application = $this->apply($student, $this->makeDrive($this->acme));

        $this->asRecruiter();
        $data = $this->getJson('/api/recruiter/applicants/' . $application->id)->assertOk()->json('data');

        $this->assertCount(1, $data['academic_records']);
        $this->assertSame('Semester 1', $data['academic_records'][0]['label']);
        $this->assertSame($verified->document->uuid, $data['academic_records'][0]['document']['uuid']);

        $this->assertCount(1, $data['experiences']);
        $this->assertSame('Initech', $data['experiences'][0]['organization']);
        $this->assertSame($internship->certificate->uuid, $data['experiences'][0]['certificate']['uuid']);

        $this->assertSame([$other->uuid], array_column($data['other_certificates'], 'uuid'));
    }

    public function test_unverified_data_and_unapproved_files_never_appear(): void
    {
        $student = $this->makeStudent('A1');
        $this->semester($student, 1, 'PENDING');
        $this->experience($student, 'PENDING');
        $this->doc($student, 'PHOTO', 'PENDING');
        $this->doc($student, 'RESUME', 'PENDING', ['is_primary' => true]);
        $this->doc($student, 'PHOTO', 'REJECTED', ['rejection_reason_code' => 'UNREADABLE']);
        $application = $this->apply($student, $this->makeDrive($this->acme));

        $this->asRecruiter();
        $data = $this->getJson('/api/recruiter/applicants/' . $application->id)->assertOk()->json('data');

        $this->assertSame([], $data['academic_records']);
        $this->assertSame([], $data['experiences']);
        $this->assertNull($data['student']['photo']);
        $this->assertNull($data['resume']);
        $this->assertNull($data['academics']['cgpa']);
    }

    public function test_filters_narrow_the_cards(): void
    {
        $drive = $this->makeDrive($this->acme);
        $other = $this->makeDrive($this->acme, 'PUBLISHED', 'Analyst');

        $php = $this->makeStudent('A1');
        $this->fillProfile($php);
        $mech = $this->makeStudent('M1', \App\Enums\AdmissionType::REGULAR, 3, $this->mech);
        $mech->forceFill(['skills' => ['CAD']])->save();

        $this->apply($php, $drive, 'SHORTLISTED');
        $this->apply($mech, $other, 'APPLIED');

        $this->asRecruiter();
        $ids = fn (string $query) => collect($this->getJson('/api/recruiter/applicants?' . $query)->assertOk()->json('data'))->pluck('student.university_id')->all();

        $this->assertSame(['A1'], $ids('drive_id=' . $drive->id));
        $this->assertSame(['M1'], $ids('stage=APPLIED'));
        $this->assertSame(['A1'], $ids('skill=laravel'));
        $this->assertSame(['M1'], $ids('skill=cad'));
        $this->assertSame(['A1'], $ids('branch_id=' . $this->cseBranch->id));
        $this->assertSame(['M1'], $ids('department_id=' . $this->mech->id));
        $this->assertSame(['A1'], $ids('search=student a1'));
        $this->assertSame(['A1', 'M1'], collect($ids('graduation_year=2029'))->sort()->values()->all());

        $this->getJson('/api/recruiter/applicants?stage=WITHDRAWN')->assertStatus(422);
        $this->getJson('/api/recruiter/applicants?per_page=500')->assertStatus(422);
    }

    // ---- documents -----------------------------------------------------------------------------------

    public function test_a_recruiter_can_open_an_approved_document_of_their_applicant(): void
    {
        $student = $this->makeStudent('A1');
        $record = $this->semester($student, 1);
        $this->apply($student, $this->makeDrive($this->acme));
        $uuid = $record->document->uuid;

        $this->asRecruiter();
        $inline = $this->get('/api/recruiter/documents/' . $uuid)->assertOk();
        $this->assertStringContainsString('inline', (string) $inline->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $inline->headers->get('X-Content-Type-Options'));

        $download = $this->get('/api/recruiter/documents/' . $uuid . '?download=1')->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));

        // the shared download route accepts the same recruiter, through the same policy
        $this->get('/api/documents/' . $uuid . '/download')->assertOk();
    }

    public function test_every_document_view_by_a_recruiter_is_audited(): void
    {
        $student = $this->makeStudent('A1');
        $record = $this->semester($student, 1);
        $application = $this->apply($student, $this->makeDrive($this->acme));

        $this->asRecruiter();
        $this->get('/api/recruiter/documents/' . $record->document->uuid)->assertOk();
        $this->getJson('/api/recruiter/applicants/' . $application->id)->assertOk();

        $viewed = AuditLog::query()->where('action', 'document.viewed')->where('actor_id', $this->recruiter->id)->first();
        $this->assertNotNull($viewed);
        $this->assertSame('recruiter', $viewed->actor_role);
        $this->assertSame($student->id, $viewed->student_id);
        $this->assertSame($this->acme->id, $viewed->meta['company_id']);
        $this->assertTrue(AuditLog::query()->where('action', 'recruiter.applicant_viewed')->where('student_id', $student->id)->exists());
    }

    public function test_documents_of_other_companys_applicants_or_unapproved_files_are_refused(): void
    {
        $foreign = $this->makeStudent('B1');
        $this->apply($foreign, $this->makeDrive($this->globex));
        $foreignDoc = $this->semester($foreign, 1)->document;

        $mine = $this->makeStudent('A1');
        $this->apply($mine, $this->makeDrive($this->acme));
        $pending = $this->semester($mine, 1, 'PENDING')->document;
        $superseded = $this->doc($mine, 'RESUME', 'SUPERSEDED');

        $stranger = $this->makeStudent('S1'); // never applied anywhere
        $strangerDoc = $this->doc($stranger, 'RESUME', 'APPROVED', ['is_primary' => true]);

        $this->asRecruiter();
        foreach ([$foreignDoc, $pending, $superseded, $strangerDoc] as $document) {
            $this->get('/api/recruiter/documents/' . $document->uuid)->assertForbidden();
            $this->get('/api/documents/' . $document->uuid . '/download')->assertForbidden();
        }
    }

    public function test_a_withdrawn_application_closes_document_access(): void
    {
        $student = $this->makeStudent('A1');
        $document = $this->semester($student, 1)->document;
        $application = $this->apply($student, $this->makeDrive($this->acme));

        $this->asRecruiter();
        $this->get('/api/recruiter/documents/' . $document->uuid)->assertOk();

        $application->update(['stage' => 'WITHDRAWN']);
        $this->get('/api/recruiter/documents/' . $document->uuid)->assertForbidden();
    }

    // ---- who may call it -----------------------------------------------------------------------------

    public function test_only_recruiters_reach_the_recruiter_api(): void
    {
        $student = $this->makeStudent('A1');
        foreach ([$student->user, $this->makeCoordinator(), $this->tpo] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/recruiter/applicants')->assertForbidden();
            $this->getJson('/api/recruiter/profile')->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/recruiter/applicants')->assertUnauthorized();
    }

    public function test_a_recruiter_cannot_reach_college_staff_or_student_endpoints(): void
    {
        $student = $this->makeStudent('A1');
        $this->apply($student, $this->makeDrive($this->acme));

        $this->asRecruiter();
        foreach (['/api/tpo/students', '/api/tpo/recruiters', '/api/tpo/applications', '/api/coordinator/students', '/api/student/profile'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_a_switched_off_recruiter_or_company_is_refused(): void
    {
        $this->asRecruiter();
        $this->getJson('/api/recruiter/profile')->assertOk()->assertJsonPath('data.company.name', 'Acme');

        $this->acme->update(['is_active' => false]);
        $this->getJson('/api/recruiter/profile')->assertForbidden();
        $this->getJson('/api/recruiter/applicants')->assertForbidden();

        $this->acme->update(['is_active' => true]);
        Recruiter::query()->where('user_id', $this->recruiter->id)->update(['is_active' => false, 'active_until' => now()->toDateString()]);
        $this->getJson('/api/recruiter/profile')->assertForbidden();
    }

    public function test_a_recruiter_without_a_company_row_is_refused(): void
    {
        $orphan = User::create(['name' => 'Orphan', 'email' => 'orphan@x.test', 'password' => 'Password123', 'role' => Role::RECRUITER]);

        Sanctum::actingAs($orphan);
        $this->getJson('/api/recruiter/applicants')->assertForbidden();
    }

    // ---- account management by the TPO ----------------------------------------------------------------

    private function recruiterPayload(array $override = []): array
    {
        return $override + [
            'company_id' => $this->acme->id, 'name' => 'Rhea Rao', 'email' => 'Rhea@Acme.test',
            'password' => 'Str0ngPassword!!', 'phone' => '9876501234', 'designation' => 'HR Manager',
        ];
    }

    public function test_the_tpo_creates_a_recruiter_account_for_a_company(): void
    {
        Sanctum::actingAs($this->tpo);
        $response = $this->postJson('/api/tpo/recruiters', $this->recruiterPayload())->assertCreated()
            ->assertJsonPath('data.email', 'rhea@acme.test')
            ->assertJsonPath('data.company.name', 'Acme')
            ->assertJsonPath('data.is_active', true);

        $user = User::query()->where('email', 'rhea@acme.test')->firstOrFail();
        $this->assertSame(Role::RECRUITER, $user->role);
        $this->assertNull($user->department_id);
        $this->assertSame($response->json('data.id'), $user->recruiter->id);
        $this->assertTrue(AuditLog::query()->where('action', 'recruiter.created')->where('actor_id', $this->tpo->id)->exists());

        // the new account can sign in and use the API
        $login = $this->postJson('/api/auth/login', ['email' => 'rhea@acme.test', 'password' => 'Str0ngPassword!!'])->assertOk();
        $this->assertSame('recruiter', $login->json('user.role'));
    }

    public function test_recruiter_creation_is_validated_and_tpo_only(): void
    {
        Sanctum::actingAs($this->tpo);
        $this->postJson('/api/tpo/recruiters', $this->recruiterPayload(['email' => 'rec@acme.test']))->assertStatus(422);        // email taken
        $this->postJson('/api/tpo/recruiters', $this->recruiterPayload(['password' => 'weak']))->assertStatus(422);
        $this->postJson('/api/tpo/recruiters', $this->recruiterPayload(['company_id' => 9999]))->assertStatus(422);
        $this->postJson('/api/tpo/recruiters', $this->recruiterPayload(['phone' => '123']))->assertStatus(422);

        $this->acme->update(['is_active' => false]);
        $this->postJson('/api/tpo/recruiters', $this->recruiterPayload())->assertStatus(409);

        foreach ([$this->makeCoordinator(), $this->recruiter, $this->makeStudent('A1')->user] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/tpo/recruiters', $this->recruiterPayload())->assertForbidden();
            $this->getJson('/api/tpo/recruiters')->assertForbidden();
        }
    }

    public function test_the_tpo_lists_and_updates_recruiters_but_cannot_move_them_between_companies(): void
    {
        $recruiter = $this->recruiter->recruiter;
        $this->makeRecruiter($this->globex, 'rec@globex.test');

        Sanctum::actingAs($this->tpo);
        $this->assertCount(2, $this->getJson('/api/tpo/recruiters')->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson('/api/tpo/recruiters?company_id=' . $this->acme->id)->json('data'));

        $this->patchJson('/api/tpo/recruiters/' . $recruiter->id, ['designation' => 'VP Talent', 'name' => 'New Name'])
            ->assertOk()->assertJsonPath('data.designation', 'VP Talent')->assertJsonPath('data.name', 'New Name');
        $this->patchJson('/api/tpo/recruiters/' . $recruiter->id, ['company_id' => $this->globex->id])->assertStatus(422);
        $this->assertSame($this->acme->id, $recruiter->fresh()->company_id);
        $this->assertTrue(AuditLog::query()->where('action', 'recruiter.updated')->exists());
    }

    public function test_deactivating_a_recruiter_cuts_access_at_once_and_reactivating_restores_it(): void
    {
        $token = $this->recruiter->createToken('t')->plainTextToken;
        $recruiter = $this->recruiter->recruiter;

        Sanctum::actingAs($this->tpo);
        $this->postJson('/api/tpo/recruiters/' . $recruiter->id . '/deactivate', ['reason' => 'Left the company'])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson('/api/tpo/recruiters/' . $recruiter->id . '/deactivate')->assertStatus(409);

        $this->assertSame(0, $this->recruiter->tokens()->count());
        $this->assertFalse($this->recruiter->fresh()->is_active);
        $this->postJson('/api/auth/login', ['email' => 'rec@acme.test', 'password' => 'Password123'])->assertStatus(422);
        $this->assertNotEmpty($token);

        $this->postJson('/api/tpo/recruiters/' . $recruiter->id . '/reactivate')->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertNull($recruiter->fresh()->active_until);
        $this->postJson('/api/auth/login', ['email' => 'rec@acme.test', 'password' => 'Password123'])->assertOk();

        $this->assertTrue(AuditLog::query()->where('action', 'recruiter.deactivated')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'recruiter.reactivated')->exists());
    }

    public function test_a_password_reset_signs_the_recruiter_out(): void
    {
        $this->recruiter->createToken('t');

        Sanctum::actingAs($this->tpo);
        $this->patchJson('/api/tpo/recruiters/' . $this->recruiter->recruiter->id, ['password' => 'AnotherStr0ngPass!'])->assertOk();

        $this->assertSame(0, $this->recruiter->tokens()->count());
        $this->postJson('/api/auth/login', ['email' => 'rec@acme.test', 'password' => 'AnotherStr0ngPass!'])->assertOk();
    }

    // ---- student photo ---------------------------------------------------------------------------------

    private function png(string $name = 'me.png'): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    public function test_a_student_photo_is_image_only_and_reaches_recruiters_only_after_approval(): void
    {
        $student = $this->makeStudent('A1');
        $application = $this->apply($student, $this->makeDrive($this->acme));

        Sanctum::actingAs($student->user);
        $this->postJson('/api/student/documents', ['document_type' => 'PHOTO', 'document' => $this->pdf('me.pdf')])->assertStatus(422);

        $uuid = $this->postJson('/api/student/documents', ['document_type' => 'PHOTO', 'document' => $this->png()])
            ->assertCreated()->assertJsonPath('data.document_type', 'PHOTO')->assertJsonPath('data.status', 'PENDING')->json('data.uuid');

        // waiting for review: the recruiter sees no photo and cannot open the file
        $this->asRecruiter();
        $this->assertNull($this->getJson('/api/recruiter/applicants/' . $application->id)->json('data.student.photo'));
        $this->get('/api/recruiter/documents/' . $uuid)->assertForbidden();

        Sanctum::actingAs($this->makeCoordinator());
        $this->postJson('/api/coordinator/documents/' . $uuid . '/approve')->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $this->asRecruiter();
        $photo = $this->getJson('/api/recruiter/applicants/' . $application->id)->json('data.student.photo');
        $this->assertSame($uuid, $photo['uuid']);
        $this->assertSame('image/png', $photo['mime_type']);

        $view = $this->get($photo['view_url'])->assertOk();
        $this->assertSame('image/png', $view->headers->get('Content-Type'));
    }

    public function test_a_new_photo_waits_for_review_and_takes_over_when_approved(): void
    {
        $student = $this->makeStudent('A1');
        $application = $this->apply($student, $this->makeDrive($this->acme));
        $first = $this->doc($student, 'PHOTO');

        Sanctum::actingAs($student->user);
        $second = $this->postJson('/api/student/documents', ['document_type' => 'PHOTO', 'document' => $this->png('new.png')])->assertCreated()->json('data.uuid');

        // the approved photo stays visible while the new one waits
        $this->asRecruiter();
        $this->assertSame($first->uuid, $this->getJson('/api/recruiter/applicants/' . $application->id)->json('data.student.photo.uuid'));

        // a third upload replaces the waiting one, not the approved one
        Sanctum::actingAs($student->user);
        $third = $this->postJson('/api/student/documents', ['document_type' => 'PHOTO', 'document' => $this->png('newer.png')])->assertCreated()->json('data.uuid');
        $this->assertSame('SUPERSEDED', Document::query()->where('uuid', $second)->firstOrFail()->status->value);
        $this->assertSame('APPROVED', Document::query()->where('uuid', $first->uuid)->firstOrFail()->status->value);

        Sanctum::actingAs($this->makeCoordinator());
        $this->postJson('/api/coordinator/documents/' . $third . '/approve')->assertOk();

        $this->assertSame('SUPERSEDED', $first->fresh()->status->value);
        $this->asRecruiter();
        $this->assertSame($third, $this->getJson('/api/recruiter/applicants/' . $application->id)->json('data.student.photo.uuid'));
        $this->get('/api/recruiter/documents/' . $first->uuid)->assertForbidden();
    }
}
