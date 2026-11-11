<?php

namespace App\Enums;

/**
 * Category of an employee document (spec module 3, Employee Documents).
 */
enum DocumentCategory: string
{
    case NationalId = 'national_id';
    case Passport = 'passport';
    case Contract = 'contract';
    case Certificate = 'certificate';
    case Qualification = 'qualification';
    case Cv = 'cv';
    case Medical = 'medical';
    case Insurance = 'insurance';
    case SignedPolicy = 'signed_policy';
    case Other = 'other';

    public function label(): string
    {
        return __('employees.enums.document_categories.'.$this->value);
    }
}
