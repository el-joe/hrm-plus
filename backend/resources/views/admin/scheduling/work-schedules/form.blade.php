@extends('layouts.admin', ['breadcrumbs' => [['label' => __('schedules.work_schedules.title'), 'url' => route('admin.work-schedules.index')]]])

@section('title', $record->exists ? __('schedules.work_schedules.edit') : __('schedules.work_schedules.create'))

@php
    $type = old('type', $record->type?->value ?? 'weekly');
    $selected = function (string $grid, int $index) use ($type, $pattern): array {
        $old = old('pattern.'.$grid.'.'.$index);

        if (is_array(old('pattern'))) {
            return array_map('intval', (array) $old);
        }

        return $grid === $type ? ($pattern[$index] ?? []) : [];
    };
    $sunday = Carbon\CarbonImmutable::create(2026, 11, 1)->locale(app()->getLocale());
    $grids = [
        'weekly' => array_map(fn (int $day) => [$day, $sunday->addDays($day)->translatedFormat('l')], $weekdays),
        'rotating' => array_map(fn (int $day) => [$day, __('schedules.work_schedules.day_n', ['n' => $day + 1])], range(0, $maxCycle - 1)),
        'monthly' => array_map(fn (int $day) => [$day, __('schedules.work_schedules.month_day', ['n' => $day])], range(1, 31)),
    ];
@endphp

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.work-schedules.update', $record) : route('admin.work-schedules.store') }}"
        class="flex flex-col gap-4 lg:gap-6" data-validate data-work-schedule-form>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
            @include('admin.structure._identity-fields')
        </x-form.section>

        <x-form.section :title="__('schedules.work_schedules.rotation')" :description="__('schedules.work_schedules.rotation_help')">
            <x-form.select name="type" :label="__('schedules.fields.type')" required data-schedule-type>
                @foreach ($types as $option)
                    <option value="{{ $option->value }}" @selected($type === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </x-form.select>
            <div class="hidden md:block" aria-hidden="true"></div>
            <x-form.input type="number" name="cycle_length_days" :label="__('schedules.fields.cycle_length_days')" min="2" :max="$maxCycle"
                :value="old('cycle_length_days', $record->cycle_length_days)" data-cycle-length data-rotating-field />
            <x-form.input type="date" name="cycle_start_date" :label="__('schedules.fields.cycle_start_date')"
                :value="old('cycle_start_date', $record->cycle_start_date?->format('Y-m-d'))" data-rotating-field />
        </x-form.section>

        <x-form.section :title="__('schedules.work_schedules.pattern')" :description="__('schedules.work_schedules.pattern_help')" :columns="1">
            @error('pattern')<p class="text-body-sm text-feedback-danger-text">{{ $message }}</p>@enderror
            @foreach ($grids as $grid => $days)
                <fieldset class="flex flex-col gap-2" data-pattern-grid="{{ $grid }}">
                    <legend class="mb-2 text-label-md text-text-secondary">{{ App\Enums\ScheduleType::from($grid)->label() }}</legend>
                    @foreach ($days as [$index, $label])
                        @php $chosen = $selected($grid, $index); @endphp
                        <div class="flex flex-col gap-2 rounded-md border border-border-subtle px-3 py-2 sm:flex-row sm:items-center" data-pattern-row data-row-index="{{ $loop->index }}">
                            <span class="text-label-md text-text-primary sm:w-40">{{ $label }}</span>
                            <div class="flex flex-1 flex-wrap gap-x-4 gap-y-2">
                                @foreach ($shifts as $shift)
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-body-sm">
                                        <input type="checkbox" name="pattern[{{ $grid }}][{{ $index }}][]" value="{{ $shift->id }}"
                                            class="size-4 rounded-xs accent-action-primary-default" @checked(in_array($shift->id, $chosen, true))>
                                        @include('admin.scheduling._color-swatch', ['color' => $shift->color])
                                        <span dir="ltr">{{ $shift->code }}</span>
                                        <span class="text-text-tertiary" dir="ltr">{{ $shift->timeRange() }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <span class="text-caption-md text-text-tertiary" data-rest-label @if ($chosen !== []) hidden @endif>{{ __('schedules.common.rest') }}</span>
                        </div>
                    @endforeach
                </fieldset>
            @endforeach
        </x-form.section>

        <x-form.section :title="__('structure.common.details')">
            @include('admin.structure._description-field')
            @include('admin.structure._active-switch')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.work-schedules.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
