<?php

namespace Tests\Feature;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\ResumeStatus;
use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\ResumeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResumeConfirmationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function reviewedInput(array $overrides = []): array
    {
        return array_merge([
            'name' => '山田 太郎',
            'name_kana' => 'やまだ たろう',
            'email' => 'taro@example.com',
            'phone' => '090-1234-5678',
            'summary' => 'Webエンジニア',
            'skills_text' => "PHP, Laravel\nDocker",
            'work_histories' => [
                ['company' => '株式会社サンプル', 'title' => 'エンジニア', 'started_on' => '2020-04', 'ended_on' => '', 'description' => ''],
                ['company' => '', 'title' => '', 'started_on' => '', 'ended_on' => '', 'description' => ''], // blank row is ignored
            ],
        ], $overrides);
    }

    public function test_hr_picks_the_role_the_resume_is_for(): void
    {
        $position = Position::factory()->create(['title' => 'CTO（最高技術責任者）']);
        $document = ResumeDocument::factory()->extracted()->create();
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)
            ->get(route('resumes.show', $document))
            ->assertOk()
            ->assertSee('応募職種')
            ->assertSee('CTO（最高技術責任者）');

        $this->actingAs($hr)
            ->post(route('resumes.confirm', $document), $this->reviewedInput(['position_id' => $position->id]))
            ->assertRedirect();

        $this->assertTrue(Candidate::sole()->position->is($position));
    }

    public function test_the_register_button_is_above_and_below_the_fields(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('resumes.show', $document))
            ->assertOk()
            ->assertSeeInOrder(['>候補者として登録</button>', '応募職種', '職歴', '>候補者として登録</button>', '</form>'], escape: false);
    }

    public function test_hr_records_where_the_candidate_came_from(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)
            ->get(route('resumes.show', $document))
            ->assertOk()
            ->assertSee('応募経路（経由・紹介）')
            ->assertSee('name="referral_source"', escape: false);

        $this->actingAs($hr)
            ->post(route('resumes.confirm', $document), $this->reviewedInput(['referral_source' => 'LinkedIn']))
            ->assertRedirect();

        $candidate = Candidate::sole();
        $this->assertSame('LinkedIn', $candidate->referral_source);

        $this->actingAs($hr)->get(route('candidates.show', $candidate))->assertSee('LinkedIn');
    }

    public function test_the_referral_source_is_optional_and_limited_to_100_characters(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)
            ->post(route('resumes.confirm', ResumeDocument::factory()->extracted()->create()), $this->reviewedInput(['referral_source' => str_repeat('あ', 101)]))
            ->assertSessionHasErrors('referral_source');

        $this->actingAs($hr)
            ->post(route('resumes.confirm', ResumeDocument::factory()->extracted()->create()), $this->reviewedInput(['referral_source' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Candidate::sole()->referral_source);
    }

    public function test_gender_is_optional_and_shown_to_hr_only(): void
    {
        $document = ResumeDocument::factory()->extracted(['gender' => 'female'])->create();
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)
            ->get(route('resumes.show', $document))
            ->assertOk()
            ->assertSee('<option value="female" selected>女性</option>', escape: false)
            ->assertSee('未記入');

        $this->actingAs($hr)
            ->post(route('resumes.confirm', $document), $this->reviewedInput(['gender' => 'other']))
            ->assertRedirect();

        $candidate = Candidate::sole();
        $this->assertSame(Gender::Other, $candidate->gender);
        $this->assertContains('gender', AuditLog::sole()->properties['corrected_fields']);

        $this->actingAs($hr)->get(route('candidates.show', $candidate))
            ->assertSeeText('性別')
            ->assertSeeText('その他・回答しない');

        $interviewer = User::factory()->interviewer()->create();
        $candidate->interviewers()->attach($interviewer);
        $this->actingAs($interviewer)->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertDontSeeText('性別')
            ->assertDontSeeText('その他・回答しない');
    }

    public function test_address_is_prefilled_saved_and_shown_to_hr_only(): void
    {
        $document = ResumeDocument::factory()->extracted(['address' => '東京都サンプル区見本町2-4-8'])->create();
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)
            ->get(route('resumes.show', $document))
            ->assertOk()
            ->assertSee('value="東京都サンプル区見本町2-4-8"', escape: false);

        $this->actingAs($hr)
            ->post(route('resumes.confirm', $document), $this->reviewedInput(['address' => '大阪府見本市1-1-1']))
            ->assertRedirect();

        $candidate = Candidate::sole();
        $this->assertSame('大阪府見本市1-1-1', $candidate->address);
        $this->assertContains('address', AuditLog::sole()->properties['corrected_fields']);

        $this->actingAs($hr)->get(route('candidates.show', $candidate))
            ->assertSeeText('大阪府見本市1-1-1');

        $interviewer = User::factory()->interviewer()->create();
        $candidate->interviewers()->attach($interviewer);
        $this->actingAs($interviewer)->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertDontSeeText('大阪府見本市1-1-1');
    }

    public function test_an_unknown_gender_is_rejected_and_blank_means_not_stated(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)
            ->post(route('resumes.confirm', ResumeDocument::factory()->extracted()->create()), $this->reviewedInput(['gender' => 'unknown']))
            ->assertSessionHasErrors('gender');

        $this->actingAs($hr)
            ->post(route('resumes.confirm', ResumeDocument::factory()->extracted()->create()), $this->reviewedInput(['gender' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Candidate::sole()->gender);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->post(route('resumes.confirm', $document), $this->reviewedInput(['position_id' => 999]))
            ->assertSessionHasErrors('position_id');

        $this->assertSame(0, Candidate::count());
    }

    public function test_hr_confirms_a_draft_and_a_candidate_is_created(): void
    {
        $hr = User::factory()->hr()->create();
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs($hr)
            ->post(route('resumes.confirm', $document), $this->reviewedInput())
            ->assertRedirect();

        $candidate = Candidate::sole();
        $this->assertSame('山田 太郎', $candidate->name);
        $this->assertSame(['PHP', 'Laravel', 'Docker'], $candidate->skills);
        $this->assertSame(Stage::DocumentScreening, $candidate->stage);
        $this->assertCount(1, $candidate->workHistories);

        $document->refresh();
        $this->assertSame(ResumeStatus::Confirmed, $document->status);
        $this->assertTrue($document->candidate->is($candidate));
        $this->assertTrue($document->confirmer->is($hr));

        $log = AuditLog::sole();
        $this->assertSame('candidate.created_from_resume', $log->action);
        $this->assertSame(['skills'], $log->properties['corrected_fields'], 'only the field the human changed is recorded');
    }

    public function test_the_review_form_is_prefilled_with_profile_fields_from_the_draft(): void
    {
        $document = ResumeDocument::factory()->extracted([
            'birth_date' => '1990-04-01',
            'languages' => ['英語（TOEIC 850点）'],
            'education_level' => 'master',
            'school_name' => '架空大学大学院',
            'major' => '情報科学専攻',
            'graduated_on' => '2015-03',
            'expected_salary' => 700,
            'desired_location' => '大阪府',
        ])->create();

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('resumes.show', $document))
            ->assertOk()
            ->assertSee('value="1990-04-01"', false)
            ->assertSee('英語（TOEIC 850点）')
            ->assertSee('<option value="master" selected>', false)
            ->assertSee('value="架空大学大学院"', false)
            ->assertSee('value="情報科学専攻"', false)
            ->assertSee('value="2015-03"', false)
            ->assertSee('value="700"', false)
            ->assertSee('value="大阪府"', false);
    }

    public function test_hr_confirms_profile_fields_and_languages_stay_separate_from_skills(): void
    {
        $document = ResumeDocument::factory()->extracted(['expected_salary' => 600])->create();

        $this->actingAs(User::factory()->hr()->create())
            ->post(route('resumes.confirm', $document), $this->reviewedInput([
                'skills_text' => 'PHP, Laravel',
                'languages_text' => "英語（TOEIC 850点、ビジネス）\n中国語（日常会話）\n",
                'birth_date' => '1990-04-01',
                'education_level' => 'bachelor',
                'school_name' => '架空大学',
                'major' => '工学部',
                'graduated_on' => '2013-03',
                'expected_salary' => '600',
                'desired_location' => '東京都',
            ]))
            ->assertRedirect();

        $candidate = Candidate::sole();
        $this->assertSame(['PHP', 'Laravel'], $candidate->skills);
        $this->assertSame(['英語（TOEIC 850点、ビジネス）', '中国語（日常会話）'], $candidate->languages, 'one entry per line, commas kept');
        $this->assertSame('1990-04-01', $candidate->birth_date->format('Y-m-d'));
        $this->assertSame(EducationLevel::Bachelor, $candidate->education_level);
        $this->assertSame('架空大学', $candidate->school_name);
        $this->assertSame('工学部', $candidate->major);
        $this->assertSame('2013-03', $candidate->graduated_on);
        $this->assertSame(600, $candidate->expected_salary);
        $this->assertSame('東京都', $candidate->desired_location);

        $corrected = AuditLog::sole()->properties['corrected_fields'];
        $this->assertNotContains('expected_salary', $corrected, '"600" from the form equals 600 from the draft');
        $this->assertContains('languages', $corrected);
        $this->assertContains('birth_date', $corrected);

        $this->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertSee('大学（学士） ／ 架空大学 ／ 工学部')
            ->assertSee('600万円')
            ->assertSee('中国語（日常会話）');
    }

    public function test_profile_fields_are_validated(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->post(route('resumes.confirm', $document), $this->reviewedInput([
                'birth_date' => now()->addDay()->format('Y-m-d'),
                'education_level' => 'kindergarten',
                'graduated_on' => '2013/03',
                'expected_salary' => '0',
            ]))
            ->assertSessionHasErrors(['birth_date', 'education_level', 'graduated_on', 'expected_salary']);

        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_human_input_is_validated_again(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs(User::factory()->hr()->create())
            ->post(route('resumes.confirm', $document), $this->reviewedInput([
                'name' => '',
                'email' => 'not-an-email',
                'work_histories' => [['company' => 'A社', 'started_on' => '2022-04', 'ended_on' => '2021-03']],
            ]))
            ->assertSessionHasErrors(['name', 'email', 'work_histories.0.ended_on']);

        $this->assertDatabaseCount('candidates', 0);
        $this->assertSame(ResumeStatus::Extracted, $document->fresh()->status);
    }

    public function test_a_draft_cannot_be_confirmed_twice(): void
    {
        $hr = User::factory()->hr()->create();
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs($hr)->post(route('resumes.confirm', $document), $this->reviewedInput());
        $this->actingAs($hr)->post(route('resumes.confirm', $document), $this->reviewedInput())->assertForbidden();

        $this->assertDatabaseCount('candidates', 1);
    }

    public function test_interviewers_cannot_confirm_drafts(): void
    {
        $document = ResumeDocument::factory()->extracted()->create();

        $this->actingAs(User::factory()->interviewer()->create())
            ->post(route('resumes.confirm', $document), $this->reviewedInput())
            ->assertForbidden();

        $this->assertDatabaseCount('candidates', 0);
    }
}
