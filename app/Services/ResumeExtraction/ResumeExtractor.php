<?php

namespace App\Services\ResumeExtraction;

/**
 * Turns a resume file into a structured, *untrusted* draft.
 *
 * The Laravel app depends only on this contract, so the extraction backend
 * (Python microservice, a different LLM, a rule-based parser…) can be swapped
 * without touching the domain code.
 */
interface ResumeExtractor
{
    /**
     * @throws ExtractionFailedException when the backend is unavailable (retryable)
     * @throws InvalidExtractionResponse when the backend answered with an unusable payload (not retryable)
     */
    public function extract(string $contents, string $filename): ExtractionResult;
}
