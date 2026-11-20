<?php

namespace App\Services\ResumeExtraction;

use Illuminate\Support\Facades\Validator;

/**
 * A sanitized extraction draft.
 *
 * LLM output is treated as untrusted input:
 *  - a broken *envelope* (wrong schema version, missing "fields") rejects the whole response;
 *  - a broken *field* (malformed e-mail, impossible date…) is dropped and reported as a warning,
 *    so one bad value does not throw away an otherwise useful draft.
 * Nothing in here is written to the candidate tables until a human confirms it.
 */
final class ExtractionResult
{
    public const SCHEMA_VERSION = '1';

    private const MAX_SKILLS = 30;

    private const MAX_WORK_HISTORIES = 20;

    private const YEAR_MONTH = '/^\d{4}-(0[1-9]|1[0-2])$/';

    /**
     * @param  array<string, mixed>  $draft
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public readonly array $draft,
        public readonly array $warnings,
        public readonly string $extractor,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidExtractionResponse
     */
    public static function fromPayload(array $payload): self
    {
        if ((string) ($payload['schema_version'] ?? '') !== self::SCHEMA_VERSION) {
            throw new InvalidExtractionResponse('未対応のスキーマバージョンです: '.json_encode($payload['schema_version'] ?? null));
        }

        $fields = $payload['fields'] ?? null;

        if (! is_array($fields)) {
            throw new InvalidExtractionResponse('応答に fields オブジェクトが含まれていません。');
        }

        $warnings = array_values(array_filter(
            is_array($payload['warnings'] ?? null) ? $payload['warnings'] : [],
            'is_string',
        ));

        $draft = [
            'name' => self::field($fields, 'name', ['string', 'max:100'], $warnings),
            'name_kana' => self::field($fields, 'name_kana', ['string', 'max:100'], $warnings),
            'email' => self::field($fields, 'email', ['string', 'email', 'max:255'], $warnings),
            'phone' => self::field($fields, 'phone', ['string', 'regex:/^[0-9+\-() ]{6,20}$/'], $warnings),
            'summary' => self::field($fields, 'summary', ['string', 'max:2000'], $warnings),
            'skills' => self::skills($fields['skills'] ?? [], $warnings),
            'work_histories' => self::workHistories($fields['work_histories'] ?? [], $warnings),
        ];

        return new self(
            draft: $draft,
            warnings: $warnings,
            extractor: is_string($payload['extractor'] ?? null) ? $payload['extractor'] : 'unknown',
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $rules
     * @param  list<string>  $warnings
     */
    private static function field(array $source, string $key, array $rules, array &$warnings, string $label = ''): ?string
    {
        $value = $source[$key] ?? null;

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (Validator::make([$key => $value], [$key => $rules])->fails()) {
            $warnings[] = sprintf('%s の値が不正なため破棄しました: %s', $label ?: $key, mb_strimwidth(json_encode($value, JSON_UNESCAPED_UNICODE), 0, 60, '…'));

            return null;
        }

        return $value;
    }

    /**
     * @param  list<string>  $warnings
     * @return list<string>
     */
    private static function skills(mixed $skills, array &$warnings): array
    {
        if (! is_array($skills)) {
            $warnings[] = 'skills が配列ではないため破棄しました。';

            return [];
        }

        $clean = collect($skills)
            ->filter(fn ($s) => is_string($s) && trim($s) !== '' && mb_strlen(trim($s)) <= 50)
            ->map(fn (string $s) => trim($s))
            ->unique()
            ->values();

        if ($clean->count() > self::MAX_SKILLS) {
            $warnings[] = 'スキルが多すぎるため先頭'.self::MAX_SKILLS.'件のみ取り込みました。';
        }

        return $clean->take(self::MAX_SKILLS)->all();
    }

    /**
     * @param  list<string>  $warnings
     * @return list<array<string, string|null>>
     */
    private static function workHistories(mixed $histories, array &$warnings): array
    {
        if (! is_array($histories)) {
            $warnings[] = 'work_histories が配列ではないため破棄しました。';

            return [];
        }

        $result = [];

        foreach (array_values($histories) as $i => $entry) {
            $n = $i + 1;

            if (! is_array($entry)) {
                $warnings[] = "職歴{$n}件目の形式が不正なため破棄しました。";

                continue;
            }

            $company = self::field($entry, 'company', ['string', 'max:200'], $warnings, "職歴{$n}件目の会社名");

            if ($company === null) {
                $warnings[] = "職歴{$n}件目は会社名がないため破棄しました。";

                continue;
            }

            $result[] = [
                'company' => $company,
                'title' => self::field($entry, 'title', ['string', 'max:200'], $warnings, "職歴{$n}件目の役職"),
                'started_on' => self::field($entry, 'started_on', ['string', 'regex:'.self::YEAR_MONTH], $warnings, "職歴{$n}件目の開始年月"),
                'ended_on' => self::field($entry, 'ended_on', ['string', 'regex:'.self::YEAR_MONTH], $warnings, "職歴{$n}件目の終了年月"),
                'description' => self::field($entry, 'description', ['string', 'max:2000'], $warnings, "職歴{$n}件目の業務内容"),
            ];
        }

        if (count($result) > self::MAX_WORK_HISTORIES) {
            $warnings[] = '職歴が多すぎるため先頭'.self::MAX_WORK_HISTORIES.'件のみ取り込みました。';
            $result = array_slice($result, 0, self::MAX_WORK_HISTORIES);
        }

        return $result;
    }
}
