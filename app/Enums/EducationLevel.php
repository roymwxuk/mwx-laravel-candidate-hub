<?php

namespace App\Enums;

/**
 * 最終学歴: the highest level of education completed.
 *
 * Cases are declared lowest first; the order is the order of the histogram.
 */
enum EducationLevel: string
{
    case HighSchool = 'high_school';
    case Vocational = 'vocational';
    case Associate = 'associate';
    case Bachelor = 'bachelor';
    case Master = 'master';
    case Doctorate = 'doctorate';

    public function label(): string
    {
        return match ($this) {
            self::HighSchool => '高校',
            self::Vocational => '専門学校',
            self::Associate => '短大・高専',
            self::Bachelor => '大学（学士）',
            self::Master => '大学院（修士）',
            self::Doctorate => '大学院（博士）',
        };
    }

    /**
     * Position in the lowest-first ordering (0 = 高校).
     */
    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    public static function fromRank(int $rank): self
    {
        return self::cases()[$rank];
    }
}
