@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('employees.index.title'), 'url' => route('admin.employees.index')],
        ['label' => $employee->name, 'url' => route('admin.employees.show', $employee)],
    ],
    'subtitle' => $employee->employee_number,
])

@section('title', __('employees.actions.terminate'))

@section('content')
    <x-alert tone="warning">{{ __('employees.termination.notice') }}</x-alert>
    <form method="POST" action="{{ route('admin.employees.terminate.store', $employee) }}" class="flex flex-col gap-4 lg:gap-6" data-validate
        data-confirm="{{ __('employees.termination.confirm', ['name' => $employee->name]) }}" data-confirm-label="{{ __('employees.actions.terminate') }}">
        @csrf
        <x-form.section :title="__('employees.termination.details')" :description="__('employees.termination.details_help')">
            <x-form.input name="termination_date" type="date" :label="__('employees.fields.termination_date')" required
                :value="old('termination_date', now()->toDateString())" :help="__('employees.form.effective_help')" />
            <x-form.select name="termination_type" :label="__('employees.fields.termination_type')" required>
                <option value="">—</option>
                @foreach ($options['terminationTypes'] as $terminationType)
                    <option value="{{ $terminationType->value }}" @selected(old('termination_type') === $terminationType->value)>{{ $terminationType->label() }}</option>
                @endforeach
            </x-form.select>
            <div class="md:col-span-2">
                <x-form.textarea name="reason" :label="__('employees.fields.termination_reason')" required maxlength="1000" rows="3">{{ old('reason') }}</x-form.textarea>
            </div>
            <div class="md:col-span-2">
                <input type="hidden" name="eligible_for_rehire" value="0">
                <x-form.switch name="eligible_for_rehire" :label="__('employees.fields.eligible_for_rehire')" :checked="(bool) old('eligible_for_rehire', true)" />
            </div>
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.employees.show', $employee)">{{ __('app.actions.cancel') }}</x-button>
            <x-button variant="danger" icon="log-out">{{ __('employees.actions.terminate') }}</x-button>
        </x-form.actions>
    </form>
@endsection
