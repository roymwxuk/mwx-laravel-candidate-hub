<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Sets or changes the role a candidate is considered for. HR may leave it empty
 * at confirmation time, so it has to be editable afterwards.
 */
class CandidatePositionController extends Controller
{
    public function update(Request $request, Candidate $candidate): RedirectResponse
    {
        Gate::authorize('changePosition', $candidate);

        $validated = $request->validate([
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
        ], attributes: ['position_id' => '応募職種']);

        $newId = $validated['position_id'] ?? null;
        $oldId = $candidate->position_id;

        // Form values arrive as strings; null and "" both mean "未設定".
        if ((string) $newId === (string) $oldId) {
            return back();
        }

        $from = $candidate->position?->title;
        $to = $newId ? Position::find($newId)?->title : null;

        DB::transaction(function () use ($candidate, $newId, $from, $to, $request) {
            $candidate->update(['position_id' => $newId]);
            AuditLog::record('position.changed', $candidate, $request->user(), [
                'from' => $from,
                'to' => $to,
            ]);
        });

        return back()->with('status', '応募職種を更新しました。');
    }
}
