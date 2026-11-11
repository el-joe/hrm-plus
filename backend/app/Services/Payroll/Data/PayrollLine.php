<?php

namespace App\Services\Payroll\Data;

use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayrollLineSource;

/**
 * One calculated line of an employee's pay (before it is stored as a
 * payroll_item_lines row). amount is a signed bcmath string with two
 * decimals: earnings and deductions are positive, a refund correction of a
 * deduction is a negative deduction. explanation is a list of
 * {key, params} parts rendered with lang payroll_runs.explain.*.
 */
final class PayrollLine
{
    /**
     * @param  list<array{key: string, params: array<string, string|int>}>  $explanation
     */
    public function __construct(
        public string $code,
        public string $nameAr,
        public string $nameEn,
        public PayComponentKind $kind,
        public PayComponentCategory $category,
        public PayrollLineSource $source,
        public string $amount,
        public ?int $componentId = null,
        public ?string $quantity = null,
        public ?string $rate = null,
        public bool $taxable = false,
        public bool $insurable = false,
        public bool $showOnPayslip = true,
        public array $explanation = [],
        public int $displayOrder = 0,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
    ) {}

    public static function fromRule(ComponentRule $rule, PayrollLineSource $source, string $amount, array $explanation = [], ?string $quantity = null, ?string $rate = null): self
    {
        return new self(
            code: $rule->code, nameAr: $rule->nameAr, nameEn: $rule->nameEn, kind: $rule->kind, category: $rule->category,
            source: $source, amount: $amount, componentId: $rule->componentId, quantity: $quantity, rate: $rate,
            taxable: $rule->taxable, insurable: $rule->insurable, showOnPayslip: $rule->showOnPayslip,
            explanation: $explanation, displayOrder: $rule->displayOrder,
        );
    }

    /**
     * Columns of a payroll_item_lines row (without the item / run / employee ids).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'salary_component_id' => $this->componentId,
            'code' => $this->code,
            'name_ar' => $this->nameAr,
            'name_en' => $this->nameEn,
            'kind' => $this->kind->value,
            'category' => $this->category->value,
            'source' => $this->source->value,
            'sourceable_type' => $this->sourceType,
            'sourceable_id' => $this->sourceId,
            'quantity' => $this->quantity,
            'rate' => $this->rate,
            'amount' => $this->amount,
            'is_taxable' => $this->taxable,
            'is_insurable' => $this->insurable,
            'show_on_payslip' => $this->showOnPayslip,
            'explanation' => $this->explanation,
            'display_order' => $this->displayOrder,
        ];
    }
}
