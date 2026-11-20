<?php

namespace App\Enums;

/**
 * Selection stages. Transitions are explicit so that a candidate can never
 * skip a step (e.g. 書類選考 → 内定). 内定 is final; 不採用 can only be reopened
 * back to 書類選考, so a reconsidered candidate goes through every step again.
 */
enum Stage: string
{
    case DocumentScreening = 'document_screening';
    case FirstInterview = 'first_interview';
    case FinalInterview = 'final_interview';
    case Offer = 'offer';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DocumentScreening => '書類選考',
            self::FirstInterview => '一次面接',
            self::FinalInterview => '最終面接',
            self::Offer => '内定',
            self::Rejected => '不採用',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DocumentScreening => [self::FirstInterview, self::Rejected],
            self::FirstInterview => [self::FinalInterview, self::Rejected],
            self::FinalInterview => [self::Offer, self::Rejected],
            // A rejected candidate can be reconsidered, but only from the start.
            self::Rejected => [self::DocumentScreening],
            self::Offer => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Moving back from 不採用 to 書類選考 restarts the selection process,
     * so the UI asks for an explicit confirmation first.
     */
    public function isReopenedBy(self $next): bool
    {
        return $this === self::Rejected && $next === self::DocumentScreening;
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Tailwind classes for the stage badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::DocumentScreening => 'bg-slate-100 text-slate-700',
            self::FirstInterview => 'bg-sky-100 text-sky-800',
            self::FinalInterview => 'bg-indigo-100 text-indigo-800',
            self::Offer => 'bg-emerald-100 text-emerald-800',
            self::Rejected => 'bg-rose-100 text-rose-800',
        };
    }
}
