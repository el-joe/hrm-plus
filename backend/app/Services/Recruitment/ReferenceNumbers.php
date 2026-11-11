<?php

namespace App\Services\Recruitment;

use Illuminate\Support\Facades\DB;

/**
 * Race-safe reference numbers (VAC-00001, OFR-00001) from number_sequences;
 * prefixes and padding in config('recruitment.references'). Call inside the
 * transaction that inserts the record.
 */
class ReferenceNumbers
{
    public function next(string $kind): string
    {
        $config = config("recruitment.references.{$kind}");

        return DB::transaction(function () use ($kind, $config): string {
            $key = 'recruitment_'.$kind;
            DB::table('number_sequences')->insertOrIgnore(['key' => $key, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $value = (int) DB::table('number_sequences')->where('key', $key)->lockForUpdate()->value('next_value');
            DB::table('number_sequences')->where('key', $key)->update(['next_value' => $value + 1, 'updated_at' => now()]);

            return $config['prefix'].str_pad((string) $value, $config['padding'], '0', STR_PAD_LEFT);
        });
    }
}
