<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;

/**
 * Race-safe reference numbers of the payroll layer from number_sequences:
 * ALW-2026-00001 (allowances), DED-2026-00001 (deductions), LN-2026-00001
 * (loans), ADV-2026-00001 (advances). One sequence per kind and year. Call
 * inside the transaction that inserts the record.
 */
class PayrollReferences
{
    public const PREFIXES = ['earning' => 'ALW', 'deduction' => 'DED', 'loan' => 'LN', 'advance' => 'ADV'];

    public function next(string $kind): string
    {
        $prefix = self::PREFIXES[$kind];
        $year = now()->format('Y');

        return DB::transaction(function () use ($prefix, $year): string {
            $key = 'payroll_'.$prefix.'_'.$year;
            DB::table('number_sequences')->insertOrIgnore(['key' => $key, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $value = (int) DB::table('number_sequences')->where('key', $key)->lockForUpdate()->value('next_value');
            DB::table('number_sequences')->where('key', $key)->update(['next_value' => $value + 1, 'updated_at' => now()]);

            return $prefix.'-'.$year.'-'.str_pad((string) $value, 5, '0', STR_PAD_LEFT);
        });
    }
}
