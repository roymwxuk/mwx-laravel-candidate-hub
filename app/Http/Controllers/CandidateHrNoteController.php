<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Candidate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Lets HR add or edit the internal note (備考) after confirmation, e.g. to record
 * an accommodation request made during the selection process.
 */
class CandidateHrNoteController extends Controller
{
    public function update(Request $request, Candidate $candidate): RedirectResponse
    {
        Gate::authorize('editHrNote', $candidate);

        $validated = $request->validate([
            'hr_note' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['hr_note' => '備考']);

        // Empty input arrives as null (ConvertEmptyStringsToNull), so "" clears the note.
        $note = $validated['hr_note'] ?? null;

        if ($note === $candidate->hr_note) {
            return back();
        }

        DB::transaction(function () use ($candidate, $note, $request) {
            $candidate->update(['hr_note' => $note]);
            // The note may hold sensitive personal information (要配慮個人情報), and
            // audit logs are permanent, so record only that it changed, not its text.
            AuditLog::record('hr_note.updated', $candidate, $request->user(), [
                'cleared' => $note === null,
            ]);
        });

        return back()->with('status', '備考を更新しました。');
    }
}
