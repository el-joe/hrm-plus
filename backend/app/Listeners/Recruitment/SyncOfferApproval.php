<?php

namespace App\Listeners\Recruitment;

use App\Actions\Recruitment\ManageOffer;
use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestRejected;
use App\Models\JobOffer;
use App\Services\Authorization\DataScopeManager;

/**
 * Applies the outcome of a "job_offer" approval request to the offer.
 */
class SyncOfferApproval
{
    public function __construct(private readonly ManageOffer $offers, private readonly DataScopeManager $scopes) {}

    public function handle(ApprovalRequestApproved|ApprovalRequestRejected $event): void
    {
        $offer = $this->scopes->withoutScoping(fn () => $event->approvalRequest->approvable()->getResults());

        if ($offer instanceof JobOffer) {
            $this->offers->applyApprovalOutcome($offer, $event instanceof ApprovalRequestApproved);
        }
    }
}
