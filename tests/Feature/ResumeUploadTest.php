<?php

namespace Tests\Feature;

use App\Enums\ResumeStatus;
use App\Jobs\ExtractResumeJob;
use App\Models\ResumeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResumeUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
    }

    public function test_hr_can_upload_a_pdf_and_extraction_is_queued(): void
    {
        $hr = User::factory()->hr()->create();

        $response = $this->actingAs($hr)->post(route('resumes.store'), [
            'resume' => UploadedFile::fake()->create('yamada.pdf', 100, 'application/pdf'),
        ]);

        $document = ResumeDocument::sole();
        $response->assertRedirect(route('resumes.show', $document));

        $this->assertSame(ResumeStatus::Uploaded, $document->status);
        $this->assertSame('yamada.pdf', $document->original_name);
        Storage::disk('local')->assertExists($document->path);
        Queue::assertPushed(ExtractResumeJob::class, fn ($job) => $job->document->is($document));
    }

    public function test_non_pdf_files_are_rejected(): void
    {
        $this->actingAs(User::factory()->hr()->create())
            ->post(route('resumes.store'), ['resume' => UploadedFile::fake()->create('resume.docx', 100)])
            ->assertSessionHasErrors('resume');

        $this->assertDatabaseCount('resume_documents', 0);
        Queue::assertNothingPushed();
    }

    public function test_interviewers_cannot_upload_or_view_resumes(): void
    {
        $interviewer = User::factory()->interviewer()->create();
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs($interviewer)
            ->post(route('resumes.store'), ['resume' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertForbidden();

        $this->actingAs($interviewer)->get(route('resumes.index'))->assertForbidden();
        $this->actingAs($interviewer)->get(route('resumes.show', $document))->assertForbidden();
        $this->actingAs($interviewer)->get(route('resumes.file', $document))->assertForbidden();
    }

    public function test_review_screen_shows_the_ai_draft_and_warnings(): void
    {
        $document = ResumeDocument::factory()->extracted(['name' => '山田 太郎'])->create([
            'warnings' => ['email の値が不正なため破棄しました: "foo@"'],
        ]);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('resumes.show', $document))
            ->assertOk()
            ->assertSee('山田 太郎')
            ->assertSee('要確認')
            ->assertSee('email の値が不正なため破棄しました', false);
    }

    public function test_failed_documents_can_be_retried(): void
    {
        $document = ResumeDocument::factory()->failed()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->post(route('resumes.retry', $document))
            ->assertRedirect(route('resumes.show', $document));

        $this->assertSame(ResumeStatus::Uploaded, $document->fresh()->status);
        Queue::assertPushed(ExtractResumeJob::class);
    }

    public function test_documents_that_did_not_fail_cannot_be_retried(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->post(route('resumes.retry', $document))
            ->assertForbidden();
    }
}
