<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Services\Analytics\CandidatePoolAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PositionAnalyticsController extends Controller
{
    public function __invoke(Request $request, Position $position, CandidatePoolAnalytics $analytics): View
    {
        // Same audience as the candidate list itself: admin and HR.
        abort_unless($request->user()->canManageCandidates(), 403);

        // Every skill's chart is rendered up front, so switching skills in the
        // browser swaps one chart instead of reloading the page. ?skill= only
        // picks which one is shown first (and keeps working without JS).
        $skillExperiences = $analytics->skillExperiences($position);
        $skills = array_keys($skillExperiences);
        $skill = in_array($request->query('skill'), $skills, true) ? $request->query('skill') : ($skills[0] ?? null);

        return view('positions.analytics', [
            'position' => $position,
            'applicants' => $position->candidates()->count(),
            'skills' => $skills,
            'skill' => $skill,
            'skillExperiences' => $skillExperiences,
            'expectedSalary' => $analytics->expectedSalary($position),
            'educationLevel' => $analytics->educationLevel($position),
            'minSample' => CandidatePoolAnalytics::MIN_SAMPLE,
        ]);
    }
}
