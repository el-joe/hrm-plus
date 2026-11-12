<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CandidateNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal note on a candidate (never shown to the candidate).
 */
#[Fillable(['candidate_id', 'job_application_id', 'body', 'user_id'])]
class CandidateNote extends Model
{
    /** @use HasFactory<CandidateNoteFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
