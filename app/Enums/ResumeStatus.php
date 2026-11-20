<?php

namespace App\Enums;

/**
 * Lifecycle of an uploaded resume:
 * uploaded → processing → extracted (draft awaiting human review) → confirmed
 *                       ↘ failed (can be retried)
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

    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }
}
