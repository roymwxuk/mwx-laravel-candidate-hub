<?php

namespace Tests\Feature;

use App\Enums\ResumeStatus;
use App\Jobs\ExtractResumeJob;
use App\Models\ResumeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cross-service contract test: Laravel → real Python extractor → sanitized draft.
 * Skipped unless EXTRACTOR_INTEGRATION_URL points at a running extractor (CI starts one).
 */
class ExtractorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_real_extractor_service_honours_the_contract(): void
    {
        $url = env('EXTRACTOR_INTEGRATION_URL');

        if (! $url) {
            $this->markTestSkipped('Set EXTRACTOR_INTEGRATION_URL to run against a live extractor.');
        }

        config([
            'services.extractor.url' => $url,
            'services.extractor.token' => env('EXTRACTOR_INTEGRATION_TOKEN'),
        ]);

        Storage::fake('local');
        Storage::disk('local')->put(
            'resumes/sample.pdf',
            file_get_contents(base_path('extractor/tests/fixtures/sample_resume.pdf')),
        );

        $document = ResumeDocument::factory()->create([
            'path' => 'resumes/sample.pdf',
            'original_name' => 'sample_resume.pdf',
        ]);

        ExtractResumeJob::dispatchSync($document);

        $document->refresh();
        $this->assertSame(ResumeStatus::Extracted, $document->status, (string) $document->error_message);
        $this->assertSame('架空 太郎', $document->extracted_data['name']);
        $this->assertSame('kakuu.taro@example.com', $document->extracted_data['email']);
        $this->assertCount(2, $document->extracted_data['work_histories']);
        $this->assertSame('2016-04', $document->extracted_data['work_histories'][0]['started_on']);
    }
}
