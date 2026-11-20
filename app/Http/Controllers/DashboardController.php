<?php

namespace App\Http\Controllers;

use App\Enums\ResumeStatus;
use App\Enums\Stage;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\ResumeDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $stageCounts = Candidate::query()
            ->visibleTo($user)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $pendingReviews = $user->canManageCandidates() && config('app.resume_intake')
            ? ResumeDocument::query()->where('status', ResumeStatus::Extracted)->latest()->limit(5)->get()
            : collect();

        return view('dashboard', [
            'stages' => Stage::cases(),
            'stageCounts' => $stageCounts,
            'pendingReviews' => $pendingReviews,
            'positions' => $user->canManageCandidates()
                ? Position::query()->withCount('candidates')->orderBy('id')->get()
                : collect(),
            'myCandidates' => $user->canManageCandidates()
                ? collect()
                : $user->assignedCandidates()->orderBy('candidates.updated_at', 'desc')->get(),
        ]);
    }
}
