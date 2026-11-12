<?php

namespace App\Jobs;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use App\Notifications\Reports\ReportExportNotification;
use App\Services\Authorization\DataScopeManager;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Generates a large report export in the background (Phase 8c). Runs AS the requesting admin:
 * their data scope is activated and their column permissions apply, exactly like the screen. The
 * file goes to the private "local" disk; the admin is notified through the notification centre
 * (event report_export_ready / report_export_failed) and downloads it from Reports → My exports.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $exportId) {}

    public function handle(ReportRegistry $registry, ReportExporter $exporter, DataScopeManager $scopes): void
    {
        $export = ReportExport::query()->with('user')->find($this->exportId);

        if ($export === null || $export->status !== ReportExportStatus::Queued) {
            return;
        }

        $user = $export->user;
        $report = $registry->find($export->report);
        $previousLocale = app()->getLocale();
        $previousAdmin = $scopes->activeAdmin();

        try {
            if ($report === null || ! $report->isAvailableTo($user) || ! $user->can('reports.export')) {
                throw new \RuntimeException(__('reports.exports.not_allowed'));
            }

            $export->forceFill(['status' => ReportExportStatus::Running, 'started_at' => now()])->save();
            app()->setLocale($export->locale);
            $scopes->activate($user);

            $relative = 'report-exports/'.$user->id.'/'.$export->id.'-'.bin2hex(random_bytes(8)).'.'.$export->format;
            $disk = Storage::disk('local');
            $disk->makeDirectory(dirname($relative));
            $rows = $exporter->write($report, $export->filters ?? [], $user, $export->format, $disk->path($relative));

            $export->forceFill([
                'status' => ReportExportStatus::Completed, 'rows' => $rows, 'file_path' => $relative,
                'file_name' => $exporter->fileName($report, $export->filters ?? [], $export->format),
                'completed_at' => now(), 'expires_at' => now()->addDays((int) config('reports.export_days', 7)),
            ])->save();
        } catch (Throwable $exception) {
            $export->forceFill(['status' => ReportExportStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 500), 'completed_at' => now()])->save();
            report($exception);
        } finally {
            $previousAdmin ? $scopes->activate($previousAdmin) : $scopes->deactivate();
            app()->setLocale($previousLocale);
        }

        $user->notify(new ReportExportNotification($export->fresh()));
    }
}
