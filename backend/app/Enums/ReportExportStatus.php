<?php

namespace App\Enums;

/**
 * State of a queued report export (Phase 8c).
 */
enum ReportExportStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return __('reports.exports.statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Queued, self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
