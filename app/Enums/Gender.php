<?php

namespace App\Enums;

/**
 * 性別, as the candidate stated it. Optional: null means 未記入 (not stated).
 *
 * Recorded only when the candidate wrote it (resume or application form);
 * never inferred from a name or photo. Not used for screening (男女雇用機会均等法),
 * so it is shown to HR only and kept out of lists, filters and analytics.
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => '男性',
            self::Female => '女性',
            self::Other => 'その他・回答しない',
        };
    }
}
