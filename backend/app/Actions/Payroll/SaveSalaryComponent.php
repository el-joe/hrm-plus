<?php

namespace App\Actions\Payroll;

use App\Enums\PayCalculationType;
use App\Models\SalaryComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a catalogue component. Only the values its calculation
 * type uses are kept (a fixed component has no rate, ...), a percentage base
 * may not lead back to the component itself, and there is one active basic
 * component. Components are configuration: payroll stores the values it
 * used, so editing a component never changes a finalised run.
 */
class SaveSalaryComponent
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(?SalaryComponent $component, array $data): SalaryComponent
    {
        $type = PayCalculationType::from($data['calculation_type']);
        $data = $this->normalise($type, $data);

        return DB::transaction(function () use ($component, $type, $data): SalaryComponent {
            if ($type === PayCalculationType::Basic) {
                $other = SalaryComponent::query()->where('calculation_type', PayCalculationType::Basic)
                    ->when($component?->exists, fn ($query) => $query->whereKeyNot($component->id))->exists();

                if ($other) {
                    throw ValidationException::withMessages(['calculation_type' => __('payroll.validation.one_basic')]);
                }
            }

            $component ??= new SalaryComponent;
            $component->fill($data);

            if ($component->exists && $data['base_component_id'] !== null && $this->leadsTo((int) $data['base_component_id'], $component->id)) {
                throw ValidationException::withMessages(['base_component_id' => __('payroll.validation.base_cycle')]);
            }

            $component->save();

            return $component;
        });
    }

    /**
     * Whether following base components from $start reaches $target.
     */
    public function leadsTo(int $start, int $target): bool
    {
        $seen = [];
        $current = $start;

        while ($current !== null && ! in_array($current, $seen, true)) {
            if ($current === $target) {
                return true;
            }

            $seen[] = $current;
            $next = SalaryComponent::query()->whereKey($current)->value('base_component_id');
            $current = $next === null ? null : (int) $next;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(PayCalculationType $type, array $data): array
    {
        $usesAmount = in_array($type, [PayCalculationType::Fixed, PayCalculationType::LateMinutes, PayCalculationType::EarlyLeaveMinutes,
            PayCalculationType::AbsentDays, PayCalculationType::OvertimeHours, PayCalculationType::UnpaidLeaveDays], true);
        $usesRate = $type === PayCalculationType::Percentage || $type->isQuantityBased();

        $data['amount'] = $usesAmount && filled($data['amount'] ?? null) ? $data['amount'] : null;
        $data['rate'] = $usesRate && filled($data['rate'] ?? null) ? $data['rate'] : null;
        $data['base_component_id'] = $type === PayCalculationType::Percentage && filled($data['base_component_id'] ?? null) ? (int) $data['base_component_id'] : null;
        $data['use_policy_multiplier'] = $type === PayCalculationType::OvertimeHours && (bool) ($data['use_policy_multiplier'] ?? false);
        $data['statutory_type'] = $type === PayCalculationType::Statutory ? ($data['statutory_type'] ?? null) : null;

        if ($type === PayCalculationType::Percentage && $data['rate'] === null) {
            throw ValidationException::withMessages(['rate' => __('payroll.validation.rate_required')]);
        }

        if ($type === PayCalculationType::Fixed && $data['amount'] === null) {
            throw ValidationException::withMessages(['amount' => __('payroll.validation.amount_required')]);
        }

        if ($type === PayCalculationType::Statutory && $data['statutory_type'] === null) {
            throw ValidationException::withMessages(['statutory_type' => __('payroll.validation.statutory_type_required')]);
        }

        return $data;
    }
}
