<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DisplayTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_converts_stored_utc_to_japan_time(): void
    {
        // 15:30 UTC is 00:30 the next day in Tokyo: date and time both move.
        $this->assertSame('2026/11/21 00:30', Carbon::parse('2026-11-20 15:30:00', 'UTC')->display());
    }

    public function test_candidate_list_shows_update_date_and_time(): void
    {
        $candidate = Candidate::factory()->create();
        $candidate->forceFill(['updated_at' => Carbon::parse('2026-11-20 01:05:00', 'UTC')])->saveQuietly();

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('candidates.index'))
            ->assertOk()
            ->assertSee('更新日時')
            ->assertSee('2026/11/20 10:05');
    }
}
