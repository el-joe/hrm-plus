<?php

namespace App\Services\Payroll\Data;

use App\Enums\PayCalculationType;
use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayRounding;
use App\Enums\StatutoryType;
use App\Models\SalaryComponent;
use App\Models\SalaryStructureLine;

/**
 * The rule of one salary component as payroll applies it: the catalogue
 * values, with a structure line's overrides (amount, rate, percentage base)
 * already applied. Amounts and rates are exact DECIMAL strings.
 *
 * amount: fixed → amount per period; quantity types → fixed amount per unit (null = salary rate × rate).
 * rate:   percentage → percent of the base; quantity types → multiplier of the salary rate (null = 1).
 * baseCode: percentage base component code (null = the basic salary).
 */
final readonly class ComponentRule
{
    public function __construct(
        public int $componentId,
        public string $code,
        public string $nameAr,
        public string $nameEn,
        public PayComponentKind $kind,
        public PayComponentCategory $category,
        public PayCalculationType $calculationType,
        public ?string $amount,
        public ?string $rate,
        public ?string $baseCode,
        public bool $usePolicyMultiplier,
        public ?StatutoryType $statutoryType,
        public bool $taxable,
        public bool $insurable,
        public bool $prorate,
        public bool $showOnPayslip,
        public PayRounding $rounding,
        public int $precision,
        public int $displayOrder,
    ) {}

    /**
     * @param  array<int, string>  $codes  component id => code, for resolving percentage bases
     */
    public static function fromComponent(SalaryComponent $component, ?SalaryStructureLine $line = null, array $codes = []): self
    {
        $baseId = $line?->base_component_id ?? $component->base_component_id;

        return new self(
            componentId: $component->id,
            code: $component->code,
            nameAr: $component->name_ar,
            nameEn: $component->name_en,
            kind: $component->kind,
            category: $component->category,
            calculationType: $component->calculation_type,
            amount: self::decimal($line?->amount ?? $component->amount),
            rate: self::decimal($line?->rate ?? $component->rate),
            baseCode: $baseId === null ? null : ($codes[$baseId] ?? SalaryComponent::query()->whereKey($baseId)->value('code')),
            usePolicyMultiplier: $component->use_policy_multiplier,
            statutoryType: $component->statutory_type,
            taxable: $component->is_taxable,
            insurable: $component->is_insurable,
            prorate: $component->prorate_partial_period,
            showOnPayslip: $component->show_on_payslip,
            rounding: $component->rounding,
            precision: $component->precision(),
            displayOrder: $component->display_order,
        );
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->nameAr : $this->nameEn;
    }

    /**
     * A copy with other amount / rate / base / treatment (used for allowance and deduction entries).
     */
    public function with(?string $amount, ?string $rate, ?string $baseCode, bool $taxable, bool $insurable, bool $prorate): self
    {
        return new self(
            $this->componentId, $this->code, $this->nameAr, $this->nameEn, $this->kind, $this->category, $this->calculationType,
            $amount, $rate, $baseCode, $this->usePolicyMultiplier, $this->statutoryType, $taxable, $insurable, $prorate,
            $this->showOnPayslip, $this->rounding, $this->precision, $this->displayOrder,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'component_id' => $this->componentId,
            'code' => $this->code,
            'name_ar' => $this->nameAr,
            'name_en' => $this->nameEn,
            'kind' => $this->kind->value,
            'category' => $this->category->value,
            'calculation_type' => $this->calculationType->value,
            'amount' => $this->amount,
            'rate' => $this->rate,
            'base_code' => $this->baseCode,
            'use_policy_multiplier' => $this->usePolicyMultiplier,
            'statutory_type' => $this->statutoryType?->value,
            'taxable' => $this->taxable,
            'insurable' => $this->insurable,
            'prorate' => $this->prorate,
            'show_on_payslip' => $this->showOnPayslip,
            'rounding' => $this->rounding->value,
            'precision' => $this->precision,
            'display_order' => $this->displayOrder,
        ];
    }

    private static function decimal(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
