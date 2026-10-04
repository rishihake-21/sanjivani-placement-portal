<?php

namespace Tests\Feature;

use App\Enums\AdmissionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTpmsFixtures;
use Tests\TestCase;

/**
 * The Blade portal on top of the student module. Needs PostgreSQL like StudentModuleTest.
 * It checks the wiring: who may see the pages, that every page renders, and that the write routes
 * under /student/x/* behave like the API (same validation, same 423 for a locked profile).
 */
class StudentPagesTest extends TestCase
{
    use CreatesTpmsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTpms();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/student/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_a_student_can_sign_in_and_sees_the_dashboard(): void
    {
        $student = $this->makeStudent('S100');

        $this->post('/login', ['email' => $student->user->email, 'password' => 'Password123'])
            ->assertRedirect(route('student.dashboard'));

        $this->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Welcome, Student')
            ->assertSee('Application readiness')
            ->assertSee('You cannot apply yet');
    }

    public function test_a_wrong_password_is_refused_with_one_message(): void
    {
        $student = $this->makeStudent('S101');

        $this->from('/login')->post('/login', ['email' => $student->user->email, 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_staff_cannot_use_the_student_portal(): void
    {
        $coordinator = $this->makeCoordinator();

        $this->from('/login')->post('/login', ['email' => $coordinator->email, 'password' => 'Password123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($coordinator)->get(route('student.dashboard'))->assertForbidden();
    }

    public function test_every_page_renders_for_a_regular_and_a_lateral_student(): void
    {
        foreach ([AdmissionType::REGULAR, AdmissionType::LATERAL] as $i => $type) {
            $student = $this->makeStudent('P' . $i, $type, 5);
            $this->actingAs($student->user);

            foreach (['dashboard', 'profile', 'academic', 'experience', 'documents', 'notifications'] as $page) {
                $this->get(route("student.$page"))->assertOk();
            }
            $this->get(route('student.documents', ['history' => 1]))->assertOk();
            $this->get(route('student.notifications', ['unread' => 1]))->assertOk();
        }
    }

    public function test_the_academic_page_lists_the_required_records_for_the_admission_type(): void
    {
        $lateral = $this->makeStudent('L200', AdmissionType::LATERAL, 5);

        $this->actingAs($lateral->user)->get(route('student.academic'))
            ->assertOk()
            ->assertSee('Diploma')
            ->assertSee('Semester 3')
            ->assertSee('Semester 4')
            ->assertDontSee('Semester 1');
    }

    public function test_profile_and_record_writes_work_through_the_session_routes(): void
    {
        $student = $this->makeStudent('S300', AdmissionType::REGULAR, 3);
        $this->actingAs($student->user);

        $this->patchJson(route('student.x.profile.update'), ['current_city' => 'Pune'])->assertOk();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'current_city' => 'Pune']);

        $this->patchJson(route('student.x.profile.update'), ['phone' => '12345'])->assertStatus(422);

        $id = $this->postJson(route('student.x.academic.store'), [
            'level' => 'TENTH', 'percentage' => 88.4, 'document' => $this->pdf(),
        ])->assertCreated()->json('data.id');

        $this->postJson(route('student.x.academic.submit', $id))->assertOk()->assertJsonPath('data.status', 'PENDING');

        $this->get(route('student.academic'))->assertOk()->assertSee('In review');
    }

    public function test_a_locked_profile_shows_the_banner_and_refuses_writes(): void
    {
        $student = $this->makeStudent('S400');
        $student->forceFill(['profile_locked_at' => now()])->save();
        $this->actingAs($student->user);

        $this->get(route('student.profile'))->assertOk()->assertSee('Your profile is locked');
        $this->patchJson(route('student.x.profile.update'), ['current_city' => 'Nashik'])->assertStatus(423);
    }

    public function test_downloads_are_only_for_the_owner(): void
    {
        $owner = $this->makeStudent('S500');
        $other = $this->makeStudent('S501');

        $this->actingAs($owner->user)->postJson(route('student.x.documents.store'), [
            'document_type' => 'RESUME', 'document' => $this->pdf('resume.pdf'),
        ])->assertCreated();
        $uuid = $owner->documents()->firstOrFail()->uuid;

        $this->actingAs($owner->user)->get(route('student.documents.download', $uuid))->assertOk();
        $this->actingAs($other->user)->get(route('student.documents.download', $uuid))->assertForbidden();
    }
}
