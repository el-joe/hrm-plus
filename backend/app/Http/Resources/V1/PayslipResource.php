<?php

namespace App\Http\Resources\V1;

use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee's own payslip (mobile app). Money is a DECIMAL string. The
 * detail endpoint adds "lines" grouped as on the PDF (earnings, deductions,
 * loans, statutory) with each line's explanation in the request language.
 *
 * @mixin Payslip
 */
class PayslipResource extends JsonResource
{
    /** @var array<string, mixed>|null */
    private ?array $document = null;

    /**
     * @param  array<string, mixed>  $document  App\Services\Payroll\PayslipDocument::data()
     */
    public function withDocument(array $document): static
    {
        $this->document = $document;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'period' => [
                'code' => $this->period->code,
                'name' => $this->period->name,
                'start_date' => $this->period->start_date->toDateString(),
                'end_date' => $this->period->end_date->toDateString(),
                'pay_date' => $this->period->pay_date->toDateString(),
            ],
            'run_type' => $this->run->type->value,
            'currency' => $this->currency,
            'gross_pay' => (string) $this->gross_pay,
            'total_deductions' => (string) $this->total_deductions,
            'net_pay' => (string) $this->net_pay,
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'amount_paid' => (string) $this->amount_paid,
            'paid_on' => $this->paid_on?->toDateString(),
            'issued_at' => $this->issued_at->toIso8601String(),
            'pdf_url' => route('api.v1.me.payslips.pdf', $this->id),
            'lines' => $this->when($this->document !== null, fn (): array => [
                'basic_salary' => $this->document['basic_salary'],
                'earnings' => $this->document['earnings'],
                'deductions' => $this->document['deductions'],
                'loans' => $this->document['loans'],
                'statutory' => $this->document['statutory'],
                'demo_statutory' => $this->document['demo'],
            ]),
        ];
    }
}
