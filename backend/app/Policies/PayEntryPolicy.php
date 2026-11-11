<?php

namespace App\Policies;

use App\Enums\PayComponentKind;
use App\Enums\RequestStatus;
use App\Models\PayEntry;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

/**
 * Allowance (allowances.*) and deduction (deductions.*) entries: the module
 * follows the entry's kind. Records are limited to the admin's data scope;
 * decisions go through the approval engine (self-approval blocked there,
 * and nobody decides an entry addressed to themselves).
 */
class PayEntryPolicy
{
    public function viewAny(User $user, PayComponentKind $kind): bool
    {
        return $user->can(self::module($kind).'.view');
    }

    public function view(User $user, PayEntry $entry): bool
    {
        return $user->can(self::module($entry->kind).'.view') && $entry->isVisibleTo($user);
    }

    public function create(User $user, PayComponentKind $kind): bool
    {
        return $user->can(self::module($kind).'.create');
    }

    public function export(User $user, PayComponentKind $kind): bool
    {
        return $user->can(self::module($kind).'.export');
    }

    public function decide(User $user, PayEntry $entry): bool
    {
        $approval = $entry->latestApprovalRequest;

        return $entry->status === RequestStatus::Pending
            && ($entry->employee_id === null || $user->employee_id !== $entry->employee_id)
            && $approval !== null && app(ApprovalService::class)->canAct($approval, $user);
    }

    public function cancel(User $user, PayEntry $entry): bool
    {
        return $user->can(self::module($entry->kind).'.delete') && $entry->isVisibleTo($user)
            && ($entry->status === RequestStatus::Pending || ($entry->status === RequestStatus::Approved && $entry->start_date->isFuture()));
    }

    public function stop(User $user, PayEntry $entry): bool
    {
        return $user->can(self::module($entry->kind).'.update') && $entry->isVisibleTo($user)
            && $entry->status === RequestStatus::Approved && $entry->recurrence->value !== 'one_time';
    }

    public function addAttachments(User $user, PayEntry $entry): bool
    {
        return $user->can(self::module($entry->kind).'.update') && $entry->isVisibleTo($user)
            && in_array($entry->status, [RequestStatus::Pending, RequestStatus::Approved], true);
    }

    public static function module(PayComponentKind $kind): string
    {
        return $kind === PayComponentKind::Deduction ? 'deductions' : 'allowances';
    }
}
