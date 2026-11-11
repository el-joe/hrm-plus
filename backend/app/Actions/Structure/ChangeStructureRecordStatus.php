<?php

namespace App\Actions\Structure;

use Illuminate\Database\Eloquent\Model;

/**
 * Activates or deactivates an organisation-structure record. The change is
 * audited by the model's Auditable trait (is_active old / new value).
 * Deactivated records keep their existing references but are no longer
 * offered for new assignments.
 */
class ChangeStructureRecordStatus
{
    public function handle(Model $record, bool $active): Model
    {
        $record->forceFill(['is_active' => $active])->save();

        return $record;
    }
}
