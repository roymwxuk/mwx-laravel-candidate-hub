<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\Stage;
use App\Exceptions\InvalidStageTransition;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable(['position_id', 'name', 'name_kana', 'email', 'phone', 'summary', 'skills', 'expected_salary', 'education_level', 'skill_years', 'stage', 'created_by'])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'expected_salary' => 'integer',
            'education_level' => EducationLevel::class,
            'skill_years' => 'array',
            'stage' => Stage::class,
        ];
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return HasMany<WorkHistory, $this>
     */
    public function workHistories(): HasMany
    {
        return $this->hasMany(WorkHistory::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function interviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'candidate_interviewer')->withTimestamps();
    }

    /**
     * @return HasOne<ResumeDocument, $this>
     */
    public function resumeDocument(): HasOne
    {
        return $this->hasOne(ResumeDocument::class)->latestOfMany();
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Interviewers only ever see candidates they are assigned to.
     *
     * @param  Builder<Candidate>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->canManageCandidates()) {
            return;
        }

        $query->whereHas('interviewers', fn (Builder $q) => $q->whereKey($user->getKey()));
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->interviewers()->whereKey($user->getKey())->exists();
    }

    /**
     * Move the candidate to the next stage, enforcing the allowed transitions
     * and writing an audit log entry in the same transaction.
     *
     * @throws InvalidStageTransition
     */
    public function transitionTo(Stage $next, User $by, ?string $note = null): void
    {
        $from = $this->stage;

        if (! $from->canTransitionTo($next)) {
            throw InvalidStageTransition::between($from, $next);
        }

        DB::transaction(function () use ($from, $next, $by, $note) {
            $this->update(['stage' => $next]);

            AuditLog::record('stage.changed', $this, $by, [
                'from' => $from->value,
                'to' => $next->value,
                'note' => $note,
            ]);
        });
    }
}
