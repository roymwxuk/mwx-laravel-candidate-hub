<?php

namespace Tests\Feature;

use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_moves_a_candidate_forward_and_it_is_audited(): void
    {
        $hr = User::factory()->hr()->create();
        $candidate = Candidate::factory()->create();

        $this->actingAs($hr)
            ->patch(route('candidates.stage.update', $candidate), ['stage' => 'first_interview', 'note' => '書類通過'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Stage::FirstInterview, $candidate->fresh()->stage);

        $log = AuditLog::sole();
        $this->assertSame('stage.changed', $log->action);
        $this->assertSame(['from' => 'document_screening', 'to' => 'first_interview', 'note' => '書類通過'], $log->properties);
        $this->assertTrue($log->user->is($hr));
    }

    public function test_stages_cannot_be_skipped(): void
    {
        $candidate = Candidate::factory()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->patch(route('candidates.stage.update', $candidate), ['stage' => 'offer'])
            ->assertSessionHasErrors('stage');

        $this->assertSame(Stage::DocumentScreening, $candidate->fresh()->stage);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_terminal_stages_cannot_be_changed(): void
    {
        $candidate = Candidate::factory()->inStage(Stage::Rejected)->create();

        $this->actingAs(User::factory()->hr()->create())
            ->patch(route('candidates.stage.update', $candidate), ['stage' => 'first_interview'])
            ->assertSessionHasErrors('stage');

        $this->assertSame(Stage::Rejected, $candidate->fresh()->stage);
    }

    public function test_interviewers_only_see_candidates_assigned_to_them(): void
    {
        $interviewer = User::factory()->interviewer()->create();
        $assigned = Candidate::factory()->create(['name' => 'アサイン済み 候補者']);
        $other = Candidate::factory()->create(['name' => '他の 候補者']);
        $assigned->interviewers()->attach($interviewer);

        $this->actingAs($interviewer)
            ->get(route('candidates.index'))
            ->assertOk()
            ->assertSee('アサイン済み 候補者')
            ->assertDontSee('他の 候補者');

        $this->actingAs($interviewer)->get(route('candidates.show', $assigned))->assertOk();
        $this->actingAs($interviewer)->get(route('candidates.show', $other))->assertForbidden();
    }

    public function test_interviewers_cannot_change_stages(): void
    {
        $interviewer = User::factory()->interviewer()->create();
        $candidate = Candidate::factory()->create();
        $candidate->interviewers()->attach($interviewer);

        $this->actingAs($interviewer)
            ->patch(route('candidates.stage.update', $candidate), ['stage' => 'first_interview'])
            ->assertForbidden();
    }

    public function test_hr_assigns_and_removes_interviewers(): void
    {
        $hr = User::factory()->hr()->create();
        $interviewer = User::factory()->interviewer()->create(['name' => '面接 一郎']);
        $candidate = Candidate::factory()->create();

        $this->actingAs($hr)->post(route('candidates.interviewers.store', $candidate), ['user_id' => $interviewer->id]);
        $this->assertTrue($candidate->isAssignedTo($interviewer));

        $this->actingAs($hr)->delete(route('candidates.interviewers.destroy', [$candidate, $interviewer]));
        $this->assertFalse($candidate->isAssignedTo($interviewer));

        $this->assertSame(['interviewer.assigned', 'interviewer.removed'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_only_interviewer_accounts_can_be_assigned(): void
    {
        $hr = User::factory()->hr()->create();
        $candidate = Candidate::factory()->create();

        $this->actingAs($hr)
            ->post(route('candidates.interviewers.store', $candidate), ['user_id' => $hr->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_candidate_detail_page_renders_for_hr(): void
    {
        $candidate = Candidate::factory()->hasWorkHistories(2)->create(['name' => '佐藤 健']);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertSee('佐藤 健')
            ->assertSee('一次面接へ進める');
    }
}
