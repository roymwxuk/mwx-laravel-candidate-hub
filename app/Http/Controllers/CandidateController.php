<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\Stage;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Candidate::class);

        $filters = $request->validate([
            'stage' => ['nullable', Rule::enum(Stage::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $candidates = Candidate::query()
            ->visibleTo($request->user())
            ->when($filters['stage'] ?? null, fn ($q, $stage) => $q->where('stage', $stage))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('name_kana', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
            })
            ->with('position:id,title')
            ->withCount('interviewers')
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('candidates.index', [
            'candidates' => $candidates,
            'stages' => Stage::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Candidate $candidate): View
    {
        Gate::authorize('view', $candidate);

        $candidate->load('position:id,title', 'workHistories', 'interviewers', 'resumeDocument', 'auditLogs.user', 'creator');

        return view('candidates.show', [
            'candidate' => $candidate,
            'availableInterviewers' => $request->user()->can('assignInterviewers', $candidate)
                ? User::query()
                    ->where('role', Role::Interviewer)
                    ->whereNotIn('id', $candidate->interviewers->modelKeys())
                    ->orderBy('name')
                    ->get()
                : collect(),
            'positions' => $request->user()->can('changePosition', $candidate)
                ? Position::query()->orderBy('id')->get(['id', 'title'])
                : collect(),
        ]);
    }
}
