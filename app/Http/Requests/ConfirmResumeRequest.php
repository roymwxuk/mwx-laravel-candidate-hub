<?php

namespace App\Http\Requests;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Services\ResumeExtraction\ExtractionResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the *human-edited* version of the draft. The AI draft was already
 * sanitized, but the reviewer can type anything, so it is validated again.
 */
class ConfirmResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('confirm', $this->route('resume'));
    }

    protected function prepareForValidation(): void
    {
        // Skills are edited as a comma / newline separated text field.
        $skills = collect(preg_split('/[,、\n]+/u', (string) $this->input('skills_text', '')))
            ->map(fn (string $s) => trim($s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Empty work-history rows (e.g. a blank row the reviewer added) are ignored.
        $histories = collect($this->input('work_histories', []))
            ->filter(fn ($row) => is_array($row) && collect($row)->filter(fn ($v) => filled($v))->isNotEmpty())
            ->values()
            ->all();

        // 語学力 is one entry per line: an entry may itself contain commas ("英語（TOEIC 850点、ビジネス）").
        $languages = collect(preg_split('/\R/u', (string) $this->input('languages_text', '')))
            ->map(fn (string $s) => trim($s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge(['skills' => $skills, 'languages' => $languages, 'work_histories' => $histories]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'referral_source' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'name_kana' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-() ]{6,20}$/'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'skills' => ['array', 'max:30'],
            'skills.*' => ['string', 'max:50'],
            'languages' => ['array', 'max:10'],
            'languages.*' => ['string', 'max:100'],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'school_name' => ['nullable', 'string', 'max:200'],
            'major' => ['nullable', 'string', 'max:200'],
            'graduated_on' => ['nullable', 'date_format:Y-m'],
            'expected_salary' => ['nullable', 'integer', 'min:1', 'max:'.ExtractionResult::MAX_SALARY],
            'desired_location' => ['nullable', 'string', 'max:200'],
            'work_histories' => ['array', 'max:20'],
            'work_histories.*.company' => ['required', 'string', 'max:200'],
            'work_histories.*.title' => ['nullable', 'string', 'max:200'],
            'work_histories.*.started_on' => ['nullable', 'date_format:Y-m'],
            'work_histories.*.ended_on' => ['nullable', 'date_format:Y-m'],
            'work_histories.*.description' => ['nullable', 'string', 'max:2000'],
            'hr_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ($this->input('work_histories', []) as $i => $row) {
                    $start = $row['started_on'] ?? null;
                    $end = $row['ended_on'] ?? null;

                    // "YYYY-MM" strings compare correctly as plain strings.
                    if (is_string($start) && is_string($end) && $start !== '' && $end !== '' && $end < $start) {
                        $validator->errors()->add("work_histories.{$i}.ended_on", '終了年月は開始年月以降を指定してください。');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'position_id' => '応募職種',
            'referral_source' => '応募経路',
            'name' => '氏名',
            'name_kana' => 'ふりがな',
            'email' => 'メールアドレス',
            'phone' => '電話番号',
            'birth_date' => '生年月日',
            'summary' => '概要',
            'skills' => 'スキル',
            'skills.*' => 'スキル',
            'languages' => '語学力',
            'languages.*' => '語学力',
            'education_level' => '最終学歴',
            'gender' => '性別',
            'school_name' => '学校名',
            'major' => '学部・専攻',
            'graduated_on' => '卒業年月',
            'expected_salary' => '希望年収',
            'desired_location' => '希望勤務地',
            'work_histories.*.company' => '会社名',
            'work_histories.*.title' => '役職',
            'work_histories.*.started_on' => '開始年月',
            'work_histories.*.ended_on' => '終了年月',
            'work_histories.*.description' => '業務内容',
            'hr_note' => '備考',
        ];
    }
}
