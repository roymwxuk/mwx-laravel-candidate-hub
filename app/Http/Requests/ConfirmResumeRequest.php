<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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

        $this->merge(['skills' => $skills, 'work_histories' => $histories]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'name' => ['required', 'string', 'max:100'],
            'name_kana' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-() ]{6,20}$/'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'skills' => ['array', 'max:30'],
            'skills.*' => ['string', 'max:50'],
            'work_histories' => ['array', 'max:20'],
            'work_histories.*.company' => ['required', 'string', 'max:200'],
            'work_histories.*.title' => ['nullable', 'string', 'max:200'],
            'work_histories.*.started_on' => ['nullable', 'date_format:Y-m'],
            'work_histories.*.ended_on' => ['nullable', 'date_format:Y-m'],
            'work_histories.*.description' => ['nullable', 'string', 'max:2000'],
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
            'name' => '氏名',
            'name_kana' => 'ふりがな',
            'email' => 'メールアドレス',
            'phone' => '電話番号',
            'summary' => '概要',
            'skills' => 'スキル',
            'skills.*' => 'スキル',
            'work_histories.*.company' => '会社名',
            'work_histories.*.title' => '役職',
            'work_histories.*.started_on' => '開始年月',
            'work_histories.*.ended_on' => '終了年月',
            'work_histories.*.description' => '業務内容',
        ];
    }
}
