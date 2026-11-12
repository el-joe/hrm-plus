<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use LogicException;

/**
 * An append-only record of who did what. Write through
 * App\Services\Audit\AuditLogger, never directly.
 */
#[Fillable(['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'meta', 'ip_address', 'user_agent', 'url'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit logs cannot be deleted through the application.'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'meta' => 'array',
        ];
    }

    /**
     * The translated label of an event key (lang/{locale}/audit.php, flat keys
     * because event names contain dots). Keys
     * without a translation are humanised: "payroll.run_created" becomes
     * "Payroll › Run created".
     */
    public static function eventLabel(string $event): string
    {
        $labels = Lang::get('audit.events');

        if (is_array($labels) && is_string($labels[$event] ?? null)) {
            return $labels[$event];
        }

        return collect(explode('.', $event))
            ->map(fn (string $part): string => Str::ucfirst(str_replace('_', ' ', $part)))
            ->implode(' › ');
    }

    public function label(): string
    {
        return self::eventLabel((string) $this->event);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
