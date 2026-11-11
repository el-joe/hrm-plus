@extends('layouts.admin', ['breadcrumbs' => [['label' => __('onboarding.onboarding.title'), 'url' => route('admin.onboarding.index')]]])

@section('title', __('onboarding.onboarding.create'))

@section('content')
    <form method="POST" action="{{ route('admin.onboarding.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('onboarding.onboarding.section')" :description="__('onboarding.onboarding.section_help')">
            <x-form.select name="employee_id" :label="__('onboarding.fields.employee_id')" required>
                <option value="">—</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) old('employee_id', $selectedEmployee) === (string) $employee->id)>{{ $employee->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="onboarding_template_id" :label="__('onboarding.fields.onboarding_template_id')" :help="__('onboarding.onboarding.template_help')">
                <option value="">{{ __('onboarding.onboarding.auto_template') }}</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}" @selected((string) old('onboarding_template_id') === (string) $template->id)>{{ $template->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="start_date" type="date" :label="__('onboarding.fields.start_date')" :value="old('start_date', today()->toDateString())" required />
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.onboarding.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('onboarding.onboarding.start') }}</x-button>
        </x-form.actions>
    </form>
@endsection
