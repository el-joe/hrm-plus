<?php

namespace App\Actions\Payroll;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payslip;
use App\Models\PayslipPayment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payment status of payslips (payslips.manage): record a full or partial
 * payment (date, method, bank reference), or mark many payslips paid at
 * once. Payments are append-only; the payslip keeps the running amount paid
 * and its status (unpaid → partially paid → paid). Void payslips take no payment.
 */
class ManagePayslipPayments
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{amount: string, paid_on: string, method: string, reference?: ?string, note?: ?string}  $data
     */
    public function record(Payslip $payslip, array $data, User $user): PayslipPayment
    {
        return DB::transaction(function () use ($payslip, $data, $user): PayslipPayment {
            $payslip = Payslip::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payslip->id);
            $amount = bcadd((string) $data['amount'], '0', 2);

            if ($payslip->isVoid()) {
                throw ValidationException::withMessages(['amount' => __('payroll_runs.validation.payslip_void')]);
            }

            if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $payslip->outstanding(), 2) > 0) {
                throw ValidationException::withMessages(['amount' => __('payroll_runs.validation.payment_amount', ['max' => $payslip->outstanding()])]);
            }

            $payment = $payslip->payments()->create([
                'amount' => $amount, 'paid_on' => $data['paid_on'], 'method' => PaymentMethod::from($data['method']),
                'reference' => $data['reference'] ?? null, 'note' => $data['note'] ?? null, 'recorded_by' => $user->id,
            ]);

            $paid = bcadd((string) $payslip->amount_paid, $amount, 2);
            $payslip->forceFill([
                'amount_paid' => $paid,
                'payment_status' => bccomp($paid, (string) $payslip->net_pay, 2) >= 0 ? PaymentStatus::Paid : PaymentStatus::PartiallyPaid,
                'paid_on' => $data['paid_on'],
                'payment_reference' => $data['reference'] ?? $payslip->payment_reference,
            ])->save();

            $this->audit->log('payroll.payslip_payment_recorded', $payslip, meta: [
                'amount' => $amount, 'paid_on' => $data['paid_on'], 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'status' => $payslip->payment_status->value,
            ], actor: $user);

            return $payment;
        });
    }

    /**
     * Pay the outstanding amount of every given payslip. Returns how many were paid.
     *
     * @param  Collection<int, Payslip>  $payslips
     * @param  array{paid_on: string, method: string, reference?: ?string}  $data
     */
    public function markPaid(Collection $payslips, array $data, User $user): int
    {
        return DB::transaction(function () use ($payslips, $data, $user): int {
            $count = 0;

            foreach ($payslips as $payslip) {
                if ($payslip->isVoid() || bccomp($payslip->outstanding(), '0', 2) <= 0) {
                    continue;
                }

                $this->record($payslip, ['amount' => $payslip->outstanding(), ...$data], $user);
                $count++;
            }

            $this->audit->log('payroll.payslips_bulk_paid', null, meta: ['count' => $count, 'paid_on' => $data['paid_on'], 'reference' => $data['reference'] ?? null], actor: $user);

            return $count;
        });
    }
}
