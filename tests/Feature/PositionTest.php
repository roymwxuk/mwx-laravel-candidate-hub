<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_links_each_position_to_its_job_description(): void
    {
        $position = Position::factory()->create(['title' => 'テスト職種']);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('dashboard'))
            ->assertSee(route('positions.show', $position), false)
            ->assertSee(route('positions.analytics', $position), false);
    }

    public function test_hr_sees_the_job_description_and_the_analytics_link(): void
    {
        $position = Position::factory()->create(['description' => "【業務内容】\nAPI開発"]);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('positions.show', $position))
            ->assertOk()
            ->assertSee('【業務内容】')
            ->assertSee(route('positions.analytics', $position), false);
    }

    public function test_interviewers_can_read_the_job_description_but_get_no_analytics_link(): void
    {
        $position = Position::factory()->create(['description' => 'JD本文']);

        $this->actingAs(User::factory()->interviewer()->create())
            ->get(route('positions.show', $position))
            ->assertOk()
            ->assertSee('JD本文')
            ->assertDontSee(route('positions.analytics', $position), false);
    }
}
