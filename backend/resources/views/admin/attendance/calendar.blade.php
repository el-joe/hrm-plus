@php
    use App\Services\Attendance\AttendanceClock;
    $cellClass = fn ($day, $schedule): string => match (true) {
        $day !== null => match ($day->status->value) {
            'present' => 'bg-status-present-bg text-status-present-text',
            'late' => 'bg-status-late-bg text-status-late-text',
            'absent' => 'bg-status-absent-bg text-status-absent-text',
            'on_leave' => 'bg-status-on-leave-bg text-status-on-leave-text',
            'incomplete' => 'bg-status-pending-bg text-status-pending-text',
            default => 'bg-surface-muted text-text-secondary',
        },
        $schedule->holiday !== null => 'bg-status-finalized-bg text-status-finalized-text',
        default => 'text-text-tertiary',
    };
    $short = fn ($day, $schedule): string => match (true) {
        $day !== null => __('attendance_reports.calendar.short.'.$day->status->value),
        $schedule->holiday !== null => __('attendance_reports.calendar.short.holiday'),
        $schedule->isRestDay || ! $schedule->isEmployed() => '·',
        default => '○',
    };
    $monthParam = $from->format('Y-m');
@endphp
@extends('layouts.admin', ['subtitle' => __('attendance_reports.calendar.subtitle')])

@section('title', __('attendance_reports.calendar.title'))

@section('content')
    <x-filter-bar :action="route('admin.attendance.calendar')" :reset="route('admin.attendance.calendar')">
        <x-form.input name="month" type="month" :label="__('attendance_reports.calendar.month')" hide-label :value="$monthParam" class="md:w-44" />
        <x-form.select name="employee_id" :label="__('attendance.fields.employee')" hide-label class="md:w-60">
            <option value="">{{ __('attendance.fields.employee') }}: —</option>
            @foreach ($employees as $option)
                <option value="{{ $option->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $option->id)>{{ $option->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="department_id" :label="__('attendance.fields.department')" hide-label class="md:w-48">
            <option value="">{{ __('attendance.fields.department') }}: —</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="branch_id" :label="__('attendance.fields.branch')" hide-label class="md:w-44">
            <option value="">{{ __('attendance.fields.branch') }}: —</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <x-button variant="ghost" size="sm" icon="chevron-left" :href="route('admin.attendance.calendar', [...request()->except(['month', 'page']), 'month' => $from->subMonth()->format('Y-m')])">{{ __('attendance_reports.calendar.previous') }}</x-button>
            <span class="text-h5">{{ $from->translatedFormat('F Y') }}</span>
            <x-button variant="ghost" size="sm" icon-end="chevron-right" :href="route('admin.attendance.calendar', [...request()->except(['month', 'page']), 'month' => $from->addMonth()->format('Y-m')])">{{ __('attendance_reports.calendar.next') }}</x-button>
        </div>
        <ul class="flex flex-wrap gap-3 text-caption-md text-text-secondary">
            @foreach (['present', 'late', 'absent', 'on_leave', 'incomplete', 'holiday'] as $key)
                <li class="flex items-center gap-1"><span class="font-semibold">{{ __('attendance_reports.calendar.short.'.$key) }}</span> {{ __('attendance_reports.calendar.legend.'.$key) }}</li>
            @endforeach
            <li class="flex items-center gap-1"><span>○</span> {{ __('attendance_reports.calendar.legend.scheduled') }}</li>
            <li class="flex items-center gap-1"><span>·</span> {{ __('attendance_reports.calendar.legend.rest') }}</li>
        </ul>
    </div>

    @if ($employee)
        <x-card :title="$employee->label()" data-region="employee-calendar">
            <x-slot:actions>
                <x-button variant="ghost" size="sm" icon="calendar" :href="route('admin.attendance.timesheet', ['employee_id' => $employee->id, 'month' => $monthParam])">{{ __('attendance.timesheet.title') }}</x-button>
            </x-slot:actions>
            <div class="grid grid-cols-7 gap-1 text-center">
                @foreach ($weekdays as $weekday)
                    <span class="py-1 text-label-sm text-text-secondary">{{ $weekday }}</span>
                @endforeach
                @foreach ($weeks as $week)
                    @foreach ($week as $cell)
                        @if ($cell === null)
                            <span></span>
                        @else
                            @php $day = $cell['days'][0] ?? null; $schedule = $cell['schedule']; @endphp
                            @if ($day)
                                <a href="{{ route('admin.attendance.days.show', $day) }}" class="flex min-h-16 flex-col items-center gap-0.5 rounded-md p-1 text-caption-md {{ $cellClass($day, $schedule) }}">
                            @else
                                <div class="flex min-h-16 flex-col items-center gap-0.5 rounded-md border border-border-subtle p-1 text-caption-md {{ $cellClass(null, $schedule) }}">
                            @endif
                                <span class="text-label-md">{{ $cell['date']->format('j') }}</span>
                                <span class="font-semibold">{{ $short($day, $schedule) }}</span>
                                @if ($day?->first_in_at)<span class="hidden tabular-nums sm:block" dir="ltr">{{ AttendanceClock::localTime($day->first_in_at, $day->timezone) }}–{{ AttendanceClock::localTime($day->last_out_at, $day->timezone) ?? '…' }}</span>@endif
                            @if ($day)</a>@else</div>@endif
                        @endif
                    @endforeach
                @endforeach
            </div>
            @if ($summary)
                <div class="mt-4">@include('admin.attendance._summary', ['summary' => $summary])</div>
            @endif
        </x-card>
    @elseif ($matrix)
        <x-card :title="__('attendance_reports.calendar.team')" padding="none" data-region="matrix">
            <div class="overflow-x-auto">
                <table class="w-full text-caption-md">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle">
                            <th class="sticky start-0 z-10 min-w-44 bg-surface-subtle px-3 py-2 text-start text-label-sm text-text-secondary">{{ __('attendance.fields.employee') }}</th>
                            @foreach ($dates as $date)
                                <th class="min-w-7 px-0.5 py-2 text-center text-label-sm text-text-secondary">{{ $date->format('j') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrix as $row)
                            <tr class="border-b border-border-subtle">
                                <th class="sticky start-0 z-10 bg-surface-default px-3 py-1.5 text-start font-normal">
                                    <a href="{{ route('admin.attendance.calendar', ['employee_id' => $row['employee']->id, 'month' => $monthParam]) }}" class="block truncate text-label-md text-text-link hover:underline">{{ $row['employee']->name }}</a>
                                    <span class="text-text-tertiary" dir="ltr">{{ $row['employee']->employee_number }}</span>
                                </th>
                                @foreach ($row['cells'] as $date => $cell)
                                    @php $day = $cell['days'][0] ?? null; @endphp
                                    <td class="px-0.5 py-1 text-center">
                                        <span title="{{ $date }}{{ $day ? ' · '.$day->statusLabel() : '' }}" class="inline-flex size-6 items-center justify-center rounded-xs font-semibold {{ $cellClass($day, $cell['schedule']) }}">{{ $short($day, $cell['schedule']) }}</span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3"><x-pagination :paginator="$matrix" /></div>
        </x-card>
    @else
        <x-empty-state icon="calendar" :title="__('attendance_reports.calendar.pick')" :description="__('attendance_reports.calendar.pick_help')" />
    @endif
@endsection
