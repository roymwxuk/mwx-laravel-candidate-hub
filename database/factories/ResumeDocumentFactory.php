<?php

namespace Database\Factories;

use App\Enums\ResumeSource;
use App\Enums\ResumeStatus;
use App\Models\ResumeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeDocument>
 */
class ResumeDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploaded_by' => User::factory(),
            'original_name' => 'resume.pdf',
            'path' => 'resumes/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12_345,
            'status' => ResumeStatus::Uploaded,
        ];
    }

    /**
     * A document whose AI draft is ready for human review.
     *
     * @param  array<string, mixed>  $draft
     */
    public function extracted(array $draft = []): static
    {
        return $this->state(fn () => [
            'status' => ResumeStatus::Extracted,
            'extractor' => 'mock-rules-v1',
            'processed_at' => now(),
            'warnings' => [],
            'extracted_data' => array_merge([
                'name' => '山田 太郎',
                'name_kana' => 'やまだ たろう',
                'email' => 'taro@example.com',
                'phone' => '090-1234-5678',
                'summary' => 'Webエンジニア',
                'skills' => ['PHP', 'Laravel'],
                'work_histories' => [
                    ['company' => '株式会社サンプル', 'title' => 'エンジニア', 'started_on' => '2020-04', 'ended_on' => null, 'description' => null],
                ],
            ], $draft),
        ]);
    }

    /**
     * A document whose draft was reviewed and turned into a candidate.
     */
    public function confirmed(): static
    {
        return $this->extracted()->state(fn () => [
            'status' => ResumeStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Uploaded by an applicant on the public form (no staff uploader).
     */
    public function fromApplication(): static
    {
        return $this->state(['source' => ResumeSource::Application, 'uploaded_by' => null]);
    }

    public function failed(string $message = '抽出サービスに接続できません'): static
    {
        return $this->state(['status' => ResumeStatus::Failed, 'error_message' => $message]);
    }
}
