<?php

namespace App\Services\Payroll;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Builds instalment plans (bcmath): amount split into count monthly
 * instalments starting in the month of $start (due on the first day of each
 * month). Every instalment but the last is amount ÷ count truncated to 2
 * decimals; the last one takes the remainder, so the plan always adds up.
 */
class LoanSchedule
{
    /**
     * @return list<array{sequence: int, due_date: string, amount: string}>
     */
    public function build(string $amount, int $count, CarbonInterface|string $start, int $firstSequence = 1): array
    {
        $count = max(1, $count);
        $month = CarbonImmutable::parse($start)->startOfMonth();
        $regular = bcdiv($amount, (string) $count, 2);
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'sequence' => $firstSequence + $index,
                'due_date' => $month->addMonthsNoOverflow($index)->toDateString(),
                'amount' => $index < $count - 1 ? $regular : bcsub($amount, bcmul($regular, (string) ($count - 1), 2), 2),
            ];
        }

        return $rows;
    }

    /**
     * Instalment amount of a plan (the regular one).
     */
    public function instalmentAmount(string $amount, int $count): string
    {
        return bcdiv($amount, (string) max(1, $count), 2);
    }
}
