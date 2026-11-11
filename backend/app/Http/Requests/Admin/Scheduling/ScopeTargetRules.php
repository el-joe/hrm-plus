<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Enums\AttendanceScope;
use App\Rules\ExistsInScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared "applies to" fields of schedule and policy assignments: scope_type
 * plus one select per type (scope_ids[employee], scope_ids[branch], ...), so
 * the form works without JavaScript. The chosen record must be inside the
 * admin's data scope.
 *
 * @mixin FormRequest
 */
trait ScopeTargetRules
{
    /**
     * @param  list<AttendanceScope>  $allowed
     * @return array<string, mixed>
     */
    protected function scopeRules(array $allowed, ?int $keep = null): array
    {
        $type = AttendanceScope::tryFrom((string) $this->input('scope_type'));
        $rules = ['scope_type' => ['required', Rule::in(array_map(fn (AttendanceScope $scope): string => $scope->value, $allowed))]];

        if ($type !== null && $type !== AttendanceScope::Company && in_array($type, $allowed, true)) {
            $rules['scope_ids.'.$type->value] = ['required', 'integer', new ExistsInScope($type->modelClass(), keep: $keep)];
        }

        return $rules;
    }

    public function scopeType(): AttendanceScope
    {
        return AttendanceScope::from($this->input('scope_type'));
    }

    public function scopeId(): ?int
    {
        $type = $this->scopeType();

        return $type === AttendanceScope::Company ? null : (int) $this->input('scope_ids.'.$type->value);
    }
}
