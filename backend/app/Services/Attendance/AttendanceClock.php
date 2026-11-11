<?php

namespace App\Services\Attendance;

use App\Models\Branch;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Timezone rules of attendance: a branch's own timezone, else the company
 * timezone (setting localisation.timezone). Instants are stored in UTC;
 * attendance dates are local calendar dates of the employee's branch.
 */
class AttendanceClock
{
    /** @var array<int, string> */
    private array $branchZones = [];

    public function __construct(private readonly SettingsService $settings) {}

    public function companyTimezone(): string
    {
        return (string) $this->settings->get('localisation.timezone');
    }

    public function timezoneForBranch(?int $branchId): string
    {
        if ($branchId === null) {
            return $this->companyTimezone();
        }

        return $this->branchZones[$branchId] ??= (string) (Branch::query()->withoutGlobalScopes()->whereKey($branchId)->value('timezone') ?: $this->companyTimezone());
    }

    /**
     * A date (Y-m-d) as an immutable midnight value, timezone-free (UTC).
     */
    public static function date(DateTimeInterface|string $date): CarbonImmutable
    {
        $value = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr($date, 0, 10);

        return CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
    }

    /**
     * The UTC instant of a local wall-clock time on a date.
     */
    public static function instant(CarbonImmutable $date, string $time, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date->format('Y-m-d').' '.substr($time, 0, 5), $timezone)->utc();
    }

    /**
     * Local calendar date of an instant.
     */
    public static function localDate(DateTimeInterface $instant, string $timezone): CarbonImmutable
    {
        return self::date(CarbonImmutable::instance($instant)->setTimezone($timezone));
    }

    /**
     * Local "H:i" of a UTC instant.
     */
    public static function localTime(?DateTimeInterface $instant, string $timezone): ?string
    {
        return $instant === null ? null : CarbonImmutable::instance($instant)->setTimezone($timezone)->format('H:i');
    }
}
