<?php

namespace App\Console\Commands;

use App\Services\Offboarding\ContractExpiryScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('offboarding:detect-contract-expiries')]
#[Description('Open "contract end" offboarding cases for contracts ending within the configured lead time')]
class DetectContractExpiries extends Command
{
    public function handle(ContractExpiryScanner $scanner): int
    {
        $this->components->info('Opened '.$scanner->run().' offboarding case(s).');

        return self::SUCCESS;
    }
}
