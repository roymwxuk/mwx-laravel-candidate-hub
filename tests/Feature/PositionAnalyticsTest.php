<?php

namespace Tests\Feature;

use App\Enums\EducationLevel;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use App\Services\Analytics\CandidatePoolAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(Position $position, ?int $salary, array $skillYears): Candidate
    {
        return Candidate::factory()->create([
            'position_id' => $position->id,
            'expected_salary' => $salary,
            'skill_years' => $skillYears,
        ]);
    }

    public function test_skill_experience_is_bucketed_and_skips_candidates_without_the_skill(): void
    {
        $position = Position::factory()->create();
        $this->applicant($position, 500, ['PHP' => 0.5]);
        $this->applicant($position, 500, ['PHP' => 3]);   // lower bound belongs to 3〜5年
        $this->applicant($position, 500, ['PHP' => 4.5]);
        $this->applicant($position, 500, ['PHP' => 12]);
        $this->applicant($position, 500, ['Go' => 2]);    // no PHP: not applicable
        Candidate::factory()->create(['skill_years' => ['PHP' => 1]]); // other position

        $result = app(CandidatePoolAnalytics::class)->skillExperience($position, 'PHP');

        $this->assertSame(['1年未満', '1〜3年', '3〜5年', '5〜10年', '10年以上'], $result->labels);
        $this->assertSame([1, 0, 2, 0, 1], $result->counts);
        $this->assertSame(4, $result->sample);
        $this->assertSame(1, $result->notApplicable);
        $this->assertSame(3.75, $result->median);
    }

    public function test_salary_bands_keep_empty_bands_between_min_and_max(): void
    {
        $position = Position::factory()->create();
        $this->applicant($position, 420, []);
        $this->applicant($position, 480, []);
        $this->applicant($position, 650, []);
        $this->applicant($position, null, []);

        $result = app(CandidatePoolAnalytics::class)->expectedSalary($position);

        $this->assertSame(['400万円台', '500万円台', '600万円台'], $result->labels);
        $this->assertSame([2, 0, 1], $result->counts);
        $this->assertSame(1, $result->notApplicable);
        $this->assertSame(480.0, $result->median);
    }

    public function test_education_levels_are_listed_in_order_with_a_lower_median(): void
    {
        $position = Position::factory()->create();
        foreach ([EducationLevel::Bachelor, EducationLevel::Bachelor, EducationLevel::Master, EducationLevel::Doctorate, null] as $level) {
            Candidate::factory()->create(['position_id' => $position->id, 'education_level' => $level]);
        }

        $result = app(CandidatePoolAnalytics::class)->educationLevel($position);

        $this->assertSame(['高校', '専門学校', '短大・高専', '大学（学士）', '大学院（修士）', '大学院（博士）'], $result->labels);
        $this->assertSame([0, 0, 0, 2, 1, 1], $result->counts);
        $this->assertSame(4, $result->sample);
        $this->assertSame(1, $result->notApplicable);
        // Four values: lower median is the 2nd, i.e. 学士 rather than "between 学士 and 修士".
        $this->assertSame(EducationLevel::Bachelor, EducationLevel::fromRank((int) $result->median));
    }

    public function test_skills_are_listed_most_common_first(): void
    {
        $position = Position::factory()->create();
        $this->applicant($position, null, ['Go' => 1, 'PHP' => 1]);
        $this->applicant($position, null, ['PHP' => 2]);

        $this->assertSame(['PHP', 'Go'], app(CandidatePoolAnalytics::class)->skills($position));
    }

    public function test_small_samples_are_withheld(): void
    {
        $position = Position::factory()->create();
        $this->applicant($position, 500, ['PHP' => 1]);
        $this->applicant($position, 600, ['PHP' => 2]);

        $this->actingAs(User::factory()->hr()->create())
            ->get(route('positions.analytics', $position))
            ->assertOk()
            ->assertSee('対象者が3名未満のため表示していません')
            ->assertDontSee('data-histogram', false);
    }

    public function test_hr_sees_both_charts_for_the_seeded_role(): void
    {
        $this->seed();
        $position = Position::query()->where('title', 'バックエンドエンジニア（Role A）')->sole();

        $this->actingAs(User::query()->where('email', 'hr@example.com')->sole())
            ->get(route('positions.analytics', ['position' => $position, 'skill' => 'Laravel']))
            ->assertOk()
            ->assertSee('Laravel の経験年数')
            ->assertSee('希望年収')
            ->assertSee('最終学歴')
            ->assertSee('中央値 大学（学士）')
            ->assertSee('対象 10名');
    }

    public function test_every_skill_is_rendered_so_switching_skills_needs_no_reload(): void
    {
        $this->seed();
        $position = Position::query()->where('title', 'バックエンドエンジニア（Role A）')->sole();

        $response = $this->actingAs(User::query()->where('email', 'hr@example.com')->sole())
            ->get(route('positions.analytics', ['position' => $position, 'skill' => 'Laravel']))
            ->assertOk();

        // The requested skill is visible; every other skill is present but hidden.
        preg_match_all('/<div data-skill-panel="([^"]+)"\s*(hidden)?\s*>/u', $response->getContent(), $panels, PREG_SET_ORDER);
        $hidden = collect($panels)->mapWithKeys(fn (array $m) => [html_entity_decode($m[1]) => ($m[2] ?? '') === 'hidden']);

        $this->assertEqualsCanonicalizing(app(CandidatePoolAnalytics::class)->skills($position), $hidden->keys()->all());
        $this->assertSame(['Laravel'], $hidden->reject()->keys()->all());

        // Skills held by fewer than three applicants (e.g. Redis) stay withheld.
        $response->assertSee('対象者が3名未満のため表示していません');
    }

    public function test_seeded_cto_role_has_the_five_sample_candidates(): void
    {
        $this->seed();
        $position = Position::query()->where('title', 'CTO（最高技術責任者）')->sole();

        $this->assertEqualsCanonicalizing(
            ['佐藤 健', '鈴木 美咲', '高橋 翔', '田中 優', '伊藤 誠'],
            $position->candidates()->pluck('name')->all(),
        );

        // Salary and education have 5 values each; every skill is held by
        // one person only, so the skill chart stays withheld.
        $this->actingAs(User::query()->where('email', 'hr@example.com')->sole())
            ->get(route('positions.analytics', $position))
            ->assertOk()
            ->assertSee('対象 5名')
            ->assertSee('対象者が3名未満のため表示していません');
    }

    public function test_seeded_pdm_role_has_four_applicants_and_shows_charts(): void
    {
        $this->seed();
        $position = Position::query()->where('title', 'プロダクトマネージャー（PdM）')->sole();

        $this->assertSame(4, $position->candidates()->count());

        $this->actingAs(User::query()->where('email', 'hr@example.com')->sole())
            ->get(route('positions.analytics', $position))
            ->assertOk()
            ->assertSee('プロダクトマネジメント の経験年数')
            ->assertSee('対象 4名')
            ->assertSee('data-histogram', false);
    }

    public function test_interviewers_cannot_see_analytics(): void
    {
        $position = Position::factory()->create();

        $this->actingAs(User::factory()->interviewer()->create())
            ->get(route('positions.analytics', $position))
            ->assertForbidden();
    }
}
