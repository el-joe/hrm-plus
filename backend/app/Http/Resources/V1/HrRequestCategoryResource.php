<?php

namespace App\Http\Resources\V1;

use App\Models\HrRequestCategory;
use App\Models\HrRequestCategoryField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A request category the employee may choose (mobile app), with its extra fields.
 *
 * @mixin HrRequestCategory
 */
class HrRequestCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'requires_attachment' => $this->requires_attachment,
            'requires_approval' => $this->requires_approval,
            'requires_description' => ! $this->isLetter() && ! $this->isProfileChange(),
            'sla_hours' => $this->slaHours(),
            'default_priority' => $this->default_priority->value,
            'letter_languages' => $this->isLetter() ? ['ar', 'en'] : [],
            'fields' => $this->fields->map(fn (HrRequestCategoryField $field): array => [
                'key' => $field->key,
                'label' => $field->label(),
                'type' => $field->type->value,
                'options' => $field->options ?? [],
                'required' => $field->is_required,
            ])->values()->all(),
        ];
    }
}
