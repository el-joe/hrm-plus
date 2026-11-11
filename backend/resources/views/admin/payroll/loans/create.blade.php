@extends('layouts.admin', ['breadcrumbs' => [['label' => __('payroll.loans.title'), 'url' => route('admin.loans.index')]]])

@section('title', __('payroll.loans.create_title'))

@section('content')
    <form method="GET" action="{{ route('admin.loans.create') }}" class="flex flex-col gap-3 rounded-lg border border-border-subtle bg-surface-default p-4 md:flex-row md:items-end">
        <x-form.select name="employee" id="pick-employee" :label="__('payroll.loans.fields.employee')" class="md:w-96">
            <option value="">—</option>
            @foreach ($employees as $row)
                <option value="{{ $row->id }}" @selected($employee?->id === $row->id)>{{ $row->label() }}</option>
            @endforeach
        </x-form.select>
        <x-button variant="secondary" icon="search">{{ __('payroll.loans.limits') }}</x-button>
    </form>

    <form method="POST" action="{{ route('admin.loans.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('payroll.loans.terms')" :description="__('payroll.loans.portal.new_request_help')">
            <x-form.select name="employee_id" :label="__('payroll.loans.fields.employee')" required>
                <option value="">—</option>
                @foreach ($employees as $row)
                    <option value="{{ $row->id }}" @selected((string) old('employee_id', $employee?->id) === (string) $row->id)>{{ $row->label() }}</option>
                @endforeach
            </x-form.select>
            <div class="hidden md:block" aria-hidden="true"></div>
            @include('admin.payroll.loans._request-fields')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.loans.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('payroll.loans.portal.send') }}</x-button>
        </x-form.actions>
    </form>
@endsection
