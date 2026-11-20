<?php

namespace Tests\Feature;

use App\Actions\SubmitApplication;
use App\Enums\ResumeSource;
use App\Enums\ResumeStatus;
use App\Enums\Stage;
use App\Jobs\ExtractResumeJob;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\ResumeDocument;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobApplicationTest extends TestCase
{
    use RefreshDatabase;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();

        $this->position = Position::factory()->create([
            'title' => 'プロダクトマネージャー（PdM）',
            'description' => "【業務内容】\n・採用管理領域の企画をリードしていただきます。\n\n【想定年収】\n650万円〜1,000万円\n\n【勤務地】\n東京都",
        ]);
    }

    private function sessionKey(): string
    {
        return "application.{$this->position->id}.resume_document_id";
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationInput(array $overrides = []): array
    {
        return array_merge([
            'name' => '山田 太郎',
            'name_kana' => 'やまだ たろう',
            'email' => 'taro@example.com',
            'phone' => '090-1234-5678',
            'summary' => 'Webエンジニア',
            'skills_text' => 'PHP, Laravel',
            'work_histories' => [
                ['company' => '株式会社サンプル', 'title' => 'エンジニア', 'started_on' => '2020-04', 'ended_on' => '', 'description' => ''],
            ],
            'consent' => '1',
        ], $overrides);
    }

    public function test_guests_can_browse_roles_and_read_the_job_description(): void
    {
        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('プロダクトマネージャー（PdM）')
            ->assertSee('採用管理領域の企画をリードしていただきます。')
            ->assertSee('650万円〜1,000万円')
            ->assertSee('社員ログイン');

        $this->get(route('jobs.show', $this->position))
            ->assertOk()
            ->assertSee('採用管理領域の企画をリードしていただきます。')
            ->assertSee(route('jobs.apply', $this->position), false);

        $this->get(route('jobs.apply', $this->position))
            ->assertOk()
            ->assertSee('履歴書から自動入力')
            ->assertSee('個人情報の取り扱いに同意する');
    }

    public function test_uploading_a_resume_starts_extraction_and_the_page_waits_for_it(): void
    {
        $response = $this->post(route('jobs.apply.resume', $this->position), [
            'resume' => UploadedFile::fake()->create('my-resume.pdf', 100, 'application/pdf'),
            'consent' => '1',
        ]);

        $response->assertRedirect(route('jobs.apply', $this->position));

        $document = ResumeDocument::sole();
        $this->assertSame(ResumeSource::Application, $document->source);
        $this->assertNull($document->uploaded_by);
        $this->assertSame(ResumeStatus::Uploaded, $document->status);
        Storage::disk('local')->assertExists($document->path);
        Queue::assertPushed(ExtractResumeJob::class);
        $response->assertSessionHas($this->sessionKey(), $document->id);

        // While reading: a polling notice and no form (a reload would wipe typed input).
        $this->get(route('jobs.apply', $this->position))
            ->assertSee('「my-resume.pdf」を読み取っています')
            ->assertSee(route('jobs.apply.status', $this->position), false)
            ->assertDontSee('応募する</button>', false);

        $this->getJson(route('jobs.apply.status', $this->position))
            ->assertOk()
            ->assertExactJson(['status' => 'uploaded']);
    }

    public function test_uploading_requires_consent_and_a_pdf(): void
    {
        $this->post(route('jobs.apply.resume', $this->position), [
            'resume' => UploadedFile::fake()->create('resume.docx', 10),
        ])->assertSessionHasErrors(['resume', 'consent']);

        $this->assertSame(0, ResumeDocument::count());
        Queue::assertNothingPushed();
    }

    public function test_the_extracted_draft_fills_the_form_and_the_applicant_submits_it(): void
    {
        $document = ResumeDocument::factory()->fromApplication()->extracted(['name' => '山田 太郎', 'skills' => ['PHP']])->create([
            'warnings' => ['ふりがなは推定です'],
        ]);
        $session = [$this->sessionKey() => $document->id];

        $this->withSession($session)
            ->get(route('jobs.apply', $this->position))
            ->assertOk()
            ->assertSee('value="山田 太郎"', false)
            ->assertSee('ふりがなは推定です');

        $this->withSession($session)
            ->post(route('jobs.apply.submit', $this->position), $this->applicationInput())
            ->assertRedirect(route('jobs.applied', $this->position))
            ->assertSessionMissing($this->sessionKey());

        $candidate = Candidate::sole();
        $this->assertTrue($candidate->position->is($this->position));
        $this->assertSame(Stage::DocumentScreening, $candidate->stage);
        $this->assertNull($candidate->created_by);
        $this->assertSame(['PHP', 'Laravel'], $candidate->skills);

        $document->refresh();
        $this->assertSame(ResumeStatus::Confirmed, $document->status);
        $this->assertTrue($document->candidate->is($candidate));
        $this->assertNull($document->confirmed_by, 'confirmed by the applicant, who has no account');

        $log = AuditLog::sole();
        $this->assertSame('candidate.applied', $log->action);
        $this->assertTrue($log->properties['autofilled']);
        $this->assertContains('skills', $log->properties['corrected_fields'], 'the applicant added Laravel');
        $this->assertSame('応募フォームから応募しました（履歴書から自動入力）', $log->description());

        $this->get(route('jobs.applied', $this->position))->assertOk()->assertSee('ご応募ありがとうございました');
    }

    public function test_applying_without_a_resume_works(): void
    {
        $this->post(route('jobs.apply.submit', $this->position), $this->applicationInput())
            ->assertRedirect(route('jobs.applied', $this->position));

        $candidate = Candidate::sole();
        $this->assertTrue($candidate->position->is($this->position));
        $this->assertNull($candidate->resumeDocument);
        $this->assertFalse(AuditLog::sole()->properties['autofilled']);
    }

    public function test_email_and_consent_are_required_to_apply(): void
    {
        $this->post(route('jobs.apply.submit', $this->position), $this->applicationInput(['email' => '', 'consent' => null]))
            ->assertSessionHasErrors(['email', 'consent']);

        $this->assertSame(0, Candidate::count());
    }

    public function test_a_resume_that_could_not_be_read_still_reaches_hr_with_the_application(): void
    {
        $document = ResumeDocument::factory()->fromApplication()->failed()->create();

        $this->withSession([$this->sessionKey() => $document->id])
            ->get(route('jobs.apply', $this->position))
            ->assertSee('履歴書を読み取れませんでした');

        $this->withSession([$this->sessionKey() => $document->id])
            ->post(route('jobs.apply.submit', $this->position), $this->applicationInput())
            ->assertRedirect(route('jobs.applied', $this->position));

        $document->refresh();
        $this->assertSame(ResumeStatus::Failed, $document->status, 'no draft was used, so it is not "confirmed"');
        $this->assertTrue($document->candidate->is(Candidate::sole()));
    }

    public function test_a_resume_cannot_be_used_for_two_applications(): void
    {
        $document = ResumeDocument::factory()->fromApplication()->extracted()->create();
        $action = app(SubmitApplication::class);
        $data = ['name' => '山田 太郎', 'email' => 'taro@example.com', 'skills' => [], 'work_histories' => []];

        $action->handle($this->position, $data, $document);

        $this->expectException(DomainException::class);
        $action->handle($this->position, $data, $document);
    }

    public function test_an_applicant_only_ever_sees_their_own_upload(): void
    {
        $someoneElses = ResumeDocument::factory()->fromApplication()->extracted(['name' => '他人 花子'])->create();

        // No session entry: the other applicant's draft is unreachable.
        $this->getJson(route('jobs.apply.status', $this->position))->assertNotFound();
        $this->get(route('jobs.apply', $this->position))->assertDontSee('他人 花子');

        // A staff upload put into the session is ignored too.
        $staffUpload = ResumeDocument::factory()->extracted(['name' => '社内 一郎'])->create();
        $this->withSession([$this->sessionKey() => $staffUpload->id])
            ->get(route('jobs.apply', $this->position))
            ->assertDontSee('社内 一郎');

        $this->assertSame(ResumeStatus::Extracted, $someoneElses->fresh()->status);
    }

    public function test_applicant_drafts_stay_out_of_hrs_review_queue(): void
    {
        $draft = ResumeDocument::factory()->fromApplication()->extracted(['name' => '応募中 次郎'])->create();
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('dashboard'))->assertDontSee('応募中 次郎');
        $this->actingAs($hr)->get(route('resumes.index'))->assertDontSee('応募中 次郎');
        $this->actingAs($hr)
            ->post(route('resumes.confirm', $draft), ['name' => '応募中 次郎'])
            ->assertForbidden();
    }

    public function test_public_uploads_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('jobs.apply.resume', $this->position), [
                'resume' => UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf'),
                'consent' => '1',
            ])->assertRedirect();
        }

        $this->post(route('jobs.apply.resume', $this->position), [
            'resume' => UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf'),
            'consent' => '1',
        ])->assertTooManyRequests();
    }

    public function test_hr_sees_the_application_on_the_candidate_page(): void
    {
        $document = ResumeDocument::factory()->fromApplication()->extracted()->create();

        $this->withSession([$this->sessionKey() => $document->id])
            ->post(route('jobs.apply.submit', $this->position), $this->applicationInput());

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('candidates.show', Candidate::sole()))
            ->assertOk()
            ->assertSeeText('応募者：応募フォームから応募しました（履歴書から自動入力）')
            ->assertSeeText('応募者本人が応募フォームからアップロード');
    }
}
