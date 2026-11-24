<?php

namespace Tests\Feature;

use App\Models\ResumeDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExtractorLabelTest extends TestCase
{
    #[DataProvider('labels')]
    public function test_model_names_are_replaced(string $aliases, ?string $extractor, ?string $label): void
    {
        config(['services.extractor.aliases' => $aliases]);

        $this->assertSame($label, (new ResumeDocument(['extractor' => $extractor]))->extractorLabel());
    }

    /**
     * @return array<string, array{string, string|null, string|null}>
     */
    public static function labels(): array
    {
        return [
            'alias' => ['azure-openai:test-model=Cedar', 'azure-openai:test-model', 'Cedar'],
            'one of several, with spaces' => ['a:b=Birch, azure-openai:test-model = Cedar', 'azure-openai:test-model', 'Cedar'],
            'not listed' => ['a:b=Birch', 'azure-openai:test-model', 'AI'],
            'no setting' => ['', 'azure-openai:test-model', 'AI'],
            'malformed setting' => ['azure-openai:test-model', 'azure-openai:test-model', 'AI'],
            'empty alias' => ['azure-openai:test-model=', 'azure-openai:test-model', 'AI'],
            'the mock keeps its name' => ['', 'mock-rules-v1', 'mock-rules-v1'],
            'not extracted yet' => ['', null, null],
        ];
    }
}
