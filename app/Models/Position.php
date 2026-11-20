<?php

namespace App\Models;

use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A job opening that candidates apply for.
 */
#[Fillable(['title', 'description'])]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    /**
     * @return HasMany<Candidate, $this>
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}
