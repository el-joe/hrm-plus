@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('employees.index.title'), 'url' => route('admin.employees.index')],
        ['label' => $employee->name, 'url' => route('admin.employees.tab', [$employee, 'contracts'])],
    ],
    'subtitle' => $employee->employee_number,
])

@section('title', $renewing ? __('employees.contracts.renew') : ($contract->exists ? __('employees.contracts.edit') : __('employees.contracts.add')))

@section('content')
    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($contract->exists)
            @method('PUT')
        @endif
        <x-form.section :title="__('employees.contracts.details')" :description="$renewing ? __('employees.contracts.renew_help', ['date' => $renewing->end_date->toDateString()]) : __('employees.contracts.details_help')">
            <x-form.select name="contract_type" :label="__('employees.fields.contract_type')" required>
                @foreach ($contractTypes as $type)
                    <option value="{{ $type->value }}" @selected(old('contract_type', $contract->contract_type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="reference" :label="__('employees.fields.reference')" :value="old('reference', $contract->reference)" maxlength="50" dir="ltr" />
            <x-form.input name="start_date" type="date" :label="__('employees.fields.start_date')" required :value="old('start_date', $contract->start_date?->toDateString())" />
            <x-form.input name="end_date" type="date" :label="__('employees.fields.end_date')" :value="old('end_date', $contract->end_date?->toDateString())" :help="__('employees.contracts.end_help')" />
            <div class="md:col-span-2">
                <x-form.textarea name="notes" :label="__('employees.fields.notes')" maxlength="1000" rows="2">{{ old('notes', $contract->notes) }}</x-form.textarea>
            </div>
            <div class="md:col-span-2">
                <x-form.file-input name="file" :label="__('employees.contracts.file')"
                    :help="($contract->file_name ? __('employees.contracts.current_file', ['name' => $contract->file_name]).' ' : '').__('employees.documents.file_help', ['types' => implode(', ', (array) setting('documents.allowed_extensions')), 'kb' => setting('documents.max_upload_size_kb')])" />
            </div>
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.employees.tab', [$employee, 'contracts'])">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
