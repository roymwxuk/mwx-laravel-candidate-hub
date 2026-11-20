<?php

namespace App\Services\Analytics;

use App\Enums\EducationLevel;
use App\Models\Candidate;
use App\Models\Position;
use Illuminate\Support\Collection;

/**
 * Aggregate views over everyone who applied for one position.
 *
 * Only distributions are produced, never per-person scores. A chart whose
 * sample is smaller than MIN_SAMPLE is withheld, so an aggregate cannot be
 * traced back to one or two individuals.
 */
class CandidatePoolAnalytics
{
    public const MIN_SAMPLE = 3;

    /**
     * Upper bounds (exclusive) for the years-of-experience buckets.
     *
     * @var array<string, float>
     */
    private const YEAR_BUCKETS = [
        '1年未満' => 1,
        '1〜3年' => 3,
        '3〜5年' => 5,
        '5〜10年' => 10,
        '10年以上' => INF,
    ];

    /**
     * Skills that appear in the pool, most common first.
     *
     * @return list<string>
     */
    public function skills(Position $position): array
    {
        return $this->skillsIn($this->pool($position));
    }

    /**
     * @param  Collection<int, Candidate>  $pool
     * @return list<string>
     */
    private function skillsIn(Collection $pool): array
    {
        return $pool
            ->flatMap(fn (Candidate $c) => array_keys($c->skill_years ?? []))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->all();
    }

    /**
     * How many candidates have how many years in one skill.
     */
    public function skillExperience(Position $position, string $skill): Distribution
    {
        return $this->skillExperienceIn($this->pool($position), $skill);
    }

    /**
     * Experience distributions for every skill in the pool, most common skill
     * first, from a single query. Lets the page switch skills without a reload.
     *
     * @return array<string, Distribution>
     */
    public function skillExperiences(Position $position): array
    {
        $pool = $this->pool($position);

        return collect($this->skillsIn($pool))
            ->mapWithKeys(fn (string $skill) => [$skill => $this->skillExperienceIn($pool, $skill)])
            ->all();
    }

    /**
     * @param  Collection<int, Candidate>  $pool
     */
    private function skillExperienceIn(Collection $pool, string $skill): Distribution
    {
        $years = $pool
            ->map(fn (Candidate $c) => $c->skill_years[$skill] ?? null)
            ->filter(fn ($y) => is_numeric($y))
            ->map(fn ($y) => (float) $y)
            ->values();

        $counts = array_fill_keys(array_keys(self::YEAR_BUCKETS), 0);

        foreach ($years as $y) {
            foreach (self::YEAR_BUCKETS as $label => $upper) {
                if ($y < $upper) {
                    $counts[$label]++;
                    break;
                }
            }
        }

        return new Distribution(
            labels: array_keys($counts),
            counts: array_values($counts),
            sample: $years->count(),
            median: $this->median($years),
            notApplicable: $pool->count() - $years->count(),
        );
    }

    /**
     * Expected salary (万円) in 100万円-wide bands, with empty bands kept so
     * the histogram has no gaps.
     */
    public function expectedSalary(Position $position): Distribution
    {
        $pool = $this->pool($position);

        $salaries = $pool->pluck('expected_salary')->filter()->map(fn ($s) => (int) $s)->values();

        $labels = [];
        $counts = [];

        if ($salaries->isNotEmpty()) {
            $first = intdiv($salaries->min(), 100) * 100;
            $last = intdiv($salaries->max(), 100) * 100;

            for ($band = $first; $band <= $last; $band += 100) {
                $labels[] = number_format($band).'万円台';
                $counts[] = $salaries->filter(fn ($s) => $s >= $band && $s < $band + 100)->count();
            }
        }

        return new Distribution(
            labels: $labels,
            counts: $counts,
            sample: $salaries->count(),
            median: $this->median($salaries),
            notApplicable: $pool->count() - $salaries->count(),
        );
    }

    /**
     * Final education level (最終学歴), every level shown in order even when
     * nobody holds it. `median` is the rank of the median level; turn it back
     * into a level with EducationLevel::fromRank().
     */
    public function educationLevel(Position $position): Distribution
    {
        $pool = $this->pool($position);

        $levels = $pool->pluck('education_level')->filter()->values();
        $counts = $levels->countBy(fn (EducationLevel $l) => $l->value);

        // Lower median, so the result is always a real level rather than
        // a point halfway between two.
        $ranks = $levels->map(fn (EducationLevel $l) => $l->rank())->sort()->values();
        $median = $ranks->isEmpty() ? null : (float) $ranks[intdiv($ranks->count() - 1, 2)];

        return new Distribution(
            labels: array_map(fn (EducationLevel $l) => $l->label(), EducationLevel::cases()),
            counts: array_map(fn (EducationLevel $l) => $counts[$l->value] ?? 0, EducationLevel::cases()),
            sample: $levels->count(),
            median: $median,
            notApplicable: $pool->count() - $levels->count(),
        );
    }

    /**
     * @return Collection<int, Candidate>
     */
    private function pool(Position $position): Collection
    {
        return $position->candidates()->get(['id', 'skill_years', 'expected_salary', 'education_level']);
    }

    /**
     * @param  Collection<int, int|float>  $values
     */
    private function median(Collection $values): ?float
    {
        return $values->isEmpty() ? null : (float) $values->median();
    }
}
