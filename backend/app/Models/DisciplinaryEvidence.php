<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supporting document of a disciplinary case, stored on the private disk
 * "disciplinary_files" (App\Services\Disciplinary\DisciplinaryFileStore) and
 * streamed only after the policy check; every download is logged.
 */
#[Fillable(['disciplinary_case_id', 'description', 'file_path', 'file_name', 'file_mime', 'file_size', 'uploaded_by'])]
class DisciplinaryEvidence extends Model
{
    use Auditable;

    protected $table = 'disciplinary_evidence';

    /**
     * @var list<string>
     */
    protected array $auditRedact = ['description', 'file_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['file_size' => 'integer'];
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
