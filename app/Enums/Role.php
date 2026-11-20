<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Hr = 'hr';
    case Interviewer = 'interviewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => '管理者',
            self::Hr => '人事担当',
            self::Interviewer => '面接官',
        };
    }

    /**
     * Admins and HR staff can see every candidate and handle personal data.
     */
    public function canManageCandidates(): bool
    {
        return $this === self::Admin || $this === self::Hr;
    }
}
