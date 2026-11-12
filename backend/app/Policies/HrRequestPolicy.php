<?php

namespace App\Policies;

use App\Enums\HrRequestStatus;
use App\Models\HrRequest;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\HrRequests\HrLetterDocument;
use App\Services\HrRequests\HrRequestVisibility;

/**
 * Admin access to HR requests (employees reach their own requests through PortalHrRequests, never here):
 *
 * - view: hr_requests.view + HrRequestVisibility (data scope; without hr_requests.manage only requests
 *   assigned to the admin, unassigned ones, and — with hr_requests.approve — those awaiting approval);
 *   never a request about yourself;
 * - update (thread, notes, status, priority, take): hr_requests.update on a visible request;
 * - assign to someone else: hr_requests.manage (hr_requests.update may only take a request);
 * - decide: the approval engine says the admin may act on the current step;
 * - decideChanges: hr_requests.approve + employees.update (field permissions are checked per field);
 * - issueLetter: hr_requests.update, plus employee_financials.view when the letter shows pay;
 * - cancel: hr_requests.delete; export: hr_requests.export.
 */
class HrRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->can('hr_requests.view');
    }

    public function view(User $user, HrRequest $request): bool
    {
        return app(HrRequestVisibility::class)->canSee($user, $request);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->can('hr_requests.create');
    }

    public function update(User $user, HrRequest $request): bool
    {
        return $user->can('hr_requests.update') && $this->view($user, $request) && ! $request->isClosed();
    }

    public function assign(User $user, HrRequest $request): bool
    {
        return $user->can('hr_requests.manage') && $this->update($user, $request);
    }

    public function decide(User $user, HrRequest $request): bool
    {
        $approval = $request->latestApprovalRequest;

        return $request->status === HrRequestStatus::PendingApproval
            && $this->view($user, $request)
            && $approval !== null && app(ApprovalService::class)->canAct($approval, $user);
    }

    public function decideChanges(User $user, HrRequest $request): bool
    {
        return $request->category->isProfileChange()
            && $request->status->isHandled()
            && $user->can('hr_requests.approve')
            && $user->can('update', $request->employee)
            && $this->view($user, $request);
    }

    public function issueLetter(User $user, HrRequest $request): bool
    {
        $template = $request->category->letterTemplate;

        return $request->category->isLetter() && $template !== null
            && $request->status->isHandled()
            && $this->update($user, $request)
            && (! app(HrLetterDocument::class)->usesFinancialData($template) || $user->can('viewFinancial', $request->employee));
    }

    public function cancel(User $user, HrRequest $request): bool
    {
        return $user->can('hr_requests.delete') && $this->view($user, $request) && ! $request->isClosed();
    }

    public function export(User $user): bool
    {
        return $user->isAdmin() && $user->can('hr_requests.export');
    }
}
