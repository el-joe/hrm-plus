<?php

namespace App\Models;

use Database\Factories\HrRequestEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of a request's history (written by App\Services\HrRequests\HrRequestHistory).
 */
#[Fillable(['hr_request_id', 'type', 'from_value', 'to_value', 'note', 'visible_to_employee', 'actor_id', 'created_at'])]
class HrRequestEvent extends Model
{
    /** @use HasFactory<HrRequestEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['visible_to_employee' => 'boolean'];
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    /**
     * Human sentence for the timeline.
     */
    public function description(): string
    {
        $status = fn (?string $value): string => $value === null ? '—' : __('hr_requests.enums.statuses.'.$value);

        return match ($this->type) {
            'status_changed' => __('hr_requests.history.status_changed', ['from' => $status($this->from_value), 'to' => $status($this->to_value)]),
            'escalated' => __('hr_requests.history.escalated', ['level' => $this->to_value]),
            'priority_changed' => __('hr_requests.history.priority_changed', [
                'from' => __('hr_requests.enums.priorities.'.$this->from_value), 'to' => __('hr_requests.enums.priorities.'.$this->to_value),
            ]),
            'assigned' => __('hr_requests.history.assigned', ['name' => $this->to_value ?? '—']),
            default => __('hr_requests.history.'.$this->type),
        };
    }
}
