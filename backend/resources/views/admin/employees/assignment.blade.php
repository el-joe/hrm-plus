@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('employees.index.title'), 'url' => route('admin.employees.index')],
        ['label' => $employee->name, 'url' => route('admin.employees.show', $employee)],
    ],
    'subtitle' => $employee->employee_number,
])

@section('title', __('employees.actions.'.$type->value))

@section('content')
    <form method="POST" action="{{ route('admin.employees.assignments.store', $employee) }}" class="flex flex-col gap-4 lg:gap-6" data-validate data-employee-form>
        @csrf
        <x-form.section :title="__('employees.assignment.change')" :description="__('employees.assignment.change_help')">
            <x-form.select name="change_type" :label="__('employees.fields.change_type')" required>
                @foreach ($options['changeTypes'] as $changeType)
                    <option value="{{ $changeType->value }}" @selected(old('change_type', $type->value) === $changeType->value)>{{ $changeType->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="effective_date" type="date" :label="__('employees.fields.effective_date')" required
                :value="old('effective_date', now()->toDateString())" :help="__('employees.form.effective_help')" />
            <div class="md:col-span-2">
                <x-form.textarea name="reason" :label="__('employees.fields.reason')" maxlength="1000" rows="2">{{ old('reason') }}</x-form.textarea>
            </div>
        </x-form.section>

        <x-form.section :title="__('employees.assignment.new_placement')" :description="__('employees.assignment.new_placement_help')">
            @include('admin.employees._placement-fields', ['values' => $employee->only(App\Models\Employee::PLACEMENT_COLUMNS)])
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.employees.tab', [$employee, 'employment'])">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('employees.assignment.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
