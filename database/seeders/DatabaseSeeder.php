<?php

namespace Database\Seeders;

use App\Enums\EducationLevel;
use App\Enums\Role;
use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Position;
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

        $cto = $this->createCtoPosition();

        // CTO applicants: one per pipeline stage, so every badge colour shows.
        // [name, kana, stage, 希望年収(万円), 最終学歴, skill => years, 生年月日?]
        $samples = [
            ['佐藤 健', 'さとう けん', Stage::DocumentScreening, 1200, EducationLevel::Bachelor, ['PHP' => 12, 'Laravel' => 9, 'MySQL' => 12]],
            ['鈴木 美咲', 'すずき みさき', Stage::FirstInterview, 1400, EducationLevel::Master, ['Go' => 8, 'AWS' => 10, 'Terraform' => 6]],
            ['高橋 翔', 'たかはし しょう', Stage::FinalInterview, 1300, EducationLevel::Bachelor, ['TypeScript' => 9, 'Next.js' => 6, 'Node.js' => 10]],
            ['田中 優', 'たなか ゆう', Stage::Offer, 1600, EducationLevel::Doctorate, ['Python' => 14, 'FastAPI' => 5, 'LLM' => 3], '1981-09-14'],
            ['伊藤 誠', 'いとう まこと', Stage::Rejected, 1100, EducationLevel::Bachelor, ['Java' => 18, 'Spring' => 15]],
        ];

        foreach ($samples as $row) {
            [$name, $kana, $stage, $salary, $education, $skillYears] = $row;

            $candidate = Candidate::factory()
                ->hasWorkHistories(2)
                ->create([
                    'position_id' => $cto->id,
                    'name' => $name,
                    'name_kana' => $kana,
                    'stage' => $stage,
                    'skills' => array_keys($skillYears),
                    'expected_salary' => $salary,
                    'education_level' => $education,
                    'skill_years' => $skillYears,
                    'birth_date' => $row[6] ?? null,
                    'created_by' => $hr->id,
                ]);

            AuditLog::record('candidate.created_from_resume', $candidate, $hr, ['corrected_fields' => []]);
        }

        // The demo interviewer is assigned to two candidates only,
        // which makes the role-based visibility easy to see.
        Candidate::query()->whereIn('stage', [Stage::FirstInterview, Stage::FinalInterview])
            ->each(fn (Candidate $c) => $c->interviewers()->attach($interviewer));

        $this->seedRoleA($hr);
        $this->seedRolePdm($hr);
    }

    private function createCtoPosition(): Position
    {
        return Position::create([
            'title' => 'CTO（最高技術責任者）',
            'description' => <<<'JD'
                【業務内容】
                経営メンバーとして、自社HRサービスの技術戦略と開発組織全体をリードしていただきます。
                ・中長期の技術戦略・アーキテクチャ方針の策定
                ・開発組織（約30名）の採用、育成、評価制度の設計
                ・AI抽出エンジンを含むプロダクト基盤の技術的意思決定
                ・セキュリティ、個人情報保護、システム信頼性の統括
                ・経営会議への参画、事業計画と技術投資の整合

                【必須スキル】
                ・Webサービスの開発経験 10年以上
                ・エンジニア組織のマネジメント経験（20名規模以上）
                ・クラウド上での大規模システムの設計・運用経験

                【歓迎スキル】
                ・CTO／VPoE等の技術責任者としての経験
                ・HR領域またはBtoB SaaSでの経験
                ・機械学習／LLMを活用したプロダクト開発の経験

                【想定年収】
                1,200万円〜1,800万円（ストックオプション制度あり）

                【勤務地】
                東京都（リモートワーク併用可）
                JD,
        ]);
    }

    /**
     * Ten fixed (not random) applicants for one position, so the applicant
     * analytics charts look the same after every demo reset. All fictional.
     */
    private function seedRoleA(User $hr): void
    {
        $position = Position::create([
            'title' => 'バックエンドエンジニア（Role A）',
            'description' => <<<'JD'
                【業務内容】
                PHP / Laravel を中心とした自社HRサービスのバックエンド開発を担当していただきます。
                ・人事評価・採用管理機能の設計、実装、テスト
                ・外部サービス（AI抽出エンジン等）とのAPI連携
                ・AWS上でのインフラ運用、パフォーマンス改善

                【必須スキル】
                ・PHPを用いたWebアプリケーション開発経験 3年以上
                ・Laravel等のフレームワークを用いた開発経験
                ・MySQL等のRDBを用いたテーブル設計の経験

                【歓迎スキル】
                ・AWSでの運用経験
                ・Docker / CI を用いた開発環境・デプロイの整備経験
                ・Go / Python 等、PHP以外の言語での開発経験

                【想定年収】
                500万円〜800万円（経験・スキルを考慮のうえ決定）

                【勤務地】
                東京都（リモートワーク併用可）
                JD,
        ]);

        // [name, kana, stage, 希望年収(万円), 最終学歴, skill => years, [company, title, from, to|null]...]
        $applicants = [
            ['山本 大輔', 'やまもと だいすけ', Stage::DocumentScreening, 420, EducationLevel::Vocational, ['PHP' => 0.5, 'JavaScript' => 1, 'MySQL' => 0.5],
                [['株式会社デモウェブ', 'Webエンジニア', '2024-04', null]], '専門学校 HAL東京'],
            ['中村 さくら', 'なかむら さくら', Stage::DocumentScreening, 480, EducationLevel::Bachelor, ['PHP' => 2, 'Laravel' => 1.5, 'MySQL' => 2, 'Vue.js' => 1],
                [['株式会社サンプルソリューションズ', 'プログラマー', '2024-10', null]]],
            ['小林 拓也', 'こばやし たくや', Stage::Rejected, 500, EducationLevel::Bachelor, ['PHP' => 2.5, 'Laravel' => 2, 'Docker' => 1, 'MySQL' => 2.5],
                [['合同会社テストラボ', 'エンジニア', '2022-04', '2024-03'], ['株式会社デモコマース', 'バックエンドエンジニア', '2024-04', null]]],
            ['加藤 由美', 'かとう ゆみ', Stage::DocumentScreening, 550, EducationLevel::Bachelor, ['PHP' => 3, 'Laravel' => 3, 'AWS' => 1, 'MySQL' => 3, 'Docker' => 2],
                [['株式会社サンプルメディア', 'Webエンジニア', '2023-10', null]]],
            ['吉田 健太', 'よしだ けんた', Stage::FirstInterview, 580, EducationLevel::Associate, ['PHP' => 4, 'Laravel' => 3, 'AWS' => 2, 'MySQL' => 4, 'Go' => 0.5],
                [['株式会社デモシステムズ', 'プログラマー', '2020-04', '2023-03'], ['株式会社サンプルペイ', 'バックエンドエンジニア', '2023-04', null]]],
            ['山田 恵', 'やまだ めぐみ', Stage::DocumentScreening, 600, EducationLevel::Master, ['PHP' => 5, 'Laravel' => 4, 'AWS' => 3, 'MySQL' => 5, 'Docker' => 3],
                [['株式会社テストネット', 'エンジニア', '2019-04', '2022-09'], ['株式会社デモHR', 'バックエンドエンジニア', '2022-10', null]]],
            ['佐々木 亮', 'ささき りょう', Stage::FirstInterview, 650, EducationLevel::Bachelor, ['PHP' => 6, 'Laravel' => 5, 'AWS' => 4, 'MySQL' => 6, 'Redis' => 2],
                [['株式会社サンプルゲームス', 'サーバーサイドエンジニア', '2018-04', '2021-12'], ['株式会社デモクラウド', 'リードエンジニア', '2022-01', null]]],
            ['松本 直樹', 'まつもと なおき', Stage::FinalInterview, 700, EducationLevel::Master, ['PHP' => 7, 'Laravel' => 6, 'AWS' => 5, 'MySQL' => 7, 'Docker' => 4, 'TypeScript' => 2],
                [['株式会社テストシステム', 'エンジニア', '2017-04', '2020-03'], ['株式会社サンプルリクルート', 'テックリード', '2020-04', null]]],
            ['井上 彩', 'いのうえ あや', Stage::DocumentScreening, 780, EducationLevel::Bachelor, ['PHP' => 8, 'Laravel' => 7, 'AWS' => 6, 'MySQL' => 8, 'Go' => 2],
                [['株式会社デモソフト', 'プログラマー', '2016-04', '2019-06'], ['株式会社サンプルフィンテック', 'シニアエンジニア', '2019-07', null]]],
            ['木村 浩二', 'きむら こうじ', Stage::Rejected, 950, EducationLevel::Doctorate, ['PHP' => 12, 'Laravel' => 8, 'AWS' => 7, 'MySQL' => 12, 'Kubernetes' => 3],
                [['株式会社テストテクノロジー', 'エンジニア', '2012-04', '2018-03'], ['株式会社デモプラットフォーム', 'エンジニアリングマネージャー', '2018-04', null]]],
        ];

        $this->seedApplicants($position, $hr, $applicants, 'role-a',
            summary: 'PHP / Laravel を用いたWebサービスのバックエンド開発に従事。',
            historyDescription: 'Webアプリケーションの設計・開発・運用。',
        );
    }

    /**
     * Four fixed applicants for a product manager opening: just over the
     * analytics minimum sample, so the charts show what a thin pool looks like.
     */
    private function seedRolePdm(User $hr): void
    {
        $position = Position::create([
            'title' => 'プロダクトマネージャー（PdM）',
            'description' => <<<'JD'
                【業務内容】
                自社HRサービスのプロダクトマネージャーとして、採用管理領域の企画から改善までをリードしていただきます。
                ・顧客・社内ヒアリングをもとにした課題定義とロードマップ策定
                ・要件定義、仕様策定、優先順位付け
                ・エンジニア・デザイナーと連携したスクラム開発の推進
                ・利用データの分析とKPI設計、施策の効果検証

                【必須スキル】
                ・Webサービス／SaaSのプロダクトマネジメント経験 2年以上
                ・要件定義・仕様策定の経験
                ・SQL等を用いたデータ分析の経験

                【歓迎スキル】
                ・HR領域またはBtoB SaaSでの経験
                ・エンジニアとしての開発経験
                ・ユーザーリサーチ、UXデザインの経験

                【想定年収】
                650万円〜1,000万円（経験・スキルを考慮のうえ決定）

                【勤務地】
                東京都（リモートワーク併用可）
                JD,
        ]);

        // [name, kana, stage, 希望年収(万円), 最終学歴, skill => years, [company, title, from, to|null]...]
        $applicants = [
            ['清水 葵', 'しみず あおい', Stage::DocumentScreening, 650, EducationLevel::Bachelor, ['プロダクトマネジメント' => 2, '要件定義' => 3, 'SQL' => 1, 'Figma' => 2],
                [['株式会社デモアプリ', 'Webディレクター', '2020-04', '2023-03'], ['株式会社サンプルSaaS', 'プロダクトマネージャー', '2023-04', null]]],
            ['森 隆之', 'もり たかゆき', Stage::FirstInterview, 750, EducationLevel::Master, ['プロダクトマネジメント' => 4, '要件定義' => 5, 'SQL' => 4, 'Python' => 3, 'スクラム' => 3],
                [['株式会社テストデータ', 'データアナリスト', '2017-04', '2020-12'], ['株式会社デモマーケット', 'プロダクトマネージャー', '2021-01', null]]],
            ['池田 真理', 'いけだ まり', Stage::DocumentScreening, 850, EducationLevel::Bachelor, ['プロダクトマネジメント' => 6, '要件定義' => 7, 'SQL' => 3, 'ユーザーリサーチ' => 5, 'スクラム' => 4],
                [['株式会社サンプル人材', '企画職', '2014-04', '2018-09'], ['株式会社デモHRテック', 'シニアプロダクトマネージャー', '2018-10', null]]],
            ['橋本 修', 'はしもと おさむ', Stage::Rejected, 1000, EducationLevel::Master, ['プロダクトマネジメント' => 8, '要件定義' => 10, 'SQL' => 5, 'スクラム' => 6, 'PHP' => 4],
                [['株式会社テストウェア', 'エンジニア', '2010-04', '2015-03'], ['株式会社サンプルクラウド', 'プロダクト部長', '2015-04', null]]],
        ];

        $this->seedApplicants($position, $hr, $applicants, 'role-pdm',
            summary: 'BtoB SaaSのプロダクトマネージャーとして企画・要件定義・開発推進に従事。',
            historyDescription: 'プロダクトの企画・要件定義・開発推進。',
        );
    }

    /**
     * Each row may carry an optional 8th element: the school name (最終学歴の学校名).
     *
     * @param  list<array{0: string, 1: string, 2: Stage, 3: int, 4: EducationLevel, 5: array<string, int|float>, 6: list<array{string, string, string, string|null}>, 7?: string}>  $applicants
     */
    private function seedApplicants(Position $position, User $hr, array $applicants, string $emailPrefix, string $summary, string $historyDescription): void
    {
        foreach ($applicants as $i => $row) {
            [$name, $kana, $stage, $salary, $education, $skillYears, $histories] = $row;

            $candidate = Candidate::create([
                'position_id' => $position->id,
                'name' => $name,
                'name_kana' => $kana,
                'email' => sprintf('%s-%02d@example.com', $emailPrefix, $i + 1),
                'phone' => sprintf('090-%04d-%04d', $position->id, $i + 1),
                'summary' => $summary,
                'skills' => array_keys($skillYears),
                'expected_salary' => $salary,
                'education_level' => $education,
                'school_name' => $row[7] ?? null,
                'skill_years' => $skillYears,
                'stage' => $stage,
                'created_by' => $hr->id,
            ]);

            foreach ($histories as $order => [$company, $title, $from, $to]) {
                $candidate->workHistories()->create([
                    'company' => $company,
                    'title' => $title,
                    'started_on' => $from,
                    'ended_on' => $to,
                    'description' => $historyDescription,
                    'sort_order' => $order,
                ]);
            }

            AuditLog::record('candidate.created_from_resume', $candidate, $hr, ['corrected_fields' => []]);
        }
    }
}
