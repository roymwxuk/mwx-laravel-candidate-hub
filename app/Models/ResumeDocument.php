<?php

namespace App\Models;

use App\Enums\ResumeSource;
use App\Enums\ResumeStatus;
use App\Exceptions\InvalidResumeTransition;
use Database\Factories\ResumeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'uploaded_by', 'candidate_id', 'source', 'original_name', 'path', 'mime_type', 'size', 'status',
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
     * @var array<string, mixed>
     */
    protected $attributes = ['source' => 'staff'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResumeStatus::class,
            'source' => ResumeSource::class,
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

    protected static function booted(): void
    {
        // transitionTo() writes through the query builder, so a status change
        // arriving here came from a plain update()/save() and skipped the
        // state machine. Fail loudly instead of saving an unchecked state.
        static::updating(function (self $document) {
            if ($document->isDirty('status')) {
                throw new LogicException('ResumeDocument の status は transitionTo() で変更してください。');
            }
        });
    }

    /**
     * A draft HR should review: applicants review their own drafts on the
     * public form, so those never show up in HR's queue.
     */
    public function isAwaitingReview(): bool
    {
        return $this->status === ResumeStatus::Extracted && $this->source === ResumeSource::Staff;
    }

    /**
     * What to call the extractor on screen. Model names are not shown: an LLM
     * appears under its alias, or as "AI" when it has none.
     */
    public function extractorLabel(): ?string
    {
        if ($this->extractor === null || str_starts_with($this->extractor, 'mock-')) {
            return $this->extractor;
        }

        foreach (explode(',', (string) config('services.extractor.aliases')) as $pair) {
            [$name, $alias] = array_map('trim', explode('=', $pair, 2)) + [1 => ''];

            if ($name === $this->extractor && $alias !== '') {
                return $alias;
            }
        }

        return 'AI';
    }

    /**
     * Display code for the LLM reasoning effort this draft was extracted with;
     * the page does not name the effort itself. Null for the mock extractor and
     * for drafts made before the extractor reported it.
     */
    public function reasoningCode(): ?string
    {
        return match ($this->raw_response['reasoning_effort'] ?? null) {
            'low' => 'X1',
            'medium' => 'X2',
            'high' => 'X3',
            default => null,
        };
    }

    /**
     * @param  Builder<ResumeDocument>  $query
     */
    public function scopeUploadedByStaff(Builder $query): void
    {
        $query->where('source', ResumeSource::Staff->value);
    }

    /**
     * The only way to change `status` after creation. Rejects moves the state
     * machine does not allow, and applies the change as a compare-and-set
     * (`... WHERE status = <current>`), so a queue job and a person acting on
     * the same resume at the same time cannot both win.
     *
     * @param  array<string, mixed>  $attributes  other columns to save in the same update
     *
     * @throws InvalidResumeTransition
     */
    public function transitionTo(ResumeStatus $next, array $attributes = []): void
    {
        $from = $this->status;

        if (! $from->canTransitionTo($next)) {
            throw InvalidResumeTransition::between($from, $next);
        }

        // Fill through the model so casts (enum, JSON) produce the stored values.
        $this->forceFill([...$attributes, 'status' => $next])->updateTimestamps();

        $updated = static::query()
            ->whereKey($this->getKey())
            ->where('status', $from->value)
            ->update($this->getDirty());

        if ($updated === 0) {
            $this->refresh();

            throw InvalidResumeTransition::changedConcurrently($from, $next);
        }

        $this->syncOriginal();
    }
}
