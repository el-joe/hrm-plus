<?php

namespace App\Models;

use App\Enums\ReportExportStatus;
use Database\Factories\ReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A queued report export (Phase 8c, App\Jobs\GenerateReportExport). The file lives on the
 * private "local" disk under report-exports/{user_id}/ and is served only to its owner.
 *
 * @property array<string, mixed>|null $filters
 */
#[Fillable(['user_id', 'report', 'format', 'locale', 'filters', 'status', 'rows', 'file_path', 'file_name', 'error', 'started_at', 'completed_at', 'expires_at'])]
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'status' => ReportExportStatus::class,
            'rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === ReportExportStatus::Completed && $this->file_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
