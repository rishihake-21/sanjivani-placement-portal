<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The TPO web pages render with their own stylesheet and the shared filter components. */
class TpoPagesTest extends TestCase
{
    use RefreshDatabase;

    private function tpo(): User
    {
        return User::create([
            'name' => 'TPO', 'email' => 'tpo.pages@college.test', 'password' => 'Password123',
            'role' => Role::TPO, 'is_active' => true,
        ]);
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/tpo/dashboard')->assertRedirect('/login');
    }

    public function test_the_dashboard_uses_the_tpo_theme_and_a_batch_segmented_filter(): void
    {
        $this->actingAs($this->tpo())->get('/tpo/dashboard')
            ->assertOk()
            ->assertSee('<body class="tpo">', false)
            ->assertSee('css/tpo.css', false)
            ->assertSee('class="segmented"', false)
            ->assertSee('data-filter="graduation_year"', false)
            ->assertSee('Needs attention')
            ->assertSee('Applications by current stage');
    }

    public function test_list_pages_render_a_filter_toolbar(): void
    {
        $this->actingAs($this->tpo());

        foreach (['/tpo/drives', '/tpo/applications', '/tpo/companies', '/tpo/placements'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('class="filters"', false);
        }
    }

    public function test_the_segmented_filter_falls_back_to_a_select_when_there_are_many_choices(): void
    {
        $few = $this->blade('<x-ui.segmented name="graduation_year" label="Batch" :options="[2027 => 2027, 2028 => 2028]" />');
        $few->assertSee('class="segmented"', false);
        $few->assertSee('aria-pressed="true"', false);

        $many = $this->blade('<x-ui.segmented name="graduation_year" label="Batch" :options="$o" />', ['o' => array_combine(range(2024, 2031), range(2024, 2031))]);
        $many->assertDontSee('class="segmented"', false);
        $many->assertSee('<select', false);
    }
}
