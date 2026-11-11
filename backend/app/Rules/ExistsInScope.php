<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * The value is the id of a record the signed-in admin can see (Eloquent
 * query, so data scopes apply, unlike Rule::exists) and, unless it is the
 * value the record already had, an active one.
 *
 *     'branch_id' => ['nullable', 'integer', new ExistsInScope(Branch::class, keep: $department->branch_id)]
 */
class ExistsInScope implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(
        private readonly string $model,
        private readonly int|string|null $keep = null,
        private readonly bool $activeOnly = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail('validation.exists')->translate();

            return;
        }

        $query = $this->model::query()->whereKey($value);

        if ($this->activeOnly && (string) $value !== (string) $this->keep) {
            // HasActiveFlag models: is_active; employees: status = active.
            $query->active();
        }

        if (! $query->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
