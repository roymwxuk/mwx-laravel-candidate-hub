<?php

namespace App\Http\Requests;

/**
 * The public application form. Same fields and rules as HR's review form
 * (inherited), except that anyone may submit it, the role comes from the
 * URL rather than a dropdown, an email address is required so HR can reply,
 * and the applicant must agree to how their data is handled.
 */
class ApplicationRequest extends ConfirmResumeRequest
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
        $rules = parent::rules();
        // HR-only fields: an applicant never sets the role, the source or the HR note.
        unset($rules['position_id'], $rules['referral_source'], $rules['hr_note']);

        return [
            ...$rules,
            'email' => ['required', 'email', 'max:255'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...parent::attributes(), 'consent' => '個人情報の取り扱いへの同意'];
    }
}
