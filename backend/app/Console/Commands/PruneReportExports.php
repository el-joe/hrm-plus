<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes queued report export files past their availability (report_exports.expires_at, 7 days) and
 * records older than 90 days. Phase 8c.
 */
#[Signature('reports:prune-exports')]
#[Description('Delete expired report export files')]
class PruneReportExports extends Command
{
    public function handle(): int
    {
        $files = 0;

        ReportExport::query()->whereNotNull('file_path')->where('expires_at', '<', now())->each(function (ReportExport $export) use (&$files): void {
            Storage::disk('local')->delete($export->file_path);
            $export->forceFill(['file_path' => null])->save();
            $files++;
        });

        $records = ReportExport::query()->where('created_at', '<', now()->subDays(90))->delete();
        $this->info("Deleted {$files} expired file(s) and {$records} old export record(s).");

        return self::SUCCESS;
    }
}
