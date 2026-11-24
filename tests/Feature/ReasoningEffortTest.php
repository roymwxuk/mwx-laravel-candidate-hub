<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\ExtractResumeJob;
use App\Models\ResumeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReasoningEffortTest extends TestCase
{
    use RefreshDatabase;

    private function show(ResumeDocument $document): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => Role::Hr]))
            ->get(route('resumes.show', $document))
            ->assertOk();
    }

    public function test_the_effort_reported_by_the_extractor_is_shown_after_its_name(): void
    {
        config(['services.extractor.url' => 'http://extractor.test']);
        Storage::fake('local');
        Storage::disk('local')->put('resumes/sample.pdf', '%PDF-1.4 fake');
        Http::fake(['extractor.test/*' => Http::response([
            'schema_version' => '1',
            'extractor' => 'azure-openai:gpt-6-luna',
            'reasoning_effort' => 'low',
            'warnings' => [],
            'fields' => ['name' => '架空 太郎'],
        ])]);
        $document = ResumeDocument::factory()->create(['path' => 'resumes/sample.pdf']);

        ExtractResumeJob::dispatchSync($document);

        $this->show($document->fresh())->assertSeeTextInOrder(['抽出エンジン:', 'azure-openai:gpt-6-luna', '推論レベル:', 'low']);
    }

    public function test_nothing_is_shown_when_the_extractor_reported_no_effort(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->show($document)->assertSee('抽出エンジン')->assertDontSee('推論レベル');
    }

    public function test_an_unknown_value_is_not_shown(): void
    {
        foreach ([['x'], 'とても高い', null] as $effort) {
            $document = ResumeDocument::factory()->extracted()->create(['raw_response' => ['reasoning_effort' => $effort]]);

            $this->assertNull($document->reasoningEffort());
        }
    }
}
