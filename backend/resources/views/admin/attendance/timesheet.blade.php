@php use App\Services\Attendance\AttendanceClock; use App\Support\Duration; @endphp
@extends('layouts.admin', ['subtitle' => __('attendance.timesheet.subtitle')])

@section('title', __('attendance.timesheet.title'))

@section('page-actions')
    @if ($employee)
        @can('attendance.export')
            <x-button variant="secondary" icon="download" :href="route('admin.attendance.timesheet.export', ['employee_id' => $employee->id, 'month' => $month])">{{ __('attendance.common.export') }}</x-button>
        @endcan
    @endif
@endsection

@section('content')
    <x-filter-bar :action="route('admin.attendance.timesheet')" :reset="route('admin.attendance.timesheet')">
        <x-form.select name="employee_id" :label="__('attendance.fields.employee')" hide-label class="md:w-80">
            <option value="">{{ __('attendance.timesheet.choose_employee') }}</option>
            @foreach ($employees as $option)
                <option value="{{ $option->id }}" @selected($employee?->id === $option->id)>{{ $option->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.input name="month" type="month" :label="__('attendance.fields.month')" hide-label :value="$month" class="md:w-44" />
    </x-filter-bar>

    @if (! $employee)
        <x-card>
            <x-empty-state icon="calendar" :title="__('attendance.timesheet.choose_employee')" :description="__('attendance.timesheet.choose_help')" />
        </x-card>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-h4 text-text-primary">{{ $employee->name }}</h2>
                <p class="text-body-sm text-text-tertiary" dir="auto">{{ $employee->employee_number }} · {{ $from->translatedFormat('F Y') }}</p>
            </div>
            <div class="flex gap-2">
                <x-button variant="ghost" size="sm" icon="chevron-left" :href="route('admin.attendance.timesheet', ['employee_id' => $employee->id, 'month' => $from->subMonth()->format('Y-m')])">{{ __('attendance.common.previous') }}</x-button>
                <x-button variant="ghost" size="sm" icon-end="chevron-right" :href="route('admin.attendance.timesheet', ['employee_id' => $employee->id, 'month' => $from->addMonth()->format('Y-m')])">{{ __('attendance.common.next') }}</x-button>
            </div>
        </div>

        @include('admin.attendance._summary')

        <x-table data-region="timesheet" :caption="__('attendance.timesheet.title')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('attendance.fields.date') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.shift') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.check_in') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.check_out') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.worked') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.late') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.early_leave') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.overtime') }}</x-table.th>
                    <x-table.th>{{ __('attendance.fields.status') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($rows as $row)
                @php $tz = $row['schedule']->timezone; @endphp
                @forelse ($row['days'] as $day)
                    <x-table.row data-href="{{ route('admin.attendance.days.show', $day) }}">
                        <x-table.td :label="__('attendance.fields.date')" class="whitespace-nowrap">
                            <a href="{{ route('admin.attendance.days.show', $day) }}" class="text-label-md text-text-link hover:underline">{{ $row['date']->translatedFormat('D j') }}</a>
                        </x-table.td>
                        <x-table.td :label="__('attendance.fields.shift')">{{ $day->shift?->code ?? '—' }}</x-table.td>
                        <x-table.td :label="__('attendance.fields.check_in')" class="tabular-nums"><span dir="ltr">{{ AttendanceClock::localTime($day->first_in_at, $tz) ?? '—' }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.check_out')" class="tabular-nums"><span dir="ltr">{{ AttendanceClock::localTime($day->last_out_at, $tz) ?? '—' }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.worked')" class="tabular-nums"><span dir="ltr">{{ Duration::format($day->worked_minutes) }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.late')" class="tabular-nums"><span dir="ltr">{{ Duration::format($day->late_minutes) }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.early_leave')" class="tabular-nums"><span dir="ltr">{{ Duration::format($day->early_leave_minutes) }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.overtime')" class="tabular-nums"><span dir="ltr">{{ Duration::format($day->overtime_minutes) }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.status')">@include('admin.attendance._day-status')</x-table.td>
                    </x-table.row>
                @empty
                    <x-table.row>
                        <x-table.td :label="__('attendance.fields.date')" class="whitespace-nowrap text-text-secondary">{{ $row['date']->translatedFormat('D j') }}</x-table.td>
                        <x-table.td :label="__('attendance.fields.shift')" class="text-text-tertiary">
                            {{ collect($row['schedule']->shifts)->map(fn ($shift) => $shift->shift->code)->implode(', ') ?: ($row['schedule']->isRestDay ? __('attendance.enums.day_statuses.rest_day') : '—') }}
                        </x-table.td>
                        <x-table.td :label="__('attendance.fields.status')" colspan="7" class="text-caption-md text-text-tertiary">
                            {{ $row['schedule']->isEmployed() ? __('attendance.timesheet.not_processed') : __('attendance.enums.schedule_sources.not_employed') }}
                        </x-table.td>
                    </x-table.row>
                @endforelse
            @endforeach
        </x-table>
    @endif
@endsection
