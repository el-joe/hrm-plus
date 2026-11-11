@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('employees.index.title'), 'url' => route('admin.employees.index')],
        ['label' => $employee->name, 'url' => route('admin.employees.show', $employee)],
    ],
    'subtitle' => $employee->employee_number,
])

@section('title', __('employees.actions.rehire'))

@section('content')
    @if ($employee->eligible_for_rehire === false)
        <x-alert tone="warning">{{ __('employees.rehire.not_eligible') }}</x-alert>
    @endif
    <form method="POST" action="{{ route('admin.employees.rehire.store', $employee) }}" class="flex flex-col gap-4 lg:gap-6" data-validate data-employee-form>
        @csrf
        <x-form.section :title="__('employees.rehire.details')" :description="__('employees.rehire.details_help', ['date' => $employee->termination_date?->toDateString()])">
            <x-form.input name="rehire_date" type="date" :label="__('employees.fields.rehire_date')" required :value="old('rehire_date', now()->toDateString())" />
            <div class="md:col-span-2">
                <x-form.textarea name="reason" :label="__('employees.fields.reason')" maxlength="1000" rows="2">{{ old('reason') }}</x-form.textarea>
            </div>
        </x-form.section>
        <x-form.section :title="__('employees.assignment.new_placement')" :description="__('employees.assignment.new_placement_help')">
            @include('admin.employees._placement-fields', ['values' => $employee->only(App\Models\Employee::PLACEMENT_COLUMNS)])
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.employees.show', $employee)">{{ __('app.actions.cancel') }}</x-button>
            <x-button icon="user-plus">{{ __('employees.actions.rehire') }}</x-button>
        </x-form.actions>
    </form>
@endsection
