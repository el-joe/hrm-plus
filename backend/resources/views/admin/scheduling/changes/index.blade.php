@extends('layouts.admin', ['subtitle' => __('schedules.changes.subtitle')])

@section('title', __('schedules.changes.title'))

@section('page-actions')
    @can('create', App\Models\ScheduleChangeRequest::class)
        <x-button :href="route('admin.schedule-changes.create')" icon="plus">{{ __('schedules.changes.create') }}</x-button>
    @endcan
@endsection

@php
    $shiftList = fn (array $shifts) => $shifts === [] ? __('schedules.common.rest') : implode(', ', array_map(fn ($scheduled) => $scheduled->shift->code, $shifts));
    $tones = ['pending' => 'pending', 'approved' => 'approved', 'rejected' => 'rejected', 'cancelled' => 'draft'];
@endphp

@section('content')
    <x-filter-bar :action="route('admin.schedule-changes.index')" :reset="route('admin.schedule-changes.index')">
        <x-form.select name="status" :label="__('schedules.fields.status')" hide-label class="md:w-48">
            <option value="">{{ __('schedules.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach (App\Enums\RequestStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('schedules.changes.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('schedules.fields.employee_id') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.work_date') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.type') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.requested') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.current') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.status') }}</x-table.th>
                <x-table.th class="min-w-64">{{ __('app.common.actions') }}</x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('schedules.fields.employee_id')">
                    <span class="block text-label-md">{{ $record->employee->label() }}</span>
                    <span class="block text-caption-md text-text-tertiary">{{ $record->reason }}</span>
                    @if ($record->requester)
                        <span class="block text-caption-md text-text-tertiary">{{ __('schedules.fields.requested_by') }}: {{ $record->requester->name }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('schedules.fields.work_date')" class="tabular-nums"><span dir="ltr">{{ $record->work_date->format('Y-m-d') }}</span></x-table.td>
                <x-table.td :label="__('schedules.fields.type')">{{ $record->type->label() }}</x-table.td>
                <x-table.td :label="__('schedules.fields.requested')">
                    @if ($record->type === App\Enums\ScheduleChangeType::Swap)
                        {{ __('schedules.fields.swap_employee_id') }}: {{ $record->swapEmployee?->label() }}
                    @else
                        {{ $record->shift ? $record->shift->code.' ('.$record->shift->timeRange().')' : __('schedules.changes.day_off') }}
                    @endif
                </x-table.td>
                <x-table.td :label="__('schedules.fields.current')" class="text-body-sm">
                    @foreach ($current[$record->id] ?? [] as $employeeId => $shifts)
                        <span class="block" dir="auto">{{ $employeeId === $record->employee_id ? $record->employee->employee_number : $record->swapEmployee?->employee_number }}: {{ $shiftList($shifts) }}</span>
                    @endforeach
                </x-table.td>
                <x-table.td :label="__('schedules.fields.status')"><x-status-badge :state="$tones[$record->status->value]">{{ $record->status->label() }}</x-status-badge></x-table.td>
                <x-table.td>
                    @can('decide', $record)
                        <form method="POST" action="{{ route('admin.schedule-changes.decide', [$record, 'approve']) }}" class="flex flex-col gap-2">
                            @csrf
                            <x-form.input name="comment" :id="'comment-'.$record->id" :label="__('schedules.changes.reject_reason')" hide-label :placeholder="__('schedules.changes.reject_reason')" maxlength="1000" />
                            <div class="flex gap-2">
                                <x-button size="sm" icon="check">{{ __('schedules.changes.approve') }}</x-button>
                                <x-button size="sm" variant="danger" icon="x" formaction="{{ route('admin.schedule-changes.decide', [$record, 'reject']) }}">{{ __('schedules.changes.reject') }}</x-button>
                            </div>
                        </form>
                    @endcan
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="history" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
