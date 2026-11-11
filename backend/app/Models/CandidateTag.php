<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Free-form label on candidates ("senior", "arabic speaker", ...).
 */
#[Fillable(['name'])]
class CandidateTag extends Model
{
    /**
     * @return BelongsToMany<Candidate, $this>
     */
    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'candidate_tag');
    }
}
