<?php

namespace App\Services\ResumeExtraction;

/**
 * Which fields a person changed between the AI draft and what they submitted.
 * Recorded in the audit log: a cheap, honest signal of extraction quality
 * that can be tracked over time, for HR reviews and applicants alike.
 */
final class DraftCorrections
{
    /**
     * @param  array<string, mixed>  $draft  the AI draft (ResumeDocument::$extracted_data)
     * @param  array<string, mixed>  $final  the validated, submitted fields
     * @param  list<array<string, mixed>>  $histories  the submitted work histories
     * @return list<string>
     */
    public static function between(array $draft, array $final, array $histories): array
    {
        $changed = [];

        foreach (['name', 'name_kana', 'email', 'phone', 'summary'] as $key) {
            if (($draft[$key] ?? null) !== (($final[$key] ?? null) ?: null)) {
                $changed[] = $key;
            }
        }

        if (array_values($draft['skills'] ?? []) !== array_values($final['skills'] ?? [])) {
            $changed[] = 'skills';
        }

        $normalize = fn (array $rows) => array_map(
            fn (array $r) => array_map(fn ($v) => $v ?: null, array_intersect_key($r, array_flip(['company', 'title', 'started_on', 'ended_on', 'description']))),
            $rows,
        );

        if ($normalize($draft['work_histories'] ?? []) != $normalize($histories)) {
            $changed[] = 'work_histories';
        }

        return $changed;
    }
}
