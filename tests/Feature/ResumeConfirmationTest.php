<?php

namespace Tests\Feature;

use App\Enums\ResumeStatus;
use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\ResumeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResumeConfirmationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function reviewedInput(array $overrides = []): array
    {
        return array_merge([
            'name' => '山田 太郎',
            'name_kana' => 'やまだ たろう',
            'email' => 'taro@example.com',
            'phone' => '090-1234-5678',
            'summary' => 'Webエンジニア',
            'skills_text' => "PHP, Laravel\nDocker",
            'work_histories' => [
                ['company' => '株式会社サンプル', 'title' => 'エンジニア', 'started_on' => '2020-04', 'ended_on' => '', 'description' => ''],
                ['company' => '', 'title' => '', 'started_on' => '', 'ended_on' => '', 'description' => ''], // blank row is ignored
            ],
        ], $overrides);
    }

    public function test_hr_confirms_a_draft_and_a_candidate_is_created(): void
    {
        $hr = User::factory()->hr()->create();
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs($hr)
            ->post(route('resumes.confirm', $document), $this->reviewedInput())
            ->assertRedirect();

        $candidate = Candidate::sole();
        $this->assertSame('山田 太郎', $candidate->name);
        $this->assertSame(['PHP', 'Laravel', 'Docker'], $candidate->skills);
        $this->assertSame(Stage::DocumentScreening, $candidate->stage);
        $this->assertCount(1, $candidate->workHistories);

        $document->refresh();
        $this->assertSame(ResumeStatus::Confirmed, $document->status);
        $this->assertTrue($document->candidate->is($candidate));
        $this->assertTrue($document->confirmer->is($hr));

        $log = AuditLog::sole();
        $this->assertSame('candidate.created_from_resume', $log->action);
        $this->assertSame(['skills'], $log->properties['corrected_fields'], 'only the field the human changed is recorded');
    }

    public function test_human_input_is_validated_again(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->post(route('resumes.confirm', $document), $this->reviewedInput([
                'name' => '',
                'email' => 'not-an-email',
                'work_histories' => [['company' => 'A社', 'started_on' => '2022-04', 'ended_on' => '2021-03']],
            ]))
            ->assertSessionHasErrors(['name', 'email', 'work_histories.0.ended_on']);

        $this->assertDatabaseCount('candidates', 0);
        $this->assertSame(ResumeStatus::Extracted, $document->fresh()->status);
    }

    public function test_a_draft_cannot_be_confirmed_twice(): void
    {
        $hr = User::factory()->hr()->create();
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs($hr)->post(route('resumes.confirm', $document), $this->reviewedInput());
        $this->actingAs($hr)->post(route('resumes.confirm', $document), $this->reviewedInput())->assertForbidden();

        $this->assertDatabaseCount('candidates', 1);
    }

    public function test_interviewers_cannot_confirm_drafts(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs(User::factory()->interviewer()->create())
            ->post(route('resumes.confirm', $document), $this->reviewedInput())
            ->assertForbidden();

        $this->assertDatabaseCount('candidates', 0);
    }
}
