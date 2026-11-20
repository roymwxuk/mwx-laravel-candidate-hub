<?php

namespace Tests\Unit;

use App\Enums\Stage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StageTest extends TestCase
{
    /**
     * @return array<string, array{Stage, Stage, bool}>
     */
    public static function transitions(): array
    {
        return [
            '書類選考 → 一次面接' => [Stage::DocumentScreening, Stage::FirstInterview, true],
            '書類選考 → 不採用' => [Stage::DocumentScreening, Stage::Rejected, true],
            '書類選考 → 内定 (skip)' => [Stage::DocumentScreening, Stage::Offer, false],
            '一次面接 → 最終面接' => [Stage::FirstInterview, Stage::FinalInterview, true],
            '一次面接 → 書類選考 (back)' => [Stage::FirstInterview, Stage::DocumentScreening, false],
            '最終面接 → 内定' => [Stage::FinalInterview, Stage::Offer, true],
            '内定 → 不採用 (terminal)' => [Stage::Offer, Stage::Rejected, false],
            '不採用 → 書類選考 (reopen)' => [Stage::Rejected, Stage::DocumentScreening, true],
            '不採用 → 一次面接 (reopen only from the start)' => [Stage::Rejected, Stage::FirstInterview, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transition_rules(Stage $from, Stage $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_only_offer_is_terminal(): void
    {
        $this->assertTrue(Stage::Offer->isTerminal());
        $this->assertFalse(Stage::Rejected->isTerminal());
        $this->assertFalse(Stage::DocumentScreening->isTerminal());
    }

    public function test_only_rejected_to_document_screening_is_a_reopen(): void
    {
        $this->assertTrue(Stage::Rejected->isReopenedBy(Stage::DocumentScreening));
        $this->assertFalse(Stage::DocumentScreening->isReopenedBy(Stage::FirstInterview));
    }
}
