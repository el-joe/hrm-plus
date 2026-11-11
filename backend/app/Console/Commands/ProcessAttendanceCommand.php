<?php

namespace App\Console\Commands;

use App\Enums\EmployeeStatus;
use App\Enums\RawEventStatus;
use App\Models\AttendanceRawEvent;
use App\Models\Employee;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\AttendanceEngine;
use App\Services\Attendance\AttendanceRecalculation;
use App\Services\Attendance\RawEventRecorder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:process
    {--date= : One date (Y-m-d); default yesterday in the company timezone}
    {--from= : First date of a range}
    {--to= : Last date of a range}
    {--employee=* : Limit to these employee ids}
    {--pending : Process the dates of raw events still pending instead of a date range}
    {--queue : Dispatch jobs instead of processing in this process}')]
#[Description('Calculate attendance days from raw events (idempotent; safe to repeat)')]
class ProcessAttendanceCommand extends Command
{
    public function handle(AttendanceEngine $engine, AttendanceRecalculation $recalculation, RawEventRecorder $recorder, AttendanceClock $clock): int
    {
        if ($this->option('pending')) {
            return $this->processPending($recalculation, $recorder);
        }

        $yesterday = AttendanceClock::date(now($clock->companyTimezone())->subDay());
        $from = AttendanceClock::date($this->option('from') ?? $this->option('date') ?? $yesterday);
        $to = AttendanceClock::date($this->option('to') ?? $this->option('date') ?? $from);

        if ($to->lt($from)) {
            $this->components->error('--to must not be before --from.');

            return self::FAILURE;
        }

        $dates = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }

        $count = 0;

        Employee::query()->withoutGlobalScopes()
            ->when($this->option('employee'), fn ($query, array $ids) => $query->whereIn('id', $ids))
            ->where(fn ($query) => $query->where('status', '!=', EmployeeStatus::Terminated)->orWhere('termination_date', '>=', $from->format('Y-m-d')))
            ->where('hire_date', '<=', $to->format('Y-m-d'))
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($engine, $recalculation, $dates, &$count): void {
                foreach ($employees as $employee) {
                    $this->option('queue') ? $recalculation->queue($employee->id, $dates) : $engine->processDates($employee, $dates);
                    $count++;
                }
            });

        $this->components->info("Processed {$count} employee(s) for {$from->format('Y-m-d')} – {$to->format('Y-m-d')}.");

        return self::SUCCESS;
    }

    private function processPending(AttendanceRecalculation $recalculation, RawEventRecorder $recorder): int
    {
        $dates = [];

        AttendanceRawEvent::query()->withoutGlobalScopes()
            ->where('status', RawEventStatus::Pending)->whereNotNull('employee_id')
            ->orderBy('id')
            ->each(function (AttendanceRawEvent $event) use ($recorder, &$dates): void {
                foreach ($recorder->affectedDates($event) as $date) {
                    $dates[$event->employee_id][$date] = true;
                }
            });

        foreach ($dates as $employeeId => $employeeDates) {
            $this->option('queue') ? $recalculation->queue($employeeId, array_keys($employeeDates)) : $recalculation->now($employeeId, array_keys($employeeDates));
        }

        $this->components->info('Processed pending events of '.count($dates).' employee(s).');

        return self::SUCCESS;
    }
}
