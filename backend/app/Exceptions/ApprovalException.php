<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule violation in the approval engine (wrong approver, request
 * already decided, missing workflow, ...). Callers translate it into a
 * validation error or a 403/409 response as fits their flow.
 */
class ApprovalException extends RuntimeException
{
    public static function workflowUnavailable(string $key): self
    {
        return new self(__('approvals.workflow_unavailable', ['key' => $key]));
    }

    public static function alreadyPending(): self
    {
        return new self(__('approvals.already_pending'));
    }

    public static function notPending(): self
    {
        return new self(__('approvals.not_pending'));
    }

    public static function notAllowed(): self
    {
        return new self(__('approvals.not_allowed'));
    }

    public static function reasonRequired(): self
    {
        return new self(__('approvals.reason_required'));
    }
}
