<?php

namespace App\Policies;

use App\Enums\ResumeSource;
use App\Enums\ResumeStatus;
use App\Models\ResumeDocument;
use App\Models\User;

/**
 * Raw resumes contain the most sensitive personal data in the system,
 * so only admins and HR staff can touch them. Interviewers never can.
 */
class ResumeDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageCandidates();
    }

    public function view(User $user, ResumeDocument $document): bool
    {
        return $user->canManageCandidates();
    }

    public function create(User $user): bool
    {
        return $user->canManageCandidates();
    }

    public function confirm(User $user, ResumeDocument $document): bool
    {
        return $user->canManageCandidates() && $document->isAwaitingReview();
    }

    /**
     * Discard an upload that will not become a candidate (wrong file, duplicate,
     * failed extraction). Not while the job may still be working on it, and not
     * once confirmed: that resume is the source record of a registered candidate.
     * Applicants' uploads belong to their in-progress application, so HR leaves them.
     */
    public function delete(User $user, ResumeDocument $document): bool
    {
        return $user->canManageCandidates()
            && $document->source === ResumeSource::Staff
            && in_array($document->status, [ResumeStatus::Extracted, ResumeStatus::Failed], true);
    }

    public function retry(User $user, ResumeDocument $document): bool
    {
        return $user->canManageCandidates()
            && $document->source === ResumeSource::Staff
            && $document->status->isRetryable();
    }
}
