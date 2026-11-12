<?php

namespace App\Actions\HrRequests;

use App\Models\HrRequestCategory;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a request category with its extra fields (fields not in the submission are removed;
 * answers already given keep their copied labels).
 */
class SaveHrRequestCategory
{
    /**
     * @param  array<string, mixed>  $data  validated category attributes
     * @param  list<array{key: string, label_ar: string, label_en: string, type: string, options?: ?string, is_required?: bool}>  $fields
     */
    public function handle(HrRequestCategory $category, array $data, array $fields): HrRequestCategory
    {
        return DB::transaction(function () use ($category, $data, $fields): HrRequestCategory {
            $category->fill($data)->save();
            $keep = [];

            foreach (array_values($fields) as $index => $field) {
                $options = $field['type'] === 'select'
                    ? array_values(array_filter(array_map('trim', preg_split('/\R|,/', (string) ($field['options'] ?? '')) ?: [])))
                    : null;

                $row = $category->fields()->updateOrCreate(['key' => $field['key']], [
                    'label_ar' => $field['label_ar'],
                    'label_en' => $field['label_en'],
                    'type' => $field['type'],
                    'options' => $options,
                    'is_required' => (bool) ($field['is_required'] ?? false),
                    'sort_order' => $index,
                ]);
                $keep[] = $row->id;
            }

            $category->fields()->whereNotIn('id', $keep)->delete();

            return $category;
        });
    }
}
