<?php

namespace Tests\Feature;

use App\Enums\ResumeStatus;
use App\Jobs\ExtractResumeJob;
use App\Models\ResumeDocument;
use App\Services\ResumeExtraction\ExtractionFailedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The extractor microservice is faked at the HTTP layer, so these tests
 * exercise the real HttpResumeExtractor + sanitization without any LLM.
 */
class ExtractResumeJobTest extends TestCase
{
    use RefreshDatabase;

    private ResumeDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.extractor.url' => 'http://extractor.test', 'services.extractor.token' => 'secret']);

        Storage::fake('local');
        Storage::disk('local')->put('resumes/sample.pdf', '%PDF-1.4 fake');

        $this->document = ResumeDocument::factory()->create(['path' => 'resumes/sample.pdf']);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function payload(array $fields = [], array $warnings = []): array
    {
        return [
            'schema_version' => '1',
            'extractor' => 'mock-rules-v1',
            'warnings' => $warnings,
            'fields' => array_merge([
                'name' => '山田 太郎',
                'name_kana' => 'やまだ たろう',
                'email' => 'taro@example.com',
                'phone' => '090-1234-5678',
                'summary' => null,
                'skills' => ['PHP', 'Laravel', 'PHP'],
                'work_histories' => [
                    ['company' => '株式会社サンプル', 'title' => 'エンジニア', 'started_on' => '2020-04', 'ended_on' => null, 'description' => null],
                ],
            ], $fields),
        ];
    }

    public function test_a_valid_response_becomes_a_draft_awaiting_review(): void
    {
        Http::fake(['extractor.test/*' => Http::response($this->payload())]);

        ExtractResumeJob::dispatchSync($this->document);

        $document = $this->document->fresh();
        $this->assertSame(ResumeStatus::Extracted, $document->status);
        $this->assertSame('山田 太郎', $document->extracted_data['name']);
        $this->assertSame(['PHP', 'Laravel'], $document->extracted_data['skills'], 'duplicates are removed');
        $this->assertSame('mock-rules-v1', $document->extractor);
        $this->assertNotNull($document->raw_response);

        // Nothing is written to the candidate tables until a human confirms.
        $this->assertDatabaseCount('candidates', 0);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://extractor.test/extract'
            && $request->hasHeader('X-Internal-Token', 'secret')
            && $request->isMultipart());
    }

    public function test_invalid_fields_are_dropped_with_a_warning_instead_of_failing(): void
    {
        Http::fake(['extractor.test/*' => Http::response($this->payload([
            'email' => 'taro@@example',
            'phone' => 'call me maybe',
            'work_histories' => [
                ['company' => '株式会社サンプル', 'started_on' => '2020-13'],
                ['title' => '会社名なし'],
            ],
        ], warnings: ['ふりがなは推定です']))]);

        ExtractResumeJob::dispatchSync($this->document);

        $document = $this->document->fresh();
        $this->assertSame(ResumeStatus::Extracted, $document->status);
        $this->assertNull($document->extracted_data['email']);
        $this->assertNull($document->extracted_data['phone']);
        $this->assertCount(1, $document->extracted_data['work_histories']);
        $this->assertNull($document->extracted_data['work_histories'][0]['started_on']);

        $warnings = implode("\n", $document->warnings);
        $this->assertStringContainsString('ふりがなは推定です', $warnings, 'extractor warnings are kept');
        $this->assertStringContainsString('email', $warnings);
        $this->assertStringContainsString('phone', $warnings);
        $this->assertStringContainsString('職歴1件目の開始年月', $warnings);
        $this->assertStringContainsString('職歴2件目は会社名がない', $warnings);
    }

    public function test_a_response_that_breaks_the_contract_fails_without_retrying(): void
    {
        Http::fake(['extractor.test/*' => Http::response(['schema_version' => '2', 'fields' => []])]);

        ExtractResumeJob::dispatchSync($this->document);

        $document = $this->document->fresh();
        $this->assertSame(ResumeStatus::Failed, $document->status);
        $this->assertStringContainsString('スキーマバージョン', $document->error_message);
        $this->assertNull($document->extracted_data);
    }

    public function test_a_rejected_file_fails_with_the_extractors_reason(): void
    {
        Http::fake(['extractor.test/*' => Http::response(['detail' => 'PDFからテキストを抽出できませんでした'], 422)]);

        ExtractResumeJob::dispatchSync($this->document);

        $document = $this->document->fresh();
        $this->assertSame(ResumeStatus::Failed, $document->status);
        $this->assertSame('PDFからテキストを抽出できませんでした', $document->error_message);
    }

    public function test_server_errors_are_treated_as_transient(): void
    {
        Http::fake(['extractor.test/*' => Http::response('boom', 503)]);

        try {
            ExtractResumeJob::dispatchSync($this->document);
            $this->fail('A transient error should be re-thrown so the queue can retry it.');
        } catch (ExtractionFailedException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
        }

        // On the sync driver the job has no retries left, so failed() has run.
        $this->assertSame(ResumeStatus::Failed, $this->document->fresh()->status);
    }

    public function test_an_already_reviewed_draft_is_never_overwritten(): void
    {
        Http::fake();
        $document = ResumeDocument::factory()->confirmed()->create(['path' => 'resumes/sample.pdf']);

        ExtractResumeJob::dispatchSync($document);

        $this->assertSame(ResumeStatus::Confirmed, $document->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_a_queue_retry_continues_a_document_that_is_already_processing(): void
    {
        Http::fake(['extractor.test/*' => Http::response($this->payload())]);
        $document = ResumeDocument::factory()->create(['path' => 'resumes/sample.pdf', 'status' => ResumeStatus::Processing]);

        ExtractResumeJob::dispatchSync($document);

        $this->assertSame(ResumeStatus::Extracted, $document->fresh()->status);
    }

    public function test_a_failing_duplicate_job_does_not_knock_a_good_draft_back_to_failed(): void
    {
        $document = ResumeDocument::factory()->extracted()->create(['path' => 'resumes/sample.pdf']);

        (new ExtractResumeJob($document))->failed(new ExtractionFailedException('late failure'));

        $this->assertSame(ResumeStatus::Extracted, $document->fresh()->status);
        $this->assertNull($document->fresh()->error_message);
    }
}
