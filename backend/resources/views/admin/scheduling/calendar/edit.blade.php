@extends('layouts.admin', ['breadcrumbs' => [['label' => __('schedules.calendar.title'), 'url' => route('admin.schedule-calendar', ['date' => $date, 'view' => $view])]]])

@section('title', __('schedules.calendar.edit_day'))

@section('content')
    <x-card :title="__('schedules.calendar.edit_day_for', ['employee' => $employee->label(), 'date' => $date])">
        <p class="mb-4 text-body-sm text-text-secondary">
            {{ __('schedules.calendar.currently') }}:
            @forelse ($resolved->shifts as $scheduled)
                <span dir="ltr">{{ $scheduled->shift->code }} ({{ $scheduled->shift->timeRange() }})</span>@if (! $loop->last), @endif
            @empty
                {{ $resolved->isRestDay ? __('schedules.common.rest_day') : '—' }}
            @endforelse
            · {{ __('attendance.enums.schedule_sources.'.$resolved->source) }}
        </p>
        <form method="POST" action="{{ route('admin.schedule-calendar.override') }}" class="flex flex-col gap-3">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="view" value="{{ $view }}">
            @php $current = array_map(fn ($shift) => $shift->key(), $resolved->shifts); @endphp
            @foreach ($shifts as $shift)
                <x-form.checkbox name="shift_ids[]" :value="$shift->id" :id="'shift-'.$shift->id" :checked="in_array($shift->id, $current, true)">
                    {{ $shift->name }} <span dir="ltr" class="text-text-tertiary">{{ $shift->timeRange() }}</span>
                </x-form.checkbox>
            @endforeach
            <p class="text-caption-md text-text-tertiary">{{ __('schedules.calendar.rest_help') }}</p>
            <x-form.input name="note" :label="__('schedules.fields.note')" maxlength="500" />
            <div class="flex flex-wrap gap-2">
                <x-button>{{ __('schedules.calendar.save_day') }}</x-button>
            </div>
        </form>
        @if ($resolved->source === 'override')
            <form method="POST" action="{{ route('admin.schedule-calendar.override.clear') }}" class="mt-3">
                @csrf
                @method('DELETE')
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="view" value="{{ $view }}">
                <x-button variant="secondary">{{ __('schedules.calendar.clear_override') }}</x-button>
            </form>
        @endif
    </x-card>
@endsection
