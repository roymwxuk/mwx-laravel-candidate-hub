<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\ExtractResumeJob;
use App\Models\ResumeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExtractorHiddenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_review_page_does_not_show_the_model_or_its_reasoning_setting(): void
    {
        config(['services.extractor.url' => 'http://extractor.test']);
        Storage::fake('local');
        Storage::disk('local')->put('resumes/sample.pdf', '%PDF-1.4 fake');
        Http::fake(['extractor.test/*' => Http::response([
            'schema_version' => '1',
            'extractor' => 'azure-openai:test-model',
            'reasoning_effort' => 'test-effort',
            'warnings' => [],
            'fields' => ['name' => '架空 太郎'],
        ])]);
        $document = ResumeDocument::factory()->create(['path' => 'resumes/sample.pdf']);

        ExtractResumeJob::dispatchSync($document);

        $this->actingAs(User::factory()->create(['role' => Role::Hr]))
            ->get(route('resumes.show', $document))
            ->assertOk()
            ->assertSee('架空 太郎')
            ->assertDontSee('抽出エンジン')
            ->assertDontSee('azure-openai')
            ->assertDontSee('test-model')
            ->assertDontSee('test-effort');

        // Still on record for comparing extraction quality.
        $this->assertSame('azure-openai:test-model', $document->fresh()->extractor);
        $this->assertSame('test-effort', $document->fresh()->raw_response['reasoning_effort']);
    }
}
