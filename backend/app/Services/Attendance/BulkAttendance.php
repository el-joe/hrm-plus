<?php

namespace App\Services\Attendance;

use App\Enums\ManualBatchStatus;
use App\Enums\ManualEntryAction;
use App\Enums\RawEventSource;
use App\Models\AttendanceDay;
use App\Models\AttendanceManualBatch;
use App\Models\AttendanceStatus;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\Data\ScheduledShift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Mark all present except..." (spec module 6 D).
 *
 * 1. eligible(): the employees scheduled to work on the date who match the
 *    filters (branch, department, team, shift, employee group) and are inside
 *    the admin's data scope, with what is already recorded for them.
 * 2. createDraft(): the selection becomes a draft batch: every selected
 *    employee is "present" unless an exception (absent, late with a time,
 *    custom times, or any configurable status) was chosen. Employees who
 *    already have recorded (device / mobile) attendance are skipped for
 *    "present", so a bulk mark never duplicates or overrides real punches.
 * 3. The confirmation screen shows the summary; ManageManualBatch then
 *    applies it or sends it for approval.
 */
class BulkAttendance
{
    public function __construct(
        private readonly ScheduleResolver $schedules,
        private readonly AttendancePolicyResolver $policies,
    ) {}

    /**
     * @param  array{date: string, branch_id?: ?int, department_id?: ?int, team_id?: ?int, shift_id?: ?int, group_id?: ?int}  $filters
     * @return list<array{employee: Employee, shifts: list<ScheduledShift>, days: list<AttendanceDay>, has_recorded: bool}>
     */
    public function eligible(array $filters): array
    {
        $date = AttendanceClock::date($filters['date']);

        $employees = Employee::query()
            ->employed()
            ->with(['branch', 'department', 'team'])
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('branch_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->where('department_id', $id))
            ->when($filters['team_id'] ?? null, fn (Builder $query, $id) => $query->where('team_id', $id))
            ->when($filters['group_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('groups', fn (Builder $query) => $query->whereKey($id)))
            ->orderBy('employee_number')
            ->get();

        $this->schedules->preloadOverrides($employees->modelKeys(), $date, $date);
        $days = AttendanceDay::query()->whereIn('employee_id', $employees->modelKeys())->whereDate('work_date', $date->format('Y-m-d'))
            ->with(['attendanceStatus', 'shift'])->get()->groupBy('employee_id');
        $rows = [];

        foreach ($employees as $employee) {
            $resolved = $this->schedules->for($employee, $date);

            if (! $resolved->isWorkingDay()) {
                continue;
            }

            $shifts = $resolved->shifts;

            if ($filters['shift_id'] ?? null) {
                $shifts = array_values(array_filter($shifts, fn (ScheduledShift $shift): bool => $shift->key() === (int) $filters['shift_id']));

                if ($shifts === []) {
                    continue;
                }
            }

            $employeeDays = $days->get($employee->id, collect())->all();

            $rows[] = [
                'employee' => $employee,
                'shifts' => $shifts,
                'days' => $employeeDays,
                'has_recorded' => collect($employeeDays)->contains(fn (AttendanceDay $day): bool => array_diff($day->sources ?? [], ['manual', 'import']) !== []),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters  validated filters incl. date
     * @param  array<int|string, array{selected?: mixed, action?: ?string, check_in?: ?string, check_out?: ?string, note?: ?string}>  $selections  by employee id
     */
    public function createDraft(array $filters, array $selections, ?string $note, User $user): AttendanceManualBatch
    {
        $date = AttendanceClock::date($filters['date']);
        $eligible = collect($this->eligible($filters))->keyBy(fn (array $row): int => $row['employee']->id);
        $statuses = AttendanceStatus::query()->active()->get()->keyBy('id');
        $entries = [];
        $errors = [];
        $summary = ['selected' => 0, 'present' => 0, 'late' => 0, 'custom_times' => 0, 'statuses' => [], 'skipped_existing' => 0, 'eligible' => $eligible->count()];

        foreach ($selections as $employeeId => $selection) {
            if (! filter_var($selection['selected'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $row = $eligible->get((int) $employeeId);

            if ($row === null) {
                $errors["employees.{$employeeId}.selected"] = __('attendance.bulk.not_eligible');

                continue;
            }

            $summary['selected']++;
            [$action, $statusId] = $this->parseAction((string) ($selection['action'] ?? 'present'));
            $shift = count($row['shifts']) === 1 || ($filters['shift_id'] ?? null) ? $row['shifts'][0] : null;
            $timezone = $this->schedules->for($row['employee'], $date)->timezone;
            $entry = [
                'employee_id' => $row['employee']->id, 'work_date' => $date->format('Y-m-d'), 'shift_id' => $shift?->key(),
                'action' => $action, 'check_in_at' => null, 'check_out_at' => null, 'attendance_status_id' => null,
                'note' => filled($selection['note'] ?? null) ? mb_substr((string) $selection['note'], 0, 500) : null,
            ];

            if ($action === ManualEntryAction::Present && $row['has_recorded']) {
                $summary['skipped_existing']++;

                continue;
            }

            if ($action === ManualEntryAction::Status) {
                if (! $statuses->has($statusId)) {
                    $errors["employees.{$employeeId}.action"] = __('validation.exists', ['attribute' => __('attendance.fields.status')]);

                    continue;
                }

                $entry['attendance_status_id'] = $statusId;
                $code = $statuses[$statusId]->code;
                $summary['statuses'][$code] = ($summary['statuses'][$code] ?? 0) + 1;
            }

            if (in_array($action, [ManualEntryAction::Late, ManualEntryAction::CustomTimes], true)) {
                $reference = $shift ?? $row['shifts'][0];
                $checkIn = $selection['check_in'] ?? null;
                $checkOut = $selection['check_out'] ?? null;

                if (! $this->isTime($checkIn)) {
                    $errors["employees.{$employeeId}.check_in"] = __('validation.required', ['attribute' => __('attendance.fields.check_in')]);

                    continue;
                }

                if ($action === ManualEntryAction::CustomTimes && ! $this->isTime($checkOut)) {
                    $errors["employees.{$employeeId}.check_out"] = __('validation.required', ['attribute' => __('attendance.fields.check_out')]);

                    continue;
                }

                $in = LocalTime::toInstant($date, $checkIn, $timezone, $reference->windowStart->subMinute());
                $entry['check_in_at'] = $in;
                $entry['check_out_at'] = $action === ManualEntryAction::CustomTimes ? LocalTime::toInstant($date, $checkOut, $timezone, $in) : null;
                $entry['shift_id'] = $reference->key();
            }

            if ($action !== ManualEntryAction::Status) {
                $summary[$action->value]++;
            }

            $entries[] = $entry;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($entries === []) {
            throw ValidationException::withMessages(['employees' => __('attendance.bulk.nothing_selected')]);
        }

        return DB::transaction(function () use ($filters, $entries, $summary, $note, $user): AttendanceManualBatch {
            $batch = AttendanceManualBatch::create([
                'source' => RawEventSource::Manual,
                'work_date' => $filters['date'],
                'filters' => array_filter(array_diff_key($filters, ['date' => true])),
                'summary' => $summary,
                'note' => $note,
                'created_by' => $user->id,
            ]);
            $batch->entries()->createMany(array_map(fn (array $entry): array => [...$entry, 'shift_key' => (int) ($entry['shift_id'] ?? 0)], $entries));

            return $batch;
        });
    }

    /**
     * Whether applying the batch needs approval: the attendance policy of any of its employees on that date requires it.
     */
    public function requiresApproval(AttendanceManualBatch $batch): bool
    {
        foreach ($batch->entries()->with(['employee' => fn ($query) => $query->withoutGlobalScopes()])->get() as $entry) {
            $date = AttendanceClock::date($entry->work_date);
            $placement = $this->schedules->for($entry->employee, $date)->placement;

            if ($placement !== null && $this->policies->for($entry->employee, $placement, $date)->manual_entry_requires_approval) {
                return true;
            }
        }

        return false;
    }

    /**
     * "present", "late", "custom_times" or "status:{id}".
     *
     * @return array{0: ManualEntryAction, 1: ?int}
     */
    public function parseAction(string $value): array
    {
        if (str_starts_with($value, 'status:')) {
            return [ManualEntryAction::Status, (int) substr($value, 7)];
        }

        $action = ManualEntryAction::tryFrom($value);

        if ($action === null || $action === ManualEntryAction::Status) {
            throw ValidationException::withMessages(['employees' => __('validation.in', ['attribute' => __('attendance.fields.action')])]);
        }

        return [$action, null];
    }

    private function isTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    /**
     * Statuses offered as exceptions (draft batches only).
     *
     * @return list<array{value: string, label: string}>
     */
    public function actionOptions(): array
    {
        $options = [
            ['value' => ManualEntryAction::Present->value, 'label' => ManualEntryAction::Present->label()],
            ['value' => ManualEntryAction::Late->value, 'label' => ManualEntryAction::Late->label()],
            ['value' => ManualEntryAction::CustomTimes->value, 'label' => ManualEntryAction::CustomTimes->label()],
        ];

        foreach (AttendanceStatus::query()->active()->orderBy('sort_order')->orderBy('id')->get() as $status) {
            $options[] = ['value' => 'status:'.$status->id, 'label' => $status->name];
        }

        return $options;
    }

    public static function isDraft(AttendanceManualBatch $batch): bool
    {
        return $batch->status === ManualBatchStatus::Draft;
    }

    public static function today(): CarbonImmutable
    {
        return AttendanceClock::date(now(app(AttendanceClock::class)->companyTimezone()));
    }
}
