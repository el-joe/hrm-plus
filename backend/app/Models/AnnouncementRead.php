<?php

namespace App\Models;

use Database\Factories\AnnouncementReadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * When an employee read (and, if required, acknowledged) an announcement.
 */
#[Fillable(['announcement_id', 'employee_id', 'read_at', 'acknowledged_at', 'acknowledged_via'])]
class AnnouncementRead extends Model
{
    /** @use HasFactory<AnnouncementReadFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
