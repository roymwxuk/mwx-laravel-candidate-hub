<?php

namespace App\Policies;

use App\Enums\ResumeSource;
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

    public function retry(User $user, ResumeDocument $document): bool
    {
        return $user->canManageCandidates()
            && $document->source === ResumeSource::Staff
            && $document->status->isRetryable();
    }
}
