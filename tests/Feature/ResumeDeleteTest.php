<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ResumeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResumeDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_hr_deletes_a_resume_awaiting_review_with_its_file(): void
    {
        $hr = User::factory()->hr()->create();
        Storage::disk('local')->put('resumes/a.pdf', '%PDF-1.4 fake');
        $document = ResumeDocument::factory()->extracted()->create(['path' => 'resumes/a.pdf']);

        $this->actingAs($hr)
            ->delete(route('resumes.destroy', $document))
            ->assertRedirect(route('resumes.index'));

        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing('resumes/a.pdf');

        $log = AuditLog::sole();
        $this->assertSame('resume.deleted', $log->action);
        $this->assertSame(['resume_document_id' => $document->id, 'status' => 'extracted'], $log->properties);
        $this->assertTrue($log->user->is($hr));
    }

    public function test_a_failed_resume_can_be_deleted(): void
    {
        $document = ResumeDocument::factory()->failed()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->delete(route('resumes.destroy', $document))
            ->assertRedirect(route('resumes.index'));

        $this->assertModelMissing($document);
    }

    public function test_the_review_page_shows_the_delete_button_with_a_confirmation(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('resumes.show', $document))
            ->assertOk()
            ->assertSee(route('resumes.destroy', $document))
            ->assertSee('data-confirm=', escape: false);
    }

    public function test_a_confirmed_resume_cannot_be_deleted(): void
    {
        $document = ResumeDocument::factory()->confirmed()->create();
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('resumes.show', $document))
            ->assertDontSee(route('resumes.destroy', $document));

        $this->actingAs($hr)->delete(route('resumes.destroy', $document))->assertForbidden();
        $this->assertModelExists($document);
    }

    public function test_a_resume_still_being_extracted_cannot_be_deleted(): void
    {
        $document = ResumeDocument::factory()->create(); // uploaded: the job may be running

        $this->actingAs(User::factory()->hr()->create())
            ->delete(route('resumes.destroy', $document))
            ->assertForbidden();

        $this->assertModelExists($document);
    }

    public function test_an_applicants_upload_cannot_be_deleted_by_hr(): void
    {
        $document = ResumeDocument::factory()->extracted()->fromApplication()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->delete(route('resumes.destroy', $document))
            ->assertForbidden();

        $this->assertModelExists($document);
    }

    public function test_interviewers_cannot_delete_resumes(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs(User::factory()->interviewer()->create())
            ->delete(route('resumes.destroy', $document))
            ->assertForbidden();

        $this->assertModelExists($document);
    }
}
