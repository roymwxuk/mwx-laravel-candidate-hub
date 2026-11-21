<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\User;

class CandidatePolicy
{
    /**
     * Everyone may open the list; the query itself is scoped with Candidate::visibleTo().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Candidate $candidate): bool
    {
        return $user->canManageCandidates() || $candidate->isAssignedTo($user);
    }

    public function changeStage(User $user, Candidate $candidate): bool
    {
        return $user->canManageCandidates();
    }

    public function changePosition(User $user, Candidate $candidate): bool
    {
        return $user->canManageCandidates();
    }

    public function assignInterviewers(User $user, Candidate $candidate): bool
    {
        return $user->canManageCandidates();
    }

    /**
     * Self-reported details that play no part in selection (性別). HR only:
     * interviewers assess the candidate without them.
     */
    public function viewSensitiveDetails(User $user, Candidate $candidate): bool
    {
        return $user->canManageCandidates();
    }

    /**
     * Viewing and editing the HR note (備考). HR only: it may hold sensitive
     * personal information, so assigned interviewers do not see it.
     */
    public function editHrNote(User $user, Candidate $candidate): bool
    {
        return $user->canManageCandidates();
    }
}
