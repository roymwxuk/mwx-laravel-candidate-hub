<?php

namespace App\Jobs;

use App\Enums\ResumeStatus;
use App\Exceptions\InvalidResumeTransition;
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

        // A queue retry finds the document already processing and carries on.
        // Otherwise it must be able to start: a draft under review, a confirmed
        // resume or a failed one (a stale duplicate job) is left alone.
        if ($document->status !== ResumeStatus::Processing) {
            if (! $document->status->canTransitionTo(ResumeStatus::Processing)) {
                return;
            }

            $document->transitionTo(ResumeStatus::Processing, ['error_message' => null]);
        }

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

        $document->transitionTo(ResumeStatus::Extracted, [
            'extracted_data' => $result->draft,
            'warnings' => $result->warnings,
            'raw_response' => $result->raw,
            'extractor' => $result->extractor,
            'processed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        // Re-read: another job or a person may have moved it on since.
        $document = $this->document->fresh();

        // A failing duplicate job must not knock a good draft back to failed.
        if ($document === null || ! $document->status->canTransitionTo(ResumeStatus::Failed)) {
            return;
        }

        try {
            $document->transitionTo(ResumeStatus::Failed, [
                'error_message' => $exception?->getMessage() ?? '不明なエラー',
            ]);
        } catch (InvalidResumeTransition) {
            // Changed between the read and the write; whoever won keeps it.
        }
    }
}
