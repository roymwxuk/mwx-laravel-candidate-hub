<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['name' => '管理 太郎', 'email' => 'admin@example.com', 'role' => Role::Admin]);
        $hr = User::factory()->create(['name' => '人事 花子', 'email' => 'hr@example.com', 'role' => Role::Hr]);
        $interviewer = User::factory()->create(['name' => '面接 一郎', 'email' => 'interviewer@example.com', 'role' => Role::Interviewer]);
        User::factory()->create(['name' => '面接 次郎', 'email' => 'interviewer2@example.com', 'role' => Role::Interviewer]);

        $samples = [
            ['佐藤 健', 'さとう けん', Stage::DocumentScreening, ['PHP', 'Laravel', 'MySQL']],
            ['鈴木 美咲', 'すずき みさき', Stage::FirstInterview, ['Go', 'AWS', 'Terraform']],
            ['高橋 翔', 'たかはし しょう', Stage::FinalInterview, ['TypeScript', 'Next.js', 'Node.js']],
            ['田中 優', 'たなか ゆう', Stage::Offer, ['Python', 'FastAPI', 'LLM']],
            ['伊藤 誠', 'いとう まこと', Stage::Rejected, ['Java', 'Spring']],
        ];

        foreach ($samples as [$name, $kana, $stage, $skills]) {
            $candidate = Candidate::factory()
                ->hasWorkHistories(2)
                ->create([
                    'name' => $name,
                    'name_kana' => $kana,
                    'stage' => $stage,
                    'skills' => $skills,
                    'created_by' => $hr->id,
                ]);

            AuditLog::record('candidate.created_from_resume', $candidate, $hr, ['corrected_fields' => []]);
        }

        // The demo interviewer is assigned to two candidates only,
        // which makes the role-based visibility easy to see.
        Candidate::query()->whereIn('stage', [Stage::FirstInterview, Stage::FinalInterview])
            ->each(fn (Candidate $c) => $c->interviewers()->attach($interviewer));
    }
}
