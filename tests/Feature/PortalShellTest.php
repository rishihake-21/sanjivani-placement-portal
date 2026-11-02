<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Coordinator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesTpmsFixtures;
use Tests\TestCase;

/**
 * Student, TPO and coordinator pages all sit on one shell (layouts/portal.blade.php): the same stylesheet and
 * sidebar script, a grouped sidebar with exactly one current item, and a single page container.
 */
class PortalShellTest extends TestCase
{
    use CreatesTpmsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTpms();
    }

    private function tpo(): User
    {
        return User::create([
            'name' => 'T&P Office', 'email' => 'tpo.shell@college.test', 'password' => 'Password123',
            'role' => Role::TPO, 'is_active' => true,
        ]);
    }

    /** A coordinator account with the Coordinator profile the web pages need (makeCoordinator only creates the login). */
    private function coordinator(): User
    {
        $user = $this->makeCoordinator($this->cse);
        Coordinator::query()->create([
            'user_id' => $user->id, 'department_id' => $this->cse->id,
            'employee_id' => 'E-SHELL', 'designation' => 'Coordinator', 'active_from' => now()->toDateString(),
        ]);

        return $user;
    }

    /** @return array<string, array{0: callable(self): \Illuminate\Testing\TestResponse}> */
    public static function portalPages(): array
    {
        return [
            'student dashboard' => [fn (self $t) => $t->actingAs($t->makeStudent('SH1')->user)->get(route('student.dashboard'))],
            'student drives' => [fn (self $t) => $t->actingAs($t->makeStudent('SH2')->user)->get(route('student.drives'))],
            'student applications' => [fn (self $t) => $t->actingAs($t->makeStudent('SH3')->user)->get(route('student.applications'))],
            'tpo dashboard' => [fn (self $t) => $t->actingAs($t->tpo())->get(route('tpo.dashboard'))],
            'tpo drives' => [fn (self $t) => $t->actingAs($t->tpo())->get(route('tpo.drives'))],
            'coordinator dashboard' => [fn (self $t) => $t->actingAs($t->coordinator())->get(route('coordinator.dashboard'))],
            'coordinator students' => [fn (self $t) => $t->actingAs($t->coordinator())->get(route('coordinator.students'))],
            'coordinator drives' => [fn (self $t) => $t->actingAs($t->coordinator())->get(route('coordinator.drives'))],
        ];
    }

    #[DataProvider('portalPages')]
    public function test_every_portal_page_renders_on_the_shared_shell(callable $visit): void
    {
        $html = $visit($this)->assertOk()
            ->assertSee('css/tpms-portal.css', false)
            ->assertSee('js/shell.js', false)
            ->assertSee('Skip to content')
            ->assertSee('data-nav-toggle', false)
            ->assertDontSee('css/student.css', false)
            ->assertDontSee('css/tpo.css', false)
            ->assertDontSee('<body class="tpo">', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="nav-item active"'), 'exactly one current nav item');
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertSame(1, substr_count($html, 'class="page"'), 'one page container, not one per layer');
        $this->assertStringContainsString('class="nav-h"', $html);
    }

    public function test_the_student_sidebar_reaches_drives_and_applications(): void
    {
        $this->actingAs($this->makeStudent('SH4')->user)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('student.drives').'"', false)
            ->assertSee('href="'.route('student.applications').'"', false)
            ->assertSee('Placement drives')
            ->assertSee('My applications');
    }

    public function test_the_coordinator_sidebar_marks_the_page_you_are_on(): void
    {
        $this->actingAs($this->coordinator())->get(route('coordinator.students'))
            ->assertOk()
            ->assertSee('href="'.route('coordinator.students').'" class="nav-item active"', false)
            ->assertDontSee('href="'.route('coordinator.dashboard').'" class="nav-item active"', false);
    }

    public function test_the_user_chip_shows_initials_without_punctuation(): void
    {
        $this->actingAs($this->tpo())->get(route('tpo.dashboard'))
            ->assertOk()
            ->assertSee('<span class="avatar" aria-hidden="true">TP</span>', false);
    }
}
