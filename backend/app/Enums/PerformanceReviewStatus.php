<?php

namespace App\Enums;

/**
 * Life of a performance review:
 * self_assessment → manager_assessment → pending_approval (HR calibration through the
 * approval engine; a rejection returns it to manager_assessment) → acknowledgement
 * (approved, waiting for the employee) → completed. Cancelled reviews are kept.
 */
enum PerformanceReviewStatus: string
{
    case SelfAssessment = 'self_assessment';
    case ManagerAssessment = 'manager_assessment';
    case PendingApproval = 'pending_approval';
    case Acknowledgement = 'acknowledgement';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('performance.enums.review_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::SelfAssessment, self::ManagerAssessment => 'warning',
            self::PendingApproval => 'info',
            self::Acknowledgement => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * Approved by HR (final rating fixed): awaiting acknowledgement or completed.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Acknowledgement, self::Completed], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::SelfAssessment, self::ManagerAssessment, self::PendingApproval], true);
    }
}
