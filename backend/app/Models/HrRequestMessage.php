<?php

namespace App\Models;

use App\Enums\HrRequestMessageType;
use App\Models\Concerns\Auditable;
use Database\Factories\HrRequestMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry of a request thread: an employee-visible reply, an HR-only internal note or a system line.
 * Audited with the body redacted.
 */
#[Fillable(['hr_request_id', 'type', 'body', 'author_id', 'from_employee'])]
class HrRequestMessage extends Model
{
    /** @use HasFactory<HrRequestMessageFactory> */
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
        return ['type' => HrRequestMessageType::class, 'from_employee' => 'boolean'];
    }

    /**
     * @return BelongsTo<HrRequest, $this>
     */
    public function hrRequest(): BelongsTo
    {
        return $this->belongsTo(HrRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    /**
     * @return HasMany<HrRequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(HrRequestAttachment::class);
    }

    public function isInternal(): bool
    {
        return $this->type === HrRequestMessageType::InternalNote;
    }
}
