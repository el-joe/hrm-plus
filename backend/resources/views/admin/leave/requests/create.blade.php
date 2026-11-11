@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('leave.requests.title'), 'url' => route('admin.leave-requests.index')]],
    'subtitle' => __('leave.requests.create_help'),
])

@section('title', __('leave.requests.create'))

@section('content')
    <x-card :title="__('leave.fields.employee')">
        <form method="GET" action="{{ route('admin.leave-requests.create') }}" class="flex flex-col gap-3 md:flex-row md:items-end">
            <x-form.select name="employee_id" :label="__('leave.fields.employee')" class="md:w-96" required>
                <option value="">—</option>
                @foreach ($employees as $option)
                    <option value="{{ $option->id }}" @selected($employee?->id === $option->id)>{{ $option->label() }}</option>
                @endforeach
            </x-form.select>
            <x-button variant="secondary" icon="search">{{ __('leave.requests.load_employee') }}</x-button>
        </form>
    </x-card>

    @if ($employee)
        <x-card :title="__('leave.balances.title_for', ['name' => $employee->name])">
            @include('admin.leave._balance-cards', ['compact' => true])
        </x-card>

        <form method="POST" action="{{ route('admin.leave-requests.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4 lg:gap-6" data-validate data-leave-form>
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
            <x-form.section :title="__('leave.requests.details')" :description="__('leave.requests.admin_rules_help')">
                @include('admin.leave._request-fields', ['allowHalfDay' => (bool) setting('leave.allow_half_day')])
                @can('leaves.manage')
                    @if (auth()->user()->employee_id !== $employee->id)
                        <div class="md:col-span-2">
                            <input type="hidden" name="record_as_approved" value="0">
                            <x-form.switch name="record_as_approved" :label="__('leave.requests.record_as_approved')" :description="__('leave.requests.record_as_approved_help')" :checked="(bool) old('record_as_approved')" />
                        </div>
                    @endif
                @endcan
            </x-form.section>
            <x-form.actions>
                <x-button variant="secondary" :href="route('admin.leave-requests.index')">{{ __('app.actions.cancel') }}</x-button>
                <x-button icon="check">{{ __('leave.requests.submit') }}</x-button>
            </x-form.actions>
        </form>
    @endif
@endsection
