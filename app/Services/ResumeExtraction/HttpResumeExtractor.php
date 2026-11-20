<?php

namespace App\Services\ResumeExtraction;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Calls the Python extraction microservice (see ./extractor).
 */
class HttpResumeExtractor implements ResumeExtractor
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 60,
        private readonly ?string $token = null,
    ) {}

    public function extract(string $contents, string $filename): ExtractionResult
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson()
                ->withHeaders(array_filter(['X-Internal-Token' => $this->token]))
                ->attach('file', $contents, $filename)
                ->post('/extract');
        } catch (ConnectionException $e) {
            throw new ExtractionFailedException('抽出サービスに接続できません: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError()) {
            throw new ExtractionFailedException("抽出サービスがエラーを返しました (HTTP {$response->status()})");
        }

        if ($response->failed()) {
            // 4xx: the file itself was rejected (not a PDF, too large, no text…).
            $detail = $response->json('detail');

            throw new InvalidExtractionResponse(is_string($detail) ? $detail : "抽出サービスがファイルを拒否しました (HTTP {$response->status()})");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new InvalidExtractionResponse('抽出サービスの応答がJSONではありません。');
        }

        return ExtractionResult::fromPayload($payload);
    }
}
