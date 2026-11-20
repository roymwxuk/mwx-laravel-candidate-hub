<?php

namespace App\Exceptions;

use App\Enums\ResumeStatus;
use DomainException;

class InvalidResumeTransition extends DomainException
{
    /**
     * The move is not in the state machine at all.
     */
    public static function between(ResumeStatus $from, ResumeStatus $to): self
    {
        return new self("履歴書の状態を「{$from->label()}」から「{$to->label()}」へは変更できません。");
    }

    /**
     * The move was valid, but someone else (another request or a queue job)
     * changed the status first.
     */
    public static function changedConcurrently(ResumeStatus $expected, ResumeStatus $to): self
    {
        return new self("履歴書の状態が「{$expected->label()}」ではなくなったため、「{$to->label()}」へ変更できませんでした。画面を再読み込みしてください。");
    }
}
