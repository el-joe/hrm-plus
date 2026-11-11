@extends('layouts.admin', ['subtitle' => __('leave.calendar.subtitle')])

@section('title', __('leave.calendar.title'))

@php
    $chip = ['chart-1' => 'bg-chart-1', 'chart-2' => 'bg-chart-2', 'chart-3' => 'bg-chart-3', 'chart-4' => 'bg-chart-4', 'chart-5' => 'bg-chart-5', 'chart-6' => 'bg-chart-6'];
    $query = fn (array $extra) => route('admin.leave-calendar', array_filter([...$filters, ...$extra]));
    $locale = app()->getLocale();
@endphp

@section('content')
    <x-filter-bar :action="route('admin.leave-calendar')" :reset="route('admin.leave-calendar')">
        <input type="hidden" name="month" value="{{ $from->format('Y-m') }}">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('leave.common.search_employee')" :value="$filters['search'] ?? ''" class="md:w-48" data-filter-debounce />
        <x-form.select name="branch_id" :label="__('leave.fields.branch')" hide-label class="md:w-40">
            <option value="">{{ __('leave.fields.branch') }}: {{ __('app.common.all') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="department_id" :label="__('leave.fields.department')" hide-label class="md:w-44">
            <option value="">{{ __('leave.fields.department') }}: {{ __('app.common.all') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="leave_type_id" :label="__('leave.fields.leave_type_id')" hide-label class="md:w-40">
            <option value="">{{ __('leave.fields.leave_type_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected((string) ($filters['leave_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
            @endforeach
        </x-form.select>
        @if ($hasTeam)
            <x-form.checkbox name="team" value="mine" :label="__('leave.calendar.my_team')" :checked="($filters['team'] ?? null) === 'mine'" />
        @endif
        <x-form.checkbox name="only_away" value="1" :label="__('leave.calendar.only_away')" :checked="(bool) ($filters['only_away'] ?? false)" />
    </x-filter-bar>

    <x-card padding="none">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-1">
                <x-icon-button icon="chevron-left" flip size="sm" :href="$query(['month' => $from->subMonth()->format('Y-m')])" :label="__('leave.calendar.previous')" />
                <x-button variant="ghost" size="sm" :href="$query(['month' => $today->format('Y-m')])">{{ __('leave.calendar.this_month') }}</x-button>
                <x-icon-button icon="chevron-right" flip size="sm" :href="$query(['month' => $from->addMonth()->format('Y-m')])" :label="__('leave.calendar.next')" />
            </div>
            <h2 class="text-h5 text-text-primary">{{ $from->locale($locale)->translatedFormat('F Y') }}</h2>
            <ul class="flex flex-wrap items-center gap-x-3 gap-y-1 text-caption-md text-text-secondary" aria-label="{{ __('leave.calendar.legend') }}">
                @foreach ($types as $type)
                    <li class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full {{ $chip[$type->color] ?? 'bg-chart-1' }}" aria-hidden="true"></span>{{ $type->name }}</li>
                @endforeach
                <li class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full border-2 border-dashed border-text-tertiary" aria-hidden="true"></span>{{ __('leave.calendar.pending') }}</li>
                <li class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-status-finalized-dot" aria-hidden="true"></span>{{ __('leave.calendar.holiday') }}</li>
                <li class="inline-flex items-center gap-1.5"><x-icon name="alert-triangle" size="sm" class="text-feedback-warning-text" />{{ __('leave.calendar.conflict') }}</li>
            </ul>
        </div>

        @if ($grid['rows'] === [])
            <x-empty-state icon="calendar" :title="__('leave.calendar.no_employees')" class="py-10" />
        @else
            <div class="relative overflow-x-auto border-t border-border-subtle" data-leave-calendar>
                <table class="w-max min-w-full border-separate border-spacing-0 text-caption-md" aria-label="{{ __('leave.calendar.grid_label') }}">
                    <thead>
                        <tr>
                            <th scope="col" class="sticky start-0 z-10 min-w-44 border-b border-e border-border-subtle bg-surface-default px-3 py-2 text-start text-label-sm text-text-secondary">{{ __('leave.fields.employee') }}</th>
                            @foreach ($days as $day)
                                @php
                                    $key = $day->format('Y-m-d');
                                    $isToday = $day->equalTo($today);
                                    $holiday = $grid['holidays'][$key] ?? null;
                                    $conflicts = $grid['conflicts'][$key] ?? [];
                                @endphp
                                <th scope="col" @class(['min-w-11 border-b border-border-subtle px-1 py-2 text-center font-normal', 'bg-feedback-info-bg text-text-link' => $isToday, 'bg-status-finalized-bg text-status-finalized-text' => $holiday && ! $isToday, 'text-text-secondary' => ! $isToday && ! $holiday])
                                    @if ($isToday) aria-current="date" @endif title="{{ $holiday }}">
                                    <span class="block text-label-sm">{{ $day->day }}</span>
                                    <span class="block">{{ $day->locale($locale)->translatedFormat('D') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grid['rows'] as $row)
                            @php $employee = $row['employee']; @endphp
                            <tr>
                                <th scope="row" class="sticky start-0 z-10 border-b border-e border-border-subtle bg-surface-default px-3 py-1.5 text-start font-normal">
                                    <span class="block max-w-44 truncate text-label-sm text-text-primary">{{ $employee->name }}</span>
                                    <span class="block text-text-tertiary" dir="ltr">{{ $employee->employee_number }}</span>
                                </th>
                                @foreach ($days as $day)
                                    @php $cell = $row['cells'][$day->format('Y-m-d')]; @endphp
                                    <td @class([
                                        'h-11 border-b border-border-subtle p-0.5 align-middle',
                                        'bg-surface-subtle' => $cell['rest'] && $cell['leave'] === [],
                                        'bg-status-finalized-bg' => $cell['holiday'] && $cell['leave'] === [],
                                        'bg-feedback-info-bg' => $day->equalTo($today) && ! $cell['rest'] && ! $cell['holiday'] && $cell['leave'] === [],
                                    ])>
                                        @if (! $cell['employed'])
                                            <span class="flex h-full items-center justify-center text-text-disabled">—</span>
                                        @else
                                            @foreach ($cell['leave'] as $leaveDay)
                                                @php $request = $leaveDay->request; @endphp
                                                <a href="{{ route('admin.leave-requests.show', $request->id) }}"
                                                    @class([
                                                        'flex h-full min-h-9 items-center justify-center rounded-xs focus-visible:shadow-focus focus-visible:outline-none',
                                                        $chip[$request->leaveType->color] ?? 'bg-chart-1' => $request->isTaken(),
                                                        'border-2 border-dashed border-text-tertiary bg-surface-default' => ! $request->isTaken(),
                                                        'h-1/2 min-h-4' => $leaveDay->day_part->isPartial(),
                                                    ])
                                                    title="{{ $employee->name }} · {{ $request->leaveType->name }} · {{ $request->status->label() }} · {{ $leaveDay->date->format('Y-m-d') }}{{ $leaveDay->day_part->isPartial() ? ' · '.$leaveDay->day_part->label() : '' }}">
                                                    <span class="sr-only">{{ $request->leaveType->name }} · {{ $request->status->label() }}</span>
                                                </a>
                                            @endforeach
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" class="sticky start-0 z-10 border-e border-border-subtle bg-surface-default px-3 py-2 text-start text-label-sm text-text-secondary">{{ __('leave.calendar.away') }}</th>
                            @foreach ($days as $day)
                                @php
                                    $key = $day->format('Y-m-d');
                                    $count = $grid['away'][$key] ?? 0;
                                    $conflicts = $grid['conflicts'][$key] ?? [];
                                @endphp
                                <td class="px-1 py-2 text-center tabular-nums" @if ($conflicts) title="{{ __('leave.calendar.conflict_title', ['departments' => implode(', ', $conflicts)]) }}" @endif>
                                    @if ($conflicts)
                                        <span class="inline-flex items-center gap-0.5 text-feedback-warning-text"><x-icon name="alert-triangle" size="sm" />{{ $count }}</span>
                                        <span class="sr-only">{{ __('leave.calendar.conflict_title', ['departments' => implode(', ', $conflicts)]) }}</span>
                                    @else
                                        <span class="{{ $count ? 'text-text-primary' : 'text-text-disabled' }}">{{ $count ?: '·' }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        <x-slot:footer>
            <x-pagination :paginator="$employees" />
        </x-slot:footer>
    </x-card>
@endsection
