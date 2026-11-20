<?php

namespace App\Enums;

/**
 * Lifecycle of an uploaded resume, as a state machine:
 *
 *   uploaded → processing → extracted → confirmed (final)
 *        ↖        ↓
 *          failed
 *
 * - uploaded   → processing  the queue job picks it up
 * - uploaded   → failed      the job died before it could start
 * - processing → extracted   the extractor returned a valid draft
 * - processing → failed      the extractor errored or broke the contract
 * - failed     → uploaded    a person pressed 再解析 (retry)
 * - extracted  → confirmed   a person reviewed the draft and registered the candidate
 *
 * Anything else (e.g. overwriting a reviewed draft, or reviving a confirmed
 * resume) is rejected by ResumeDocument::transitionTo().
 */
enum ResumeStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Extracted = 'extracted';
    case Failed = 'failed';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => '解析待ち',
            self::Processing => '解析中',
            self::Extracted => '確認待ち',
            self::Failed => '解析失敗',
            self::Confirmed => '登録済み',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Uploaded, self::Processing => 'bg-amber-100 text-amber-800',
            self::Extracted => 'bg-sky-100 text-sky-800',
            self::Failed => 'bg-rose-100 text-rose-800',
            self::Confirmed => 'bg-emerald-100 text-emerald-800',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Uploaded => [self::Processing, self::Failed],
            self::Processing => [self::Extracted, self::Failed],
            self::Failed => [self::Uploaded],
            self::Extracted => [self::Confirmed],
            self::Confirmed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function isRetryable(): bool
    {
        return $this->canTransitionTo(self::Uploaded);
    }
}
