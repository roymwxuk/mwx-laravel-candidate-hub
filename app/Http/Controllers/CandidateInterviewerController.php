<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CandidateInterviewerController extends Controller
{
    public function store(Request $request, Candidate $candidate): RedirectResponse
    {
        Gate::authorize('assignInterviewers', $candidate);

        $validated = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', Role::Interviewer->value)],
        ], attributes: ['user_id' => '面接官']);

        $interviewer = User::findOrFail($validated['user_id']);

        DB::transaction(function () use ($candidate, $interviewer, $request) {
            $candidate->interviewers()->syncWithoutDetaching([$interviewer->getKey()]);
            AuditLog::record('interviewer.assigned', $candidate, $request->user(), [
                'interviewer_id' => $interviewer->getKey(),
                'interviewer_name' => $interviewer->name,
            ]);
        });

        return back()->with('status', "{$interviewer->name} さんをアサインしました。");
    }

    public function destroy(Request $request, Candidate $candidate, User $user): RedirectResponse
    {
        Gate::authorize('assignInterviewers', $candidate);

        DB::transaction(function () use ($candidate, $user, $request) {
            $candidate->interviewers()->detach($user->getKey());
            AuditLog::record('interviewer.removed', $candidate, $request->user(), [
                'interviewer_id' => $user->getKey(),
                'interviewer_name' => $user->name,
            ]);
        });

        return back()->with('status', "{$user->name} さんのアサインを解除しました。");
    }
}
