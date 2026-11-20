<?php

namespace Tests\Feature;

use App\Enums\ResumeStatus;
use App\Exceptions\InvalidResumeTransition;
use App\Models\ResumeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ResumeTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_transition_saves_the_status_and_extra_columns_together(): void
    {
        $document = ResumeDocument::factory()->failed('boom')->create();

        $document->transitionTo(ResumeStatus::Uploaded, ['error_message' => null]);

        $this->assertSame(ResumeStatus::Uploaded, $document->status);
        $this->assertFalse($document->isDirty(), 'model is in sync with the database');
        $fresh = $document->fresh();
        $this->assertSame(ResumeStatus::Uploaded, $fresh->status);
        $this->assertNull($fresh->error_message);
    }

    public function test_a_move_outside_the_state_machine_is_rejected_and_nothing_is_saved(): void
    {
        $document = ResumeDocument::factory()->confirmed()->create();

        try {
            $document->transitionTo(ResumeStatus::Uploaded);
            $this->fail('confirmed → uploaded should be rejected');
        } catch (InvalidResumeTransition $e) {
            $this->assertStringContainsString('「登録済み」から「解析待ち」へは変更できません', $e->getMessage());
        }

        $this->assertSame(ResumeStatus::Confirmed, $document->fresh()->status);
    }

    public function test_a_stale_copy_loses_to_a_change_made_elsewhere(): void
    {
        $document = ResumeDocument::factory()->create(['status' => ResumeStatus::Processing]);
        $stale = ResumeDocument::findOrFail($document->id);

        // Someone else finishes first: processing → extracted.
        $document->transitionTo(ResumeStatus::Extracted, ['extracted_data' => ['name' => '山田 太郎']]);

        // The stale copy still thinks it is processing; processing → failed is
        // a valid move on paper, but the database no longer says processing.
        try {
            $stale->transitionTo(ResumeStatus::Failed, ['error_message' => 'late']);
            $this->fail('the stale copy should not overwrite the newer status');
        } catch (InvalidResumeTransition $e) {
            $this->assertStringContainsString('画面を再読み込み', $e->getMessage());
        }

        $fresh = $document->fresh();
        $this->assertSame(ResumeStatus::Extracted, $fresh->status);
        $this->assertNull($fresh->error_message);
        $this->assertSame(ResumeStatus::Extracted, $stale->status, 'the loser is refreshed to the real state');
    }

    public function test_changing_status_without_transition_to_is_refused(): void
    {
        $document = ResumeDocument::factory()->create();

        $this->expectException(LogicException::class);

        $document->update(['status' => ResumeStatus::Confirmed]);
    }

    public function test_other_columns_can_still_be_updated_normally(): void
    {
        $document = ResumeDocument::factory()->create();

        $document->update(['original_name' => 'renamed.pdf']);

        $this->assertSame('renamed.pdf', $document->fresh()->original_name);
    }
}
