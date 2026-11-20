<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\WorkHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkHistory>
 */
class WorkHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-10 years', '-2 years');

        return [
            'candidate_id' => Candidate::factory(),
            'company' => fake()->company(),
            'title' => 'エンジニア',
            'started_on' => $start->format('Y-m'),
            'ended_on' => null,
            'description' => 'Webアプリケーションの開発・運用を担当。',
            'sort_order' => 0,
        ];
    }
}
