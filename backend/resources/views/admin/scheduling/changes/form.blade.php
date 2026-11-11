@extends('layouts.admin', ['breadcrumbs' => [['label' => __('schedules.changes.title'), 'url' => route('admin.schedule-changes.index')]]])

@section('title', __('schedules.changes.create'))

@section('content')
    <form method="POST" action="{{ route('admin.schedule-changes.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate data-schedule-change-form>
        @csrf
        <x-form.section :title="__('schedules.changes.create')" :description="__('schedules.changes.swap_help')">
            <x-form.select name="type" :label="__('schedules.fields.type')" required data-change-type>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', 'change') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="date" name="work_date" :label="__('schedules.fields.work_date')" :value="old('work_date', $prefill['work_date'] ?? '')" required />
            <x-form.select name="employee_id" :label="__('schedules.fields.employee_id')" required>
                <option value="">—</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) old('employee_id', $prefill['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->label() }}</option>
                @endforeach
            </x-form.select>
            <div data-change-field="change">
                <x-form.select name="shift_id" :label="__('schedules.fields.shift_id')">
                    <option value="">{{ __('schedules.changes.day_off') }}</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string) old('shift_id') === (string) $shift->id)>{{ $shift->code }} · {{ $shift->name }} ({{ $shift->timeRange() }})</option>
                    @endforeach
                </x-form.select>
            </div>
            <div data-change-field="swap">
                <x-form.select name="swap_employee_id" :label="__('schedules.fields.swap_employee_id')">
                    <option value="">—</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('swap_employee_id') === (string) $employee->id)>{{ $employee->label() }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="md:col-span-2">
                <x-form.textarea name="reason" :label="__('schedules.fields.reason')" rows="2" maxlength="1000" required>{{ old('reason') }}</x-form.textarea>
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.schedule-changes.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('schedules.changes.create') }}</x-button>
        </x-form.actions>
    </form>
@endsection
