<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Opt a model into automatic audit logging:
 *
 *     use Auditable;
 *
 * Every create, update, delete (and restore) writes an audit_logs row with the
 * old and new values. Secrets are redacted (config/audit.php plus the model's
 * $hidden). To leave noisy attributes out, declare
 * `protected array $auditExclude = ['column'];` on the model. To keep the
 * fact of a change but hide its value, declare
 * `protected array $auditRedact = ['column'];` or override getAuditRedact().
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => app(AuditLogger::class)->modelCreated($model));
        static::updated(fn (Model $model) => app(AuditLogger::class)->modelUpdated($model));
        static::deleted(fn (Model $model) => app(AuditLogger::class)->modelDeleted($model));

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(fn (Model $model) => app(AuditLogger::class)->modelRestored($model));
        }
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /**
     * Attributes that are skipped entirely by the audit log.
     *
     * @return list<string>
     */
    public function getAuditExclude(): array
    {
        return property_exists($this, 'auditExclude') ? $this->auditExclude : [];
    }

    /**
     * Attributes whose values are replaced by the redaction placeholder.
     *
     * @return list<string>
     */
    public function getAuditRedact(): array
    {
        return property_exists($this, 'auditRedact') ? $this->auditRedact : [];
    }
}
