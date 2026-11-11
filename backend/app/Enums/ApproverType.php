<?php

namespace App\Enums;

/**
 * How an approval step decides who may act on it.
 *
 * Phase 3 seam: a "direct manager of the requester" rule needs the employees
 * table; add a case here and handle it in ApprovalService::canAct().
 */
enum ApproverType: string
{
    case User = 'user';
    case Role = 'role';
    case Permission = 'permission';
}
