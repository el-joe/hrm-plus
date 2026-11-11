<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored override of a setting declared in config/settings.php. Read and
 * write through App\Services\Settings\SettingsService so the cache stays valid.
 */
#[Fillable(['key', 'group', 'value'])]
class Setting extends Model
{
    use Auditable;

    /**
     * A setting whose key looks secret (e.g. "notifications.smtp_password")
     * is audited as changed, but its value never reaches the audit log.
     *
     * @return list<string>
     */
    public function getAuditRedact(): array
    {
        return app(AuditLogger::class)->isSecretKey((string) $this->key) ? ['value'] : [];
    }
}
