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

    /**
     * The text under one 【heading】 of the job description, e.g.
     * section('想定年収') → "500万円〜800万円（…）". Null if there is no such heading.
     */
    public function section(string $heading): ?string
    {
        $pattern = '/【'.preg_quote($heading, '/').'】[^\S\n]*\n(.*?)(?=\n\s*【|\z)/su';

        if (! preg_match($pattern, (string) $this->description, $m)) {
            return null;
        }

        return trim($m[1]) ?: null;
    }

    /**
     * First line of a section, without a leading "・" bullet: short enough for a card.
     */
    public function sectionHeadline(string $heading): ?string
    {
        $first = strtok((string) $this->section($heading), "\n");

        return $first === false ? null : (ltrim(trim($first), '・') ?: null);
    }
}
