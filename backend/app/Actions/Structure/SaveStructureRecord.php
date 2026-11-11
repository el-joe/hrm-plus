<?php

namespace App\Actions\Structure;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an organisation-structure record from validated data.
 * Auditing happens in the model (Auditable). Saving a branch as the
 * headquarters moves the flag away from the previous headquarters, so there
 * is never more than one.
 */
class SaveStructureRecord
{
    /**
     * @template TModel of Model
     *
     * @param  TModel  $record
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function handle(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $record->fill($data)->save();

            if ($record instanceof Branch && $record->is_headquarters) {
                Branch::query()->withoutGlobalScopes()->whereKeyNot($record->getKey())->where('is_headquarters', true)
                    ->get()->each(fn (Branch $branch) => $branch->update(['is_headquarters' => false]));
            }

            return $record;
        });
    }
}
