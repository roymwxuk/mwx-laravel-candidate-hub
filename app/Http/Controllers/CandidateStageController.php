<?php

namespace App\Http\Controllers;

use App\Enums\Stage;
use App\Exceptions\InvalidStageTransition;
use App\Http\Requests\ChangeStageRequest;
use App\Models\Candidate;
use Illuminate\Http\RedirectResponse;

class CandidateStageController extends Controller
{
    public function update(ChangeStageRequest $request, Candidate $candidate): RedirectResponse
    {
        try {
            $candidate->transitionTo(
                Stage::from($request->validated('stage')),
                $request->user(),
                $request->validated('note'),
            );
        } catch (InvalidStageTransition $e) {
            return back()->withErrors(['stage' => $e->getMessage()]);
        }

        return back()->with('status', '選考ステージを更新しました。');
    }
}
