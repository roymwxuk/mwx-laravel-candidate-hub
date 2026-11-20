<?php

namespace App\Services\Analytics;

/**
 * A histogram ready for a chart: one count per labelled bucket.
 */
final readonly class Distribution
{
    /**
     * @param  list<string>  $labels
     * @param  list<int>  $counts
     * @param  int  $sample  candidates that have a value
     * @param  float|null  $median  median value; for ordinal data (education) the rank of the median bucket
     * @param  int  $notApplicable  candidates without a value (skill not listed, salary blank)
     */
    public function __construct(
        public array $labels,
        public array $counts,
        public int $sample,
        public ?float $median,
        public int $notApplicable,
    ) {}

    public function isTooSmall(): bool
    {
        return $this->sample < CandidatePoolAnalytics::MIN_SAMPLE;
    }
}
