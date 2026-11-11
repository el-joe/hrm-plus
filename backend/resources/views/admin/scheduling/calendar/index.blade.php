@extends('layouts.admin', ['subtitle' => __('schedules.calendar.subtitle')])

@section('title', __('schedules.calendar.title'))

@php
    $chip = ['chart-1' => 'bg-chart-1', 'chart-2' => 'bg-chart-2', 'chart-3' => 'bg-chart-3', 'chart-4' => 'bg-chart-4', 'chart-5' => 'bg-chart-5', 'chart-6' => 'bg-chart-6'];
    $query = fn (array $extra) => route('admin.schedule-calendar', array_filter([...$filters, 'view' => $view, ...$extra]));
    $locale = app()->getLocale();
@endphp

@section('page-actions')
    <div class="flex items-center gap-1" role="group" aria-label="{{ __('schedules.calendar.title') }}">
        <x-button variant="{{ $view === 'month' ? 'primary' : 'secondary' }}" size="sm" :href="$query(['view' => 'month', 'date' => $anchor->format('Y-m-d')])">{{ __('schedules.calendar.month') }}</x-button>
        <x-button variant="{{ $view === 'week' ? 'primary' : 'secondary' }}" size="sm" :href="$query(['view' => 'week', 'date' => $anchor->format('Y-m-d')])">{{ __('schedules.calendar.week') }}</x-button>
    </div>
@endsection

@section('content')
    <x-filter-bar :action="route('admin.schedule-calendar')" :reset="route('admin.schedule-calendar', ['view' => $view])">
        <input type="hidden" name="view" value="{{ $view }}">
        <input type="hidden" name="date" value="{{ $anchor->format('Y-m-d') }}">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="branch_id" :label="__('schedules.fields.branch_id')" hide-label class="md:w-44">
            <option value="">{{ __('schedules.fields.branch_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="department_id" :label="__('schedules.fields.department_id')" hide-label class="md:w-48">
            <option value="">{{ __('schedules.fields.department_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="group_id" :label="__('schedules.fields.group_id')" hide-label class="md:w-44">
            <option value="">{{ __('schedules.fields.group_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($groups as $group)
                <option value="{{ $group->id }}" @selected((string) ($filters['group_id'] ?? '') === (string) $group->id)>{{ $group->name }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-card padding="none">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-1">
                <x-icon-button icon="chevron-left" flip size="sm" :href="$query(['date' => $previous->format('Y-m-d')])" :label="__('schedules.calendar.previous')" />
                <x-button variant="ghost" size="sm" :href="$query(['date' => $today->format('Y-m-d')])">{{ __('schedules.calendar.today') }}</x-button>
                <x-icon-button icon="chevron-right" flip size="sm" :href="$query(['date' => $next->format('Y-m-d')])" :label="__('schedules.calendar.next')" />
            </div>
            <h2 class="text-h5 text-text-primary">
                @if ($view === 'month')
                    {{ $anchor->locale($locale)->translatedFormat('F Y') }}
                @else
                    <span dir="ltr">{{ $days[0]->format('Y-m-d') }} – {{ end($days)->format('Y-m-d') }}</span>
                @endif
            </h2>
            <ul class="flex flex-wrap items-center gap-x-3 gap-y-1 text-caption-md text-text-secondary" aria-label="{{ __('schedules.calendar.legend') }}">
                @foreach ($shifts as $shift)
                    <li class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full {{ $chip[$shift->color] ?? 'bg-chart-1' }}" aria-hidden="true"></span>
                        <span dir="ltr">{{ $shift->code }}</span> <span dir="ltr" class="text-text-tertiary">{{ $shift->timeRange() }}</span>
                    </li>
                @endforeach
                <li class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-status-finalized-dot" aria-hidden="true"></span>{{ __('schedules.calendar.holiday') }}</li>
                <li class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full border-2 border-action-primary-default" aria-hidden="true"></span>{{ __('schedules.calendar.override') }}</li>
            </ul>
        </div>

        @if ($rows->isEmpty())
            <x-empty-state icon="calendar" :title="__('schedules.calendar.no_employees')" class="py-10" />
        @else
            <div class="overflow-x-auto border-t border-border-subtle" data-schedule-calendar>
                <table class="w-max min-w-full border-separate border-spacing-0 text-caption-md" aria-label="{{ __('schedules.calendar.grid_label') }}">
                    <thead>
                        <tr>
                            <th scope="col" class="sticky start-0 z-10 min-w-44 border-b border-e border-border-subtle bg-surface-default px-3 py-2 text-start text-label-sm text-text-secondary">{{ __('schedules.fields.employee_id') }}</th>
                            @foreach ($days as $day)
                                @php $isToday = $day->equalTo($today); @endphp
                                <th scope="col" @class(['min-w-14 border-b border-border-subtle px-1 py-2 text-center font-normal', 'bg-feedback-info-bg text-text-link' => $isToday, 'text-text-secondary' => ! $isToday])
                                    @if ($isToday) aria-current="date" @endif>
                                    <span class="block text-label-sm">{{ $day->day }}</span>
                                    <span class="block">{{ $day->locale($locale)->translatedFormat('D') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $employee = $row['employee']; @endphp
                            <tr>
                                <th scope="row" class="sticky start-0 z-10 border-b border-e border-border-subtle bg-surface-default px-3 py-1.5 text-start font-normal">
                                    <span class="block max-w-44 truncate text-label-sm text-text-primary">{{ $employee->name }}</span>
                                    <span class="block text-text-tertiary" dir="ltr">{{ $employee->employee_number }}</span>
                                </th>
                                @foreach ($days as $day)
                                    @php
                                        $resolved = $row['days'][$day->format('Y-m-d')];
                                        $isOverride = $resolved->source === 'override';
                                        $shiftIds = implode(',', array_map(fn ($shift) => $shift->key(), $resolved->shifts));
                                        $label = $employee->name.' · '.$day->format('Y-m-d');
                                    @endphp
                                    <td @class(['relative h-12 border-b border-border-subtle p-0.5 align-top', 'bg-feedback-info-bg' => $day->equalTo($today)])>
                                        @if (! $resolved->isEmployed())
                                            <span class="flex h-full items-center justify-center text-text-disabled" title="{{ __('schedules.calendar.not_employed') }}">—</span>
                                        @else
                                            @php $tag = $canEdit ? 'a' : 'div'; @endphp
                                            <{{ $tag }} @if ($canEdit) href="{{ route('admin.schedule-calendar.edit', ['employee_id' => $employee->id, 'date' => $day->format('Y-m-d'), 'view' => $view]) }}"
                                                data-calendar-cell data-employee-id="{{ $employee->id }}" data-employee-name="{{ $employee->name }}"
                                                data-date="{{ $day->format('Y-m-d') }}" data-shift-ids="{{ $shiftIds }}" data-override="{{ $isOverride ? '1' : '0' }}" @endif
                                                @class(['flex h-full min-h-11 flex-col items-stretch gap-0.5 rounded-sm p-0.5', 'hover:bg-surface-subtle focus-visible:shadow-focus focus-visible:outline-none' => $canEdit, 'ring-2 ring-inset ring-action-primary-default' => $isOverride])
                                                title="{{ $label }}{{ $resolved->holiday ? ' · '.$resolved->holiday->name : '' }}{{ $isOverride ? ' · '.__('schedules.calendar.override') : '' }}">
                                                @if ($resolved->holiday)
                                                    <span class="truncate rounded-xs bg-status-finalized-bg px-1 text-status-finalized-text">{{ $resolved->holiday->name }}</span>
                                                @endif
                                                @forelse ($resolved->shifts as $scheduled)
                                                    <span class="inline-flex items-center gap-1 rounded-xs bg-surface-subtle px-1 text-text-primary">
                                                        <span class="size-2 shrink-0 rounded-full {{ $chip[$scheduled->shift->color] ?? 'bg-chart-1' }}" aria-hidden="true"></span>
                                                        <span dir="ltr">{{ $scheduled->shift->code }}</span>
                                                    </span>
                                                @empty
                                                    <span class="px-1 text-text-tertiary">{{ $resolved->isRestDay ? __('schedules.common.rest') : '·' }}</span>
                                                @endforelse
                                                @if ($isOverride)
                                                    <span class="sr-only">{{ __('schedules.calendar.override') }}</span>
                                                @endif
                                            </{{ $tag }}>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <x-slot:footer>
            <x-pagination :paginator="$employees" />
        </x-slot:footer>
    </x-card>

    @if ($canEdit)
        <x-modal id="calendar-day-modal" :title="__('schedules.calendar.edit_day')">
            <p class="mb-4 text-body-md text-text-secondary" data-calendar-modal-subject></p>
            <form method="POST" action="{{ route('admin.schedule-calendar.override') }}" id="calendar-override-form" class="flex flex-col gap-3">
                @csrf
                <input type="hidden" name="employee_id" value="" data-calendar-field="employee_id">
                <input type="hidden" name="date" value="" data-calendar-field="date">
                <input type="hidden" name="view" value="{{ $view }}">
                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-label-md">{{ __('schedules.fields.shift_ids') }}</legend>
                    @foreach ($shifts as $shift)
                        <label class="inline-flex items-center gap-2 text-body-md">
                            <input type="checkbox" name="shift_ids[]" value="{{ $shift->id }}" class="size-4 rounded-xs accent-action-primary-default" data-calendar-shift>
                            <span class="size-2.5 rounded-full {{ $chip[$shift->color] ?? 'bg-chart-1' }}" aria-hidden="true"></span>
                            <span>{{ $shift->name }}</span> <span dir="ltr" class="text-text-tertiary">{{ $shift->timeRange() }}</span>
                        </label>
                    @endforeach
                    <p class="text-caption-md text-text-tertiary">{{ __('schedules.calendar.rest_help') }}</p>
                </fieldset>
                <x-form.input name="note" id="calendar-note" :label="__('schedules.fields.note')" maxlength="500" />
            </form>
            <form method="POST" action="{{ route('admin.schedule-calendar.override.clear') }}" id="calendar-clear-form" data-calendar-clear>
                @csrf
                @method('DELETE')
                <input type="hidden" name="employee_id" value="" data-calendar-field="employee_id">
                <input type="hidden" name="date" value="" data-calendar-field="date">
                <input type="hidden" name="view" value="{{ $view }}">
            </form>
            <x-slot:footer>
                <x-button type="submit" variant="ghost" form="calendar-clear-form" data-calendar-clear-button>{{ __('schedules.calendar.clear_override') }}</x-button>
                <x-button type="button" variant="secondary" data-modal-close>{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit" form="calendar-override-form">{{ __('schedules.calendar.save_day') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
@endsection
