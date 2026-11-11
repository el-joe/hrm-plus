<?php

namespace App\Policies;

use App\Models\JobOffer;
use App\Models\User;

/**
 * Offers. The offered salary itself additionally needs
 * employee_financials.view (App\Services\Recruitment\SalaryVisibility).
 * Approval decisions are made through the approval engine (offers.approve /
 * offers.reject on the "job_offer" workflow step).
 */
class JobOfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('offers.view');
    }

    public function view(User $user, JobOffer $offer): bool
    {
        return $user->can('offers.view') && $offer->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('offers.create');
    }

    /**
     * Edit, submit, send, record the candidate's answer, withdraw.
     */
    public function update(User $user, JobOffer $offer): bool
    {
        return $user->can('offers.update') && $offer->isVisibleTo($user);
    }
}
