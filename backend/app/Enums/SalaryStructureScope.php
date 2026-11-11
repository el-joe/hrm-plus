<?php

namespace App\Enums;

/**
 * What a salary-structure assignment applies to. Precedence, most specific
 * first: employee > job grade > department (nearest up the tree) > branch > company.
 */
enum SalaryStructureScope: string
{
    case Employee = 'employee';
    case JobGrade = 'job_grade';
    case Department = 'department';
    case Branch = 'branch';
    case Company = 'company';

    public function label(): string
    {
        return __('payroll.enums.structure_scopes.'.$this->value);
    }
}
