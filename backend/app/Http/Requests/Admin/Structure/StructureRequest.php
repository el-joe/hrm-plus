<?php

namespace App\Http\Requests\Admin\Structure;

use App\Rules\ExistsInScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared rules for creating and updating organisation-structure records:
 * the policy decides access, codes are unique and upper-cased, names exist
 * in Arabic and English, and foreign keys must point at records the admin can
 * see (active ones, unless the record already used the value).
 */
abstract class StructureRequest extends FormRequest
{
    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * Route parameter holding the record being edited.
     */
    abstract protected function routeKey(): string;

    public function authorize(): bool
    {
        $record = $this->record();

        return $record ? $this->user()->can('update', $record) : $this->user()->can('create', $this->modelClass());
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
        }
    }

    public function record(): ?Model
    {
        $record = $this->route($this->routeKey());

        return $record instanceof Model ? $record : null;
    }

    /**
     * @return array<string, list<ValidationRule|Closure|Unique|string>>
     */
    protected function identityRules(): array
    {
        $table = (new ($this->modelClass()))->getTable();

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique($table, 'code')->ignore($this->record())],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A foreign key to a record the admin can see; inactive targets are only
     * accepted when the record already pointed at them.
     *
     * @param  class-string<Model>  $model
     */
    protected function inScope(string $model, string $attribute): ExistsInScope
    {
        return new ExistsInScope($model, keep: $this->record()?->getAttribute($attribute));
    }

    /**
     * Blocks hierarchy cycles: the parent may not be the record itself or one of its descendants.
     */
    protected function notOwnDescendant(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $record = $this->record();

            if ($record !== null && method_exists($record, 'canHaveParent') && ! $record->canHaveParent($value)) {
                $fail(__('structure.messages.hierarchy_cycle'));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('structure.fields');
    }

    /**
     * Validated data with switches cast to booleans. The forms always send
     * the switches (hidden "0" + checkbox); a switch that is absent keeps the
     * stored value, or the column default on create.
     *
     * @param  list<string>  $booleans
     * @return array<string, mixed>
     */
    protected function withBooleans(array $booleans): array
    {
        $data = $this->validated();

        foreach ($booleans as $boolean) {
            if ($this->has($boolean)) {
                $data[$boolean] = $this->boolean($boolean);
            }
        }

        return $data;
    }
}
