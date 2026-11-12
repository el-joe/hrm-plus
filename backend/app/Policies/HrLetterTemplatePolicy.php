<?php

namespace App\Policies;

use App\Models\HrLetterTemplate;
use App\Models\User;

/**
 * Letter templates are configuration: hr_requests.manage. A template used by a category cannot be deleted.
 */
class HrLetterTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr_requests.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('hr_requests.manage');
    }

    public function update(User $user, HrLetterTemplate $template): bool
    {
        return $user->can('hr_requests.manage');
    }

    public function delete(User $user, HrLetterTemplate $template): bool
    {
        return $user->can('hr_requests.manage') && $template->categories()->doesntExist();
    }
}
