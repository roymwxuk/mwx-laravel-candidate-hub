<?php

namespace App\Models;

use App\Enums\ResumeStatus;
use Database\Factories\ResumeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uploaded_by', 'candidate_id', 'original_name', 'path', 'mime_type', 'size', 'status',
    'extracted_data', 'warnings', 'raw_response', 'extractor', 'error_message',
    'processed_at', 'confirmed_by', 'confirmed_at',
])]
class ResumeDocument extends Model
{
    /** @use HasFactory<ResumeDocumentFactory> */
    use HasFactory;

    /**
     * Personal data stays on the server-side record only.
     *
     * @var list<string>
     */
    protected $hidden = ['raw_response', 'path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResumeStatus::class,
            'extracted_data' => 'array',
            'warnings' => 'array',
            'raw_response' => 'array',
            'processed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function isAwaitingReview(): bool
    {
        return $this->status === ResumeStatus::Extracted;
    }
}
