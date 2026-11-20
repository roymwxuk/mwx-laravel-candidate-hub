<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateListTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shows_the_role_each_candidate_applied_for(): void
    {
        $position = Position::factory()->create(['title' => 'プロダクトマネージャー（PdM）']);
        Candidate::factory()->create(['name' => '応募 太郎', 'position_id' => $position->id]);
        Candidate::factory()->create(['name' => '職種なし 花子', 'position_id' => null]);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('candidates.index'))
            ->assertOk()
            ->assertSee('応募職種')
            ->assertSeeInOrder(['応募 太郎', 'プロダクトマネージャー（PdM）'])
            ->assertSeeInOrder(['職種なし 花子', '未設定']);
    }
}
