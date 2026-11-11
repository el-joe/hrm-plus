<?php

namespace App\Console\Commands;

use App\Actions\Recruitment\ManageOffer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recruitment:expire-offers')]
#[Description('Mark sent job offers past their expiry date as expired')]
class ExpireJobOffers extends Command
{
    public function handle(ManageOffer $offers): int
    {
        $this->components->info('Expired '.$offers->expireDue().' offer(s).');

        return self::SUCCESS;
    }
}
