<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Race-safe yearly reference numbers from number_sequences for the Phase 8a
 * modules: PRV-2026-00001 (performance reviews), TRS-2026-00001 (training
 * sessions), DIS-2026-00001 (disciplinary cases). One sequence per prefix and
 * year. Call inside the transaction that inserts the record.
 *
 *     app(ReferenceNumbers::class)->next('DIS');
 */
class ReferenceNumbers
{
    public function next(string $prefix, ?string $year = null): string
    {
        $year ??= now()->format('Y');

        return DB::transaction(function () use ($prefix, $year): string {
            $key = 'ref_'.$prefix.'_'.$year;
            DB::table('number_sequences')->insertOrIgnore(['key' => $key, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $value = (int) DB::table('number_sequences')->where('key', $key)->lockForUpdate()->value('next_value');
            DB::table('number_sequences')->where('key', $key)->update(['next_value' => $value + 1, 'updated_at' => now()]);

            return $prefix.'-'.$year.'-'.str_pad((string) $value, 5, '0', STR_PAD_LEFT);
        });
    }
}
