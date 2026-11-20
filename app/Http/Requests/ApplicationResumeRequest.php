<?php

namespace App\Http\Requests;

/**
 * A resume an applicant uploads to auto-fill the public form. It is sent to
 * the AI extractor straight away, so consent is required before upload, not
 * only on the final submit.
 */
class ApplicationResumeRequest extends StoreResumeRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'consent' => ['accepted']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...parent::attributes(), 'consent' => '個人情報の取り扱いへの同意'];
    }
}
