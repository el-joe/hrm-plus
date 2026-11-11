@extends('layouts.admin', ['subtitle' => __('schedules.assignments.subtitle')])

@section('title', __('schedules.assignments.title'))

@section('page-actions')
    @can('create', App\Models\ScheduleAssignment::class)
        <x-button :href="route('admin.schedule-assignments.create')" icon="plus">{{ __('schedules.assignments.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-alert tone="info">{{ __('schedules.assignments.precedence') }}</x-alert>

    <x-filter-bar :action="route('admin.schedule-assignments.index')" :reset="route('admin.schedule-assignments.index')">
        <x-form.select name="scope_type" :label="__('schedules.fields.scope_type')" hide-label class="md:w-48">
            <option value="">{{ __('schedules.fields.scope_type') }}: {{ __('app.common.all') }}</option>
            @foreach (App\Enums\AttendanceScope::forSchedules() as $type)
                <option value="{{ $type->value }}" @selected(($filters['scope_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="work_schedule_id" :label="__('schedules.fields.work_schedule_id')" hide-label class="md:w-56">
            <option value="">{{ __('schedules.fields.work_schedule_id') }}: {{ __('app.common.all') }}</option>
            @foreach ($schedules as $schedule)
                <option value="{{ $schedule->id }}" @selected((string) ($filters['work_schedule_id'] ?? '') === (string) $schedule->id)>{{ $schedule->code }} · {{ $schedule->name }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('schedules.assignments.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('schedules.assignments.target') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.work_schedule_id') }}</x-table.th>
                <x-table.th>{{ __('schedules.assignments.period') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.note') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('schedules.assignments.target')"><span class="text-label-md">{{ $record->scopeLabel() }}</span></x-table.td>
                <x-table.td :label="__('schedules.fields.work_schedule_id')">{{ $record->schedule->code }} · {{ $record->schedule->name }}</x-table.td>
                <x-table.td :label="__('schedules.assignments.period')" class="tabular-nums">
                    <span dir="ltr">{{ $record->effective_from->format('Y-m-d') }}</span> –
                    @if ($record->effective_to)
                        <span dir="ltr">{{ $record->effective_to->format('Y-m-d') }}</span>
                    @else
                        {{ __('schedules.common.ongoing') }}
                    @endif
                </x-table.td>
                <x-table.td :label="__('schedules.fields.note')" class="max-w-xs truncate text-body-sm text-text-secondary">{{ $record->note }}</x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        @can('update', $record)
                            <x-icon-button icon="pencil" size="sm" :href="route('admin.schedule-assignments.edit', $record)" :label="__('app.actions.edit')" />
                        @endcan
                        @can('delete', $record)
                            <form method="POST" action="{{ route('admin.schedule-assignments.destroy', $record) }}" data-confirm="{{ __('schedules.assignments.confirm_delete') }}"
                                data-confirm-label="{{ __('app.actions.delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endcan
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="users" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
