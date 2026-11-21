<?php

namespace App\Actions;

use App\Enums\ResumeSource;
use App\Enums\ResumeStatus;
use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\ResumeDocument;
use App\Services\ResumeExtraction\DraftCorrections;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Turns a submitted public application into a candidate at 書類選考.
 *
 * Here the applicant, not HR, is the person who reviewed the AI draft: they
 * saw the auto-filled fields and corrected them before submitting. Auto-fill
 * is optional, so the application may come with no resume at all, or with
 * one whose extraction failed (the file is still kept for HR).
 */
class SubmitApplication
{
    /**
     * @param  array<string, mixed>  $data  validated input from ApplicationRequest
     *
     * @throws DomainException when the resume was already used for an application
     */
    public function handle(Position $position, array $data, ?ResumeDocument $document): Candidate
    {
        return DB::transaction(function () use ($position, $data, $document) {
            if ($document !== null) {
                // Lock the row so a double-submit cannot create two candidates.
                $document = ResumeDocument::query()->lockForUpdate()->findOrFail($document->getKey());

                if ($document->source !== ResumeSource::Application || $document->candidate_id !== null) {
                    throw new DomainException('この履歴書はすでに応募に使用されています。');
                }
            }

            $histories = array_values($data['work_histories'] ?? []);

            $candidate = Candidate::create([
                'position_id' => $position->getKey(),
                'name' => $data['name'],
                'name_kana' => $data['name_kana'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'summary' => $data['summary'] ?? null,
                'skills' => $data['skills'] ?? [],
                ...Arr::only($data, Candidate::PROFILE_FIELDS),
                'stage' => Stage::DocumentScreening,
                'created_by' => null, // the applicant has no account
            ]);

            foreach ($histories as $i => $history) {
                $candidate->workHistories()->create([...$history, 'sort_order' => $i]);
            }

            $autofilled = $document?->status === ResumeStatus::Extracted;

            if ($autofilled) {
                // The applicant reviewed the draft: extracted → confirmed.
                $document->transitionTo(ResumeStatus::Confirmed, [
                    'candidate_id' => $candidate->getKey(),
                    'confirmed_at' => now(),
                ]);
            } elseif ($document !== null) {
                // Extraction failed or is still running: no draft was used,
                // but HR still gets the original file with the candidate.
                $document->update(['candidate_id' => $candidate->getKey()]);
            }

            AuditLog::record('candidate.applied', $candidate, null, [
                'position_id' => $position->getKey(),
                'resume_document_id' => $document?->getKey(),
                'autofilled' => $autofilled,
                // Same quality signal as HR's review, but from the applicant.
                'corrected_fields' => $autofilled
                    ? DraftCorrections::between($document->extracted_data ?? [], $data, $histories)
                    : null,
            ]);

            return $candidate;
        });
    }
}
