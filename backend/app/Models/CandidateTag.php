<?php

namespace App\Models;

use Database\Factories\CandidateTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Free-form label on candidates ("senior", "arabic speaker", ...).
 */
#[Fillable(['name'])]
class CandidateTag extends Model
{
    /** @use HasFactory<CandidateTagFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Candidate, $this>
     */
    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'candidate_tag');
    }
}
