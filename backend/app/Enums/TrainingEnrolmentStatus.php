<?php

namespace App\Enums;

/**
 * State of an employee's place on a training session.
 *
 * pending (awaiting approval) → enrolled → completed | failed | no_show;
 * pending → rejected; pending / enrolled → cancelled.
 */
enum TrainingEnrolmentStatus: string
{
    case Pending = 'pending';
    case Enrolled = 'enrolled';
    case Completed = 'completed';
    case Failed = 'failed';
    case NoShow = 'no_show';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('training.enums.enrolment_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Enrolled => 'info',
            self::Completed => 'success',
            self::Failed, self::Rejected => 'danger',
            self::NoShow, self::Cancelled => 'neutral',
        };
    }

    /**
     * Statuses that hold a seat on the session.
     *
     * @return list<self>
     */
    public static function seated(): array
    {
        return [self::Enrolled, self::Completed, self::Failed, self::NoShow];
    }

    /**
     * Statuses whose cost counts against the training budget (the seat was paid for).
     *
     * @return list<self>
     */
    public static function costed(): array
    {
        return self::seated();
    }

    /**
     * Outcomes HR records when the session is over.
     *
     * @return list<self>
     */
    public static function outcomes(): array
    {
        return [self::Completed, self::Failed, self::NoShow];
    }
}
