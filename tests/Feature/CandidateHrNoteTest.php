<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateHrNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_edits_the_note_and_the_audit_log_does_not_keep_its_text(): void
    {
        $hr = User::factory()->hr()->create();
        $candidate = Candidate::factory()->create(['hr_note' => null]);
        $note = '面接時に配慮の申し出あり（段差のない会場を希望）';

        $this->actingAs($hr)
            ->patch(route('candidates.hr-note.update', $candidate), ['hr_note' => $note])
            ->assertSessionHasNoErrors();

        $this->assertSame($note, $candidate->fresh()->hr_note);

        $log = AuditLog::sole();
        $this->assertSame('hr_note.updated', $log->action);
        $this->assertSame(['cleared' => false], $log->properties);
        $this->assertTrue($log->user->is($hr));
        $this->assertSame('備考を更新しました', $log->description());
    }

    public function test_the_note_can_be_cleared(): void
    {
        $candidate = Candidate::factory()->create(['hr_note' => '古いメモ']);

        $this->actingAs(User::factory()->hr()->create())
            ->patch(route('candidates.hr-note.update', $candidate), ['hr_note' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($candidate->fresh()->hr_note);
        $this->assertSame('備考を削除しました', AuditLog::sole()->description());
    }

    public function test_saving_an_unchanged_note_writes_no_audit_log(): void
    {
        $candidate = Candidate::factory()->create(['hr_note' => '同じ内容']);

        $this->actingAs(User::factory()->hr()->create())
            ->patch(route('candidates.hr-note.update', $candidate), ['hr_note' => '同じ内容'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_the_note_is_limited_to_2000_characters(): void
    {
        $candidate = Candidate::factory()->create(['hr_note' => null]);

        $this->actingAs(User::factory()->hr()->create())
            ->patch(route('candidates.hr-note.update', $candidate), ['hr_note' => str_repeat('あ', 2001)])
            ->assertSessionHasErrors('hr_note');

        $this->assertNull($candidate->fresh()->hr_note);
    }

    public function test_an_assigned_interviewer_can_neither_edit_nor_see_the_note(): void
    {
        $interviewer = User::factory()->interviewer()->create();
        $candidate = Candidate::factory()->create(['hr_note' => '面接時に配慮の申し出あり']);
        $candidate->interviewers()->attach($interviewer);

        $this->actingAs($interviewer)
            ->patch(route('candidates.hr-note.update', $candidate), ['hr_note' => '書き換え'])
            ->assertForbidden();

        $this->assertSame('面接時に配慮の申し出あり', $candidate->fresh()->hr_note);

        $this->actingAs($interviewer)
            ->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertDontSee('面接時に配慮の申し出あり')
            ->assertDontSee('name="hr_note"', escape: false);
    }

    public function test_hr_sees_the_note_in_an_editable_field(): void
    {
        $candidate = Candidate::factory()->create(['hr_note' => '面接時に配慮の申し出あり']);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertSee('name="hr_note"', escape: false)
            ->assertSee('面接時に配慮の申し出あり');
    }
}
