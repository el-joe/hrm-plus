@extends('layouts.admin', ['breadcrumbs' => [['label' => __('schedules.shifts.title'), 'url' => route('admin.shifts.index')]]])

@section('title', $record->exists ? __('schedules.shifts.edit') : __('schedules.shifts.create'))

@php
    $time = fn ($value) => $value ? substr((string) $value, 0, 5) : '';
    $type = old('type', $record->type?->value ?? 'fixed');
    $rows = old('breaks') ?? $breaks->map(fn ($break) => [
        'name' => $break->name, 'type' => $break->type->value, 'start_time' => $time($break->start_time),
        'end_time' => $time($break->end_time), 'duration_minutes' => $break->duration_minutes, 'is_paid' => $break->is_paid,
    ])->all();
    // Without JavaScript the form offers every row; with it, empty rows beyond the first are added on demand.
    $rows = array_values($rows);
    $rows = array_pad($rows, $maxBreaks, []);
@endphp

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.shifts.update', $record) : route('admin.shifts.store') }}"
        class="flex flex-col gap-4 lg:gap-6" data-validate data-shift-form>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
            @include('admin.structure._identity-fields')
        </x-form.section>

        <x-form.section :title="__('schedules.shifts.timing')" :description="__('schedules.shifts.timing_help')">
            <x-form.select name="type" :label="__('schedules.fields.type')" required data-shift-type>
                @foreach ($types as $option)
                    <option value="{{ $option->value }}" @selected($type === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </x-form.select>
            <div class="hidden md:block" aria-hidden="true"></div>
            <x-form.input type="time" name="start_time" :label="__('schedules.fields.start_time')" :value="old('start_time', $time($record->start_time))" required dir="ltr" />
            <x-form.input type="time" name="end_time" :label="__('schedules.fields.end_time')" :value="old('end_time', $time($record->end_time))" required dir="ltr" />
        </x-form.section>

        <x-form.section :title="__('schedules.shifts.flexible')" :description="__('schedules.shifts.flexible_help')" data-flexible-fields>
            <x-form.input type="number" name="required_minutes" :label="__('schedules.fields.required_minutes')" :value="old('required_minutes', $record->required_minutes)" min="1" max="1440" />
            <div class="hidden md:block" aria-hidden="true"></div>
            <x-form.input type="time" name="core_start_time" :label="__('schedules.fields.core_start_time')" :value="old('core_start_time', $time($record->core_start_time))" dir="ltr" />
            <x-form.input type="time" name="core_end_time" :label="__('schedules.fields.core_end_time')" :value="old('core_end_time', $time($record->core_end_time))" dir="ltr" />
        </x-form.section>

        <x-form.section :title="__('schedules.shifts.window')" :description="__('schedules.shifts.window_help')">
            <x-form.input type="number" name="checkin_opens_before_minutes" :label="__('schedules.fields.checkin_opens_before_minutes')"
                :value="old('checkin_opens_before_minutes', $record->checkin_opens_before_minutes)" required min="0" max="720" />
            <x-form.input type="number" name="checkout_closes_after_minutes" :label="__('schedules.fields.checkout_closes_after_minutes')"
                :value="old('checkout_closes_after_minutes', $record->checkout_closes_after_minutes)" required min="0" max="720" />
            <x-form.input type="number" name="late_grace_minutes" :label="__('schedules.fields.late_grace_minutes')"
                :value="old('late_grace_minutes', $record->late_grace_minutes)" min="0" max="240" :placeholder="__('schedules.common.policy_default')" />
            <x-form.input type="number" name="early_leave_grace_minutes" :label="__('schedules.fields.early_leave_grace_minutes')"
                :value="old('early_leave_grace_minutes', $record->early_leave_grace_minutes)" min="0" max="240" :placeholder="__('schedules.common.policy_default')" />
        </x-form.section>

        <x-form.section :title="__('schedules.shifts.breaks')" :description="__('schedules.shifts.breaks_help')" :columns="1">
            <div class="flex flex-col gap-3" data-break-rows>
                @foreach ($rows as $index => $row)
                    @include('admin.scheduling.shifts._break-row', ['index' => $index, 'row' => $row, 'hideWhenEmpty' => $index > 0])
                @endforeach
            </div>
            <div hidden data-js-only>
                <x-button type="button" variant="secondary" size="sm" icon="plus" data-add-break>{{ __('schedules.shifts.add_break') }}</x-button>
            </div>
        </x-form.section>

        <x-form.section :title="__('schedules.shifts.display')">
            <x-form.field :label="__('schedules.fields.color')">
                <div class="flex flex-wrap gap-3" role="radiogroup">
                    @foreach ($colors as $color)
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-border-subtle px-2.5 py-1.5 has-checked:border-border-focus has-checked:shadow-focus">
                            <input type="radio" name="color" value="{{ $color }}" class="sr-only" @checked(old('color', $record->color) === $color)>
                            @include('admin.scheduling._color-swatch', ['color' => $color])
                            <span class="text-caption-md" dir="ltr">{{ $loop->iteration }}</span>
                        </label>
                    @endforeach
                </div>
            </x-form.field>
            <div class="flex items-end">
                <input type="hidden" name="is_night" value="0">
                <x-form.switch name="is_night" :label="__('schedules.fields.is_night')" :checked="(bool) old('is_night', $record->is_night)" />
            </div>
            @include('admin.structure._description-field')
            @include('admin.structure._active-switch')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.shifts.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
