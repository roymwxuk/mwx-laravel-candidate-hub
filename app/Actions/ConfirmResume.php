<?php

namespace App\Actions;

use App\Enums\ResumeStatus;
use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\ResumeDocument;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Turns a human-reviewed draft into an official candidate record.
 * This is the only path by which extracted data reaches the candidate tables.
 */
class ConfirmResume
{
    /**
     * @param  array<string, mixed>  $data  validated input from ConfirmResumeRequest
     *
     * @throws DomainException when the draft was already confirmed or is not ready
     */
    public function handle(ResumeDocument $document, array $data, User $reviewer): Candidate
    {
        return DB::transaction(function () use ($document, $data, $reviewer) {
            // Lock the row so a double-submit cannot create two candidates.
            $document = ResumeDocument::query()->lockForUpdate()->findOrFail($document->getKey());

            // Checked up front so no candidate is created for a resume that
            // cannot be confirmed; transitionTo() below enforces it again.
            if (! $document->status->canTransitionTo(ResumeStatus::Confirmed)) {
                throw new DomainException('この履歴書は確認待ちの状態ではありません。');
            }

            $histories = array_values($data['work_histories'] ?? []);

            $candidate = Candidate::create([
                'name' => $data['name'],
                'name_kana' => $data['name_kana'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'summary' => $data['summary'] ?? null,
                'skills' => $data['skills'] ?? [],
                'stage' => Stage::DocumentScreening,
                'created_by' => $reviewer->getKey(),
            ]);

            foreach ($histories as $i => $history) {
                $candidate->workHistories()->create([...$history, 'sort_order' => $i]);
            }

            $document->transitionTo(ResumeStatus::Confirmed, [
                'candidate_id' => $candidate->getKey(),
                'confirmed_by' => $reviewer->getKey(),
                'confirmed_at' => now(),
            ]);

            AuditLog::record('candidate.created_from_resume', $candidate, $reviewer, [
                'resume_document_id' => $document->getKey(),
                'extractor' => $document->extractor,
                // Which fields the human had to correct: a cheap, honest signal
                // of extraction quality that can be tracked over time.
                'corrected_fields' => $this->correctedFields($document->extracted_data ?? [], $data, $histories),
            ]);

            return $candidate;
        });
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $final
     * @param  list<array<string, mixed>>  $histories
     * @return list<string>
     */
    private function correctedFields(array $draft, array $final, array $histories): array
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
