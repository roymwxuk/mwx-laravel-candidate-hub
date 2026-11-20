<?php

namespace App\Enums;

/**
 * Where an uploaded resume came from, which decides who reviews the AI draft.
 */
enum ResumeSource: string
{
    /** Uploaded by HR in 履歴書取込; HR reviews the draft. */
    case Staff = 'staff';

    /** Uploaded by an applicant to auto-fill the public form; the applicant reviews it. */
    case Application = 'application';
}
