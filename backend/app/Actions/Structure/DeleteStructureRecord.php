<?php

namespace App\Actions\Structure;

use App\Models\Contracts\GuardsDeletion;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an organisation-structure record only while nothing references it.
 * References are counted across the whole company (data scopes off), so a
 * restricted admin cannot orphan records they do not see. In-use records are
 * deactivated instead (ChangeStructureRecordStatus).
 */
class DeleteStructureRecord
{
    public function __construct(private readonly DataScopeManager $dataScopes) {}

    /**
     * @throws ValidationException when the record is still in use
     */
    public function handle(Model&GuardsDeletion $record): void
    {
        $usages = array_filter($this->dataScopes->withoutScoping(fn (): array => $record->usageCounts()));

        if ($usages !== []) {
            $parts = [];

            foreach ($usages as $key => $count) {
                $parts[] = trans_choice('structure.usage.'.$key, $count, ['count' => $count]);
            }

            throw ValidationException::withMessages([
                'record' => __('structure.messages.in_use', ['name' => $record->name, 'usages' => implode(__('structure.messages.list_separator'), $parts)]),
            ]);
        }

        $record->delete();
    }
}
