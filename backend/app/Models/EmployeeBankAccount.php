<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\SensitiveValue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bank account salaries are paid into. Account number and IBAN are
 * encrypted; iban_hash allows exact lookups.
 */
#[Fillable(['employee_id', 'bank_name', 'account_holder', 'account_number', 'iban', 'swift_code', 'is_primary'])]
#[Hidden(['account_number', 'iban', 'iban_hash'])]
class EmployeeBankAccount extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected array $auditRedact = ['account_number', 'iban', 'iban_hash'];

    protected static function booted(): void
    {
        static::saving(function (EmployeeBankAccount $account): void {
            if ($account->isDirty('iban')) {
                $account->iban = SensitiveValue::normalise($account->iban);
                $account->iban_hash = SensitiveValue::hash($account->iban);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'iban' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
