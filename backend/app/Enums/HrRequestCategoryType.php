<?php

namespace App\Enums;

/**
 * general: an inquiry / service handled in the thread; letter: HR issues a PDF letter from the category's
 * letter template; profile_change: the employee proposes new values for protected profile data.
 */
enum HrRequestCategoryType: string
{
    case General = 'general';
    case Letter = 'letter';
    case ProfileChange = 'profile_change';

    public function label(): string
    {
        return __('hr_requests.enums.category_types.'.$this->value);
    }
}
