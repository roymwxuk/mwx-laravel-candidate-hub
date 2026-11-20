<?php

namespace App\Http\Requests;

use App\Enums\Stage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStage', $this->route('candidate'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(Stage::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['stage' => '選考ステージ', 'note' => 'メモ'];
    }
}
