<?php

namespace App\Services\Attendance;

use App\Enums\ManualEntryAction;
use App\Enums\RawEventSource;
use App\Models\AttendanceManualBatch;
use App\Models\AttendanceStatus;
use App\Models\Employee;
use App\Models\User;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\Spreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Attendance import from Excel (.xlsx) or CSV, in two steps like the
 * employee import:
 *
 * 1. upload + dry run: every row is validated (employee in the admin's scope
 *    and employed on the date, shift scheduled that day, times, status code,
 *    duplicates inside the file). Nothing is written.
 * 2. confirm: validated again; only when no row has an error a DRAFT batch
 *    (source "import") is created, which then goes through the same
 *    confirmation / approval as a bulk entry and produces raw events with
 *    source "import".
 *
 * Columns: employee_number, date (Y-m-d), shift_code (optional), check_in /
 * check_out (HH:MM, branch time), status_code (optional, instead of times), note.
 */
class AttendanceImporter
{
    /** @var list<string> */
    public const COLUMNS = ['employee_number', 'date', 'shift_code', 'check_in', 'check_out', 'status_code', 'note'];

    public function __construct(private readonly Spreadsheet $spreadsheet, private readonly ScheduleResolver $schedules) {}

    public function stash(UploadedFile $file, User $user): string
    {
        $token = Str::uuid()->toString();
        $extension = strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';
        app(EmployeeFileStore::class)->disk()->putFileAs($this->folder($user), $file, $token.'.'.$extension);

        return $token;
    }

    /**
     * @return array{path: string, extension: string}|null
     */
    public function stashed(string $token, User $user): ?array
    {
        if (! Str::isUuid($token)) {
            return null;
        }

        $disk = app(EmployeeFileStore::class)->disk();

        foreach (['xlsx', 'csv'] as $extension) {
            $relative = $this->folder($user).'/'.$token.'.'.$extension;

            if ($disk->exists($relative) && $disk->lastModified($relative) >= now()->subMinutes(config('employees.import_ttl_minutes'))->getTimestamp()) {
                return ['path' => $disk->path($relative), 'extension' => $extension];
            }
        }

        return null;
    }

    public function discard(string $token, User $user): void
    {
        foreach (['xlsx', 'csv'] as $extension) {
            app(EmployeeFileStore::class)->disk()->delete($this->folder($user).'/'.$token.'.'.$extension);
        }
    }

    /**
     * Validate every row without writing anything.
     *
     * @return array{total: int, valid: int, errors: list<array{line: int, column: string, message: string}>, rows: array<int, array<string, mixed>>}
     */
    public function analyse(string $path, string $extension): array
    {
        $rows = $this->spreadsheet->read($path, $extension, (int) config('employees.import_max_rows'));

        if ($rows === []) {
            return ['total' => 0, 'valid' => 0, 'errors' => [['line' => 1, 'column' => '', 'message' => __('attendance.import.empty')]], 'rows' => []];
        }

        $missing = array_diff(['employee_number', 'date'], array_keys(reset($rows)));

        if ($missing !== []) {
            return ['total' => count($rows), 'valid' => 0, 'errors' => [['line' => 1, 'column' => implode(', ', $missing), 'message' => __('attendance.import.missing_columns')]], 'rows' => []];
        }

        $statuses = AttendanceStatus::query()->active()->get()->keyBy(fn (AttendanceStatus $status): string => mb_strtolower($status->code));
        $errors = [];
        $prepared = [];
        $seen = [];

        foreach ($rows as $line => $row) {
            if (isset($row['__too_many__'])) {
                $errors[] = ['line' => $line, 'column' => '', 'message' => __('attendance.import.too_many_rows', ['max' => config('employees.import_max_rows')])];

                break;
            }

            [$entry, $rowErrors] = $this->prepareRow($row, $statuses);

            if ($entry !== null) {
                $key = $entry['employee_id'].'|'.$entry['work_date'].'|'.($entry['shift_id'] ?? 0);

                if (isset($seen[$key])) {
                    $rowErrors[] = ['employee_number', __('attendance.import.duplicate_in_file', ['line' => $seen[$key]])];
                }

                $seen[$key] = $line;
            }

            foreach ($rowErrors as [$column, $message]) {
                $errors[] = ['line' => $line, 'column' => $column, 'message' => $message];
            }

            if ($rowErrors === [] && $entry !== null) {
                $prepared[$line] = $entry;
            }
        }

        return ['total' => count($rows), 'valid' => count($prepared), 'errors' => $errors, 'rows' => $prepared];
    }

    /**
     * Validate again and create the draft batch (all rows or none).
     *
     * @return array{report: array<string, mixed>, batch: ?AttendanceManualBatch}
     */
    public function import(string $path, string $extension, string $fileName, User $user): array
    {
        $report = $this->analyse($path, $extension);

        if ($report['errors'] !== [] || $report['rows'] === []) {
            return ['report' => $report, 'batch' => null];
        }

        $summary = ['selected' => count($report['rows']), 'present' => 0, 'late' => 0, 'custom_times' => 0, 'statuses' => [], 'skipped_existing' => 0];

        foreach ($report['rows'] as $entry) {
            if ($entry['action'] === ManualEntryAction::Status) {
                $code = $entry['status_code'];
                $summary['statuses'][$code] = ($summary['statuses'][$code] ?? 0) + 1;
            } else {
                $summary[$entry['action']->value]++;
            }
        }

        $batch = DB::transaction(function () use ($report, $summary, $fileName, $user): AttendanceManualBatch {
            $batch = AttendanceManualBatch::create([
                'source' => RawEventSource::Import, 'summary' => $summary, 'file_name' => mb_substr($fileName, 0, 255), 'created_by' => $user->id,
            ]);
            $batch->entries()->createMany(array_map(fn (array $entry): array => [...array_diff_key($entry, ['status_code' => true]), 'shift_key' => (int) ($entry['shift_id'] ?? 0)], array_values($report['rows'])));

            return $batch;
        });

        return ['report' => $report, 'batch' => $batch];
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, AttendanceStatus>  $statuses
     * @return array{0: ?array<string, mixed>, 1: list<array{0: string, 1: string}>}
     */
    private function prepareRow(array $row, Collection $statuses): array
    {
        $value = fn (string $column): ?string => filled($row[$column] ?? null) ? trim((string) $row[$column]) : null;
        $errors = [];
        $number = $value('employee_number');
        $date = $value('date');

        $employee = $number ? Employee::query()->where('employee_number', mb_strtoupper($number))->first() : null;

        if ($employee === null) {
            $errors[] = ['employee_number', __('attendance.import.unknown_employee', ['number' => (string) $number])];
        }

        if ($date === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            $errors[] = ['date', __('validation.date_format', ['attribute' => 'date', 'format' => 'Y-m-d'])];
        } elseif ($date > BulkAttendance::today()->addDay()->format('Y-m-d')) {
            $errors[] = ['date', __('attendance.import.future_date')];
        }

        foreach (['check_in', 'check_out'] as $column) {
            if ($value($column) !== null && ! preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $value($column))) {
                $errors[] = [$column, __('validation.date_format', ['attribute' => $column, 'format' => 'HH:MM'])];
            }
        }

        $statusCode = $value('status_code') ? mb_strtolower($value('status_code')) : null;

        if ($statusCode !== null && ! $statuses->has($statusCode)) {
            $errors[] = ['status_code', __('attendance.import.unknown_status', ['code' => $statusCode])];
        }

        if ($statusCode === null && $value('check_in') === null) {
            $errors[] = ['check_in', __('attendance.import.times_or_status')];
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        $resolved = $this->schedules->for($employee, $date);

        if (! $resolved->isEmployed()) {
            return [null, [['date', __('attendance.import.not_employed')]]];
        }

        $shift = $resolved->shifts[0] ?? null;

        if ($value('shift_code') !== null) {
            $shift = collect($resolved->shifts)->first(fn ($candidate): bool => strcasecmp($candidate->shift->code, $value('shift_code')) === 0);

            if ($shift === null) {
                return [null, [['shift_code', __('attendance.import.shift_not_scheduled', ['code' => $value('shift_code')])]]];
            }
        }

        $day = AttendanceClock::date($date);
        $checkIn = $value('check_in') ? LocalTime::toInstant($day, str_pad($value('check_in'), 5, '0', STR_PAD_LEFT), $resolved->timezone, $shift?->windowStart->subMinute()) : null;
        $checkOut = $value('check_out') ? LocalTime::toInstant($day, str_pad($value('check_out'), 5, '0', STR_PAD_LEFT), $resolved->timezone, $checkIn ?? $shift?->startAt) : null;

        return [[
            'employee_id' => $employee->id,
            'work_date' => $date,
            'shift_id' => $shift?->key(),
            'action' => $statusCode !== null ? ManualEntryAction::Status : ManualEntryAction::CustomTimes,
            'check_in_at' => $statusCode !== null ? null : $checkIn,
            'check_out_at' => $statusCode !== null ? null : $checkOut,
            'attendance_status_id' => $statusCode !== null ? $statuses[$statusCode]->id : null,
            'status_code' => $statusCode,
            'note' => $value('note') ? mb_substr($value('note'), 0, 500) : null,
        ], []];
    }

    private function folder(User $user): string
    {
        return 'attendance-imports/'.$user->getKey();
    }
}
