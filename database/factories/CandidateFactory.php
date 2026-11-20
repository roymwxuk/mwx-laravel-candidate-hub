<?php

namespace Database\Factories;

use App\Enums\Stage;
use App\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'name_kana' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '090-'.fake()->numerify('####-####'),
            'summary' => 'バックエンドエンジニアとしてWebサービスの設計・開発に従事。',
            'skills' => fake()->randomElements(['PHP', 'Laravel', 'Go', 'TypeScript', 'AWS', 'MySQL', 'Docker', 'Python'], 3),
            'stage' => Stage::DocumentScreening,
        ];
    }

    public function inStage(Stage $stage): static
    {
        return $this->state(['stage' => $stage]);
    }
}
