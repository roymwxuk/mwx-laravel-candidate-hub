<?php

namespace App\Services\ResumeExtraction;

use RuntimeException;

/**
 * The extractor answered, but the payload does not match the contract.
 * Retrying the same file would give the same result, so the job fails fast.
 */
class InvalidExtractionResponse extends RuntimeException {}
