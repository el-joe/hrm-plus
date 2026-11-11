<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PayslipStatus;
use App\Models\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The payslip of one employee in a finalised run (issued on finalisation,
 * voided by a reversal). Its content is the frozen payroll item; only the
 * payment fields change (payslip_payments). Visible to the employee (portal,
 * API) and to admins with payslips.view inside their data scope; every view
 * and download is logged.
 */
class Payslip extends Model
{
    use HasDataScope;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayslipStatus::class,
            'payment_status' => PaymentStatus::class,
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'paid_on' => 'date',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'branch_id',
            'department' => 'department_id',
            'employee_group' => 'employee.groups.id',
        ];
    }

    public function isVoid(): bool
    {
        return $this->status === PayslipStatus::Void;
    }

    /**
     * Amount still to pay (bcmath).
     */
    public function outstanding(): string
    {
        $rest = bcsub((string) $this->net_pay, (string) $this->amount_paid, 2);

        return bccomp($rest, '0', 2) > 0 ? $rest : '0.00';
    }

    /**
     * @return BelongsTo<PayrollItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<PayslipPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PayslipPayment::class)->orderBy('paid_on')->orderBy('id');
    }
}
