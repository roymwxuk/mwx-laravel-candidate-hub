<?php

namespace App\Jobs;

use App\Enums\ResumeStatus;
use App\Models\ResumeDocument;
use App\Services\ResumeExtraction\InvalidExtractionResponse;
use App\Services\ResumeExtraction\ResumeExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sends an uploaded resume to the extractor and stores the result as a draft.
 * Runs on the queue so the upload request returns immediately even though an
 * LLM call can take several seconds.
 */
class ExtractResumeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(public ResumeDocument $document) {}

    public function handle(ResumeExtractor $extractor): void
    {
        $document = $this->document;

        // Never overwrite a draft a human is already reviewing or has confirmed.
        if (in_array($document->status, [ResumeStatus::Extracted, ResumeStatus::Confirmed], true)) {
            return;
        }

        $document->update(['status' => ResumeStatus::Processing, 'error_message' => null]);

        try {
            $result = $extractor->extract(
                Storage::disk('local')->get($document->path),
                $document->original_name,
            );
        } catch (InvalidExtractionResponse $e) {
            // Same input → same bad output. Don't burn retries (or LLM credits) on it.
            $this->fail($e);

            return;
        }

        $document->update([
            'status' => ResumeStatus::Extracted,
            'extracted_data' => $result->draft,
            'warnings' => $result->warnings,
            'raw_response' => $result->raw,
            'extractor' => $result->extractor,
            'processed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->document->update([
            'status' => ResumeStatus::Failed,
            'error_message' => $exception?->getMessage() ?? '不明なエラー',
        ]);
    }
}
