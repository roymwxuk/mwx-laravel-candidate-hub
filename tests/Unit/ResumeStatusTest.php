<?php

namespace Tests\Unit;

use App\Enums\ResumeStatus;
use PHPUnit\Framework\TestCase;

class ResumeStatusTest extends TestCase
{
    /**
     * The whole state machine, written out independently of the enum, so a
     * change to allowedTransitions() has to be made here on purpose too.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'uploaded' => ['processing', 'failed'],
        'processing' => ['extracted', 'failed'],
        'failed' => ['uploaded'],
        'extracted' => ['confirmed'],
        'confirmed' => [],
    ];

    public function test_every_pair_of_states_matches_the_table(): void
    {
        // 5 × 5 = 25 pairs, including staying in the same state (never allowed).
        foreach (ResumeStatus::cases() as $from) {
            foreach (ResumeStatus::cases() as $to) {
                $this->assertSame(
                    in_array($to->value, self::ALLOWED[$from->value], true),
                    $from->canTransitionTo($to),
                    "{$from->value} → {$to->value}",
                );
            }
        }
    }

    public function test_only_confirmed_is_terminal(): void
    {
        foreach (ResumeStatus::cases() as $status) {
            $this->assertSame($status === ResumeStatus::Confirmed, $status->isTerminal(), $status->value);
        }
    }

    public function test_only_failed_documents_are_retryable(): void
    {
        foreach (ResumeStatus::cases() as $status) {
            $this->assertSame($status === ResumeStatus::Failed, $status->isRetryable(), $status->value);
        }
    }
}
