<?php

namespace App\Models;

use App\Enums\DisciplinaryNoteType;
use App\Models\Concerns\Auditable;
use Database\Factories\DisciplinaryCaseNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An investigation record on a disciplinary case (note, interview, finding).
 * Restricted like the case; audited with the body redacted.
 */
#[Fillable(['disciplinary_case_id', 'type', 'body', 'created_by'])]
class DisciplinaryCaseNote extends Model
{
    /** @use HasFactory<DisciplinaryCaseNoteFactory> */
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected array $auditRedact = ['body'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => DisciplinaryNoteType::class];
    }

    /**
     * @return BelongsTo<DisciplinaryCase, $this>
     */
    public function disciplinaryCase(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
