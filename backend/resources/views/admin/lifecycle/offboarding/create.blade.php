@extends('layouts.admin', ['breadcrumbs' => [['label' => __('onboarding.offboarding.title'), 'url' => route('admin.offboarding.index')]]])

@section('title', __('onboarding.offboarding.create'))

@section('content')
    <form method="POST" action="{{ route('admin.offboarding.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('onboarding.offboarding.section')" :description="__('onboarding.offboarding.section_help')">
            <div class="md:col-span-2">
                <x-form.select name="employee_id" :label="__('onboarding.fields.employee_id')" required>
                    <option value="">—</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id', $selectedEmployee) === (string) $employee->id)>{{ $employee->label() }}</option>
                    @endforeach
                </x-form.select>
            </div>
            @include('admin.lifecycle.offboarding._case-fields')
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.offboarding.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('onboarding.offboarding.open') }}</x-button>
        </x-form.actions>
    </form>
@endsection
