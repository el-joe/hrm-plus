<?php

namespace App\Notifications\Reports;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use App\Notifications\CentreNotification;
use App\Services\Notifications\NotificationRouter;
use App\Services\Reports\ReportRegistry;

/**
 * A queued report export finished (event report_export_ready) or failed (report_export_failed).
 * Sent only to the admin who asked for it.
 */
class ReportExportNotification extends CentreNotification
{
    public function __construct(public readonly ReportExport $export) {}

    public function event(): string
    {
        return $this->export->status === ReportExportStatus::Completed ? 'report_export_ready' : 'report_export_failed';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationRouter::class)->channelsFor($notifiable, $this->event());
    }

    protected function actionLabelKey(): string
    {
        return 'reports.notifications.open';
    }

    public function placeholders(object $notifiable, ?string $locale): array
    {
        $report = app(ReportRegistry::class)->find($this->export->report);

        return [
            'report' => $report ? __('reports.items.'.$report->key().'.title', [], $locale) : $this->export->report,
            'format' => strtoupper($this->export->format),
            'rows' => (string) ($this->export->rows ?? 0),
            'reason' => (string) $this->export->error,
        ];
    }

    /**
     * @return array{kind: string, report_export_id: int, url: string}
     */
    public function payload(object $notifiable): array
    {
        return [
            'kind' => $this->event(),
            'report_export_id' => $this->export->id,
            'url' => route('admin.reports.exports.index'),
        ];
    }
}
