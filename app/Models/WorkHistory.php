<?php

namespace App\Models;

use Database\Factories\WorkHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company', 'title', 'started_on', 'ended_on', 'description', 'sort_order'])]
class WorkHistory extends Model
{
    /** @use HasFactory<WorkHistoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function period(): string
    {
        $start = $this->started_on ? str_replace('-', '/', $this->started_on) : '?';
        $end = $this->ended_on ? str_replace('-', '/', $this->ended_on) : '現在';

        return "{$start} 〜 {$end}";
    }
}
