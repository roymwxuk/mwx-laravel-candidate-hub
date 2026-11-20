<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['candidate_id', 'user_id', 'action', 'properties'])]
class AuditLog extends Model
{
    /** Append-only: there is no updated_at. */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, ?Candidate $candidate, ?User $user, array $properties = []): self
    {
        return self::create([
            'candidate_id' => $candidate?->getKey(),
            'user_id' => $user?->getKey(),
            'action' => $action,
            'properties' => $properties,
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Human-readable description for the activity timeline.
     */
    public function description(): string
    {
        $p = $this->properties ?? [];

        return match ($this->action) {
            'candidate.created_from_resume' => '履歴書から候補者を登録しました',
            'stage.changed' => sprintf(
                '選考ステージを「%s」から「%s」に変更しました',
                \App\Enums\Stage::tryFrom($p['from'] ?? '')?->label() ?? '?',
                \App\Enums\Stage::tryFrom($p['to'] ?? '')?->label() ?? '?',
            ),
            'interviewer.assigned' => sprintf('面接官「%s」をアサインしました', $p['interviewer_name'] ?? '?'),
            'interviewer.removed' => sprintf('面接官「%s」のアサインを解除しました', $p['interviewer_name'] ?? '?'),
            default => $this->action,
        };
    }
}
