<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidatePositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_sets_the_role_of_a_candidate_confirmed_without_one_and_it_is_audited(): void
    {
        $hr = User::factory()->hr()->create();
        $position = Position::factory()->create(['title' => 'バックエンドエンジニア']);
        $candidate = Candidate::factory()->create(['position_id' => null]);

        $this->actingAs($hr)
            ->patch(route('candidates.position.update', $candidate), ['position_id' => $position->id])
            ->assertSessionHasNoErrors();

        $this->assertTrue($candidate->fresh()->position->is($position));

        $log = AuditLog::sole();
        $this->assertSame('position.changed', $log->action);
        $this->assertSame(['from' => null, 'to' => 'バックエンドエンジニア'], $log->properties);
        $this->assertTrue($log->user->is($hr));
        $this->assertSame('応募職種を「未設定」から「バックエンドエンジニア」に変更しました', $log->description());
    }

    public function test_the_role_can_be_cleared(): void
    {
        $candidate = Candidate::factory()->create(['position_id' => Position::factory()]);

        $this->actingAs(User::factory()->hr()->create())
            ->patch(route('candidates.position.update', $candidate), ['position_id' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($candidate->fresh()->position_id);
    }

    public function test_saving_the_same_role_is_not_audited(): void
    {
        $position = Position::factory()->create();
        $candidate = Candidate::factory()->create(['position_id' => $position->id]);

        $this->actingAs(User::factory()->hr()->create())
            ->patch(route('candidates.position.update', $candidate), ['position_id' => (string) $position->id]);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $candidate = Candidate::factory()->create(['position_id' => null]);

        $this->actingAs(User::factory()->hr()->create())
            ->patch(route('candidates.position.update', $candidate), ['position_id' => 999999])
            ->assertSessionHasErrors('position_id');

        $this->assertNull($candidate->fresh()->position_id);
    }

    public function test_an_assigned_interviewer_cannot_change_the_role(): void
    {
        $interviewer = User::factory()->interviewer()->create();
        $candidate = Candidate::factory()->create(['position_id' => null]);
        $candidate->interviewers()->attach($interviewer);

        $this->actingAs($interviewer)
            ->patch(route('candidates.position.update', $candidate), ['position_id' => Position::factory()->create()->id])
            ->assertForbidden();

        $this->actingAs($interviewer)
            ->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertDontSee(route('candidates.position.update', $candidate));
    }

    public function test_hr_sees_the_role_form_on_the_candidate_page(): void
    {
        $candidate = Candidate::factory()->create(['position_id' => null]);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertSee(route('candidates.position.update', $candidate));
    }
}
