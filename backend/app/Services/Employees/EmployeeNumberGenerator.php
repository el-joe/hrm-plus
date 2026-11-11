<?php

namespace App\Services\Employees;

use App\Models\Branch;
use App\Models\Employee;
use App\Services\Settings\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Generates unique employee numbers from the configurable pattern
 * (settings employee_number.*: pattern, prefix, padding, next_sequence).
 *
 * Race-safe: the counter row in number_sequences is locked FOR UPDATE inside
 * the caller's transaction, so two concurrent hires can never draw the same
 * number, and employees.employee_number is unique as a final guard. Numbers
 * already taken (e.g. imported ones) are skipped.
 *
 * Call it inside the transaction that inserts the employee.
 */
class EmployeeNumberGenerator
{
    public const SEQUENCE_KEY = 'employee_number';

    public function __construct(private readonly SettingsService $settings) {}

    public function next(?Branch $branch = null, ?CarbonInterface $hireDate = null): string
    {
        return DB::transaction(function () use ($branch, $hireDate): string {
            DB::table('number_sequences')->insertOrIgnore([
                'key' => self::SEQUENCE_KEY, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $current = (int) DB::table('number_sequences')->where('key', self::SEQUENCE_KEY)->lockForUpdate()->value('next_value');
            $sequence = max($current, (int) $this->settings->get('employee_number.next_sequence'));

            do {
                $candidate = $this->format($sequence, $branch, $hireDate);
                $sequence++;
            } while (Employee::query()->withoutGlobalScopes()->where('employee_number', $candidate)->exists());

            DB::table('number_sequences')->where('key', self::SEQUENCE_KEY)->update(['next_value' => $sequence, 'updated_at' => now()]);

            return $candidate;
        });
    }

    /**
     * Apply the pattern to a sequence value.
     */
    public function format(int $sequence, ?Branch $branch = null, ?CarbonInterface $hireDate = null): string
    {
        $number = str_pad((string) $sequence, (int) $this->settings->get('employee_number.padding'), '0', STR_PAD_LEFT);

        return mb_substr(strtr((string) $this->settings->get('employee_number.pattern'), [
            '{prefix}' => (string) $this->settings->get('employee_number.prefix'),
            '{number}' => $number,
            '{year}' => ($hireDate ?? now())->format('Y'),
            '{branch}' => (string) $branch?->code,
        ]), 0, 30);
    }
}
