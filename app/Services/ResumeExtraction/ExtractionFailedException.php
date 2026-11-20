<?php

namespace App\Services\ResumeExtraction;

use RuntimeException;

/**
 * Transient failure (timeout, 5xx, connection refused). Worth retrying.
 */
class ExtractionFailedException extends RuntimeException {}
