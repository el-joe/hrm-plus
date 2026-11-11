<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The only writer of the audit log.
 *
 * - Models that `use Auditable` are logged automatically (created / updated /
 *   deleted / restored).
 * - Everything else (logins, exports, approvals, sensitive-data views,
 *   permission changes) calls log() with a dotted event name such as
 *   "payroll.exported" or "employee.salary_viewed".
 *
 * Secrets never reach the table: see config/audit.php.
 */
class AuditLogger
{
    /**
     * Record a custom event.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $meta
     */
    public function log(
        string $event,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        array $meta = [],
        ?User $actor = null,
    ): AuditLog {
        $hidden = array_merge(
            $subject?->getHidden() ?? [],
            $subject !== null && method_exists($subject, 'getAuditRedact') ? $subject->getAuditRedact() : [],
        );
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'user_id' => ($actor ?? $this->currentUser())?->getKey(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->redact($oldValues, $hidden) ?: null,
            'new_values' => $this->redact($newValues, $hidden) ?: null,
            'meta' => $this->redact($meta) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 500, '') ?: null,
            'url' => $request ? Str::limit($request->url(), 2000, '') : null,
        ]);
    }

    public function modelCreated(Model $model): void
    {
        $this->log('created', $model, newValues: $this->auditableAttributes($model, $model->getAttributes()));
    }

    public function modelUpdated(Model $model): void
    {
        $changes = $this->auditableAttributes($model, $model->getChanges());

        if ($changes === []) {
            return;
        }

        $original = [];

        foreach (array_keys($changes) as $attribute) {
            $original[$attribute] = $model->getRawOriginal($attribute);
        }

        $this->log('updated', $model, $original, $changes);
    }

    public function modelDeleted(Model $model): void
    {
        $this->log('deleted', $model, oldValues: $this->auditableAttributes($model, $model->getRawOriginal()));
    }

    public function modelRestored(Model $model): void
    {
        $this->log('restored', $model);
    }

    /**
     * Replace the value of every secret-looking key, at any depth.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $hidden  Extra exact keys to redact (a model's $hidden).
     * @return array<string, mixed>
     */
    public function redact(array $values, array $hidden = []): array
    {
        $placeholder = config('audit.redacted_placeholder');

        foreach ($values as $key => $value) {
            if (is_string($key) && (in_array($key, $hidden, true) || $this->isSecretKey($key))) {
                $values[$key] = $placeholder;
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    /**
     * Whether a key name looks like it holds a secret (config/audit.php).
     */
    public function isSecretKey(string $key): bool
    {
        return Str::contains(Str::lower($key), config('audit.redacted_fragments'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function auditableAttributes(Model $model, array $attributes): array
    {
        $excluded = array_merge(
            config('audit.ignored_attributes'),
            method_exists($model, 'getAuditExclude') ? $model->getAuditExclude() : [],
        );

        return array_diff_key($attributes, array_flip($excluded));
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user;
        }

        foreach (['admin', 'employee'] as $guard) {
            if (($user = Auth::guard($guard)->user()) instanceof User) {
                return $user;
            }
        }

        return null;
    }
}
