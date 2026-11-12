@extends('layouts.admin', ['breadcrumbs' => [['label' => __('hr_requests.queue.title'), 'url' => route('admin.hr-requests.index')]]])

@section('title', __('hr_requests.create.title'))

@section('content')
    {{-- Choosing the employee reloads the page (GET) so a profile correction shows their current data. --}}
    <form method="GET" action="{{ route('admin.hr-requests.create') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" data-region="employee-picker">
        <x-form.select name="employee" :label="__('hr_requests.fields.employee')" :help="__('hr_requests.create.employee_hint')" class="sm:w-96">
            <option value="">—</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected($selectedEmployee === $employee->id)>{{ $employee->employee_number }} · {{ $employee->name }}</option>
            @endforeach
        </x-form.select>
        <x-button variant="secondary" type="submit">{{ __('app.actions.filter') }}</x-button>
    </form>

    <form method="POST" action="{{ route('admin.hr-requests.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('hr_requests.create.request')" :description="__('hr_requests.create.request_help')">
            <div class="md:col-span-2">
            <x-form.select name="employee_id" :label="__('hr_requests.fields.employee')" required>
                <option value="">—</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) old('employee_id', $selectedEmployee) === (string) $employee->id)>{{ $employee->employee_number }} · {{ $employee->name }}</option>
                @endforeach
            </x-form.select>
            </div>
            @include('admin.hr-requests._request-fields', ['selected' => request()->integer('category') ?: null])
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.hr-requests.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button icon="send">{{ __('hr_requests.create.submit') }}</x-button>
        </x-form.actions>
    </form>
@endsection
