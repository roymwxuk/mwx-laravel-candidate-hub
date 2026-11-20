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

    public function assignInterviewers(User $user, Candidate $candidate): bool
    {
        return $user->canManageCandidates();
    }
}
