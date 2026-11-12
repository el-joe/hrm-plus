@extends('layouts.admin', ['breadcrumbs' => [['label' => __('disciplinary.cases.title'), 'url' => route('admin.disciplinary-cases.index')]]])

@section('title', __('disciplinary.cases.create'))

@section('content')
    <form method="POST" action="{{ route('admin.disciplinary-cases.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('disciplinary.cases.incident')" :description="__('disciplinary.cases.create_help')">
            <x-form.select name="employee_id" :label="__('disciplinary.fields.employee')" required>
                <option value="">—</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) old('employee_id', $selectedEmployee) === (string) $employee->id)>{{ $employee->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="violation_type_id" :label="__('disciplinary.fields.violation_type')" required>
                <option value="">—</option>
                @foreach ($violationTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) old('violation_type_id') === (string) $type->id)>{{ $type->name }} · {{ $type->severity->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="date" name="incident_date" :label="__('disciplinary.fields.incident_date')" :value="old('incident_date', now()->toDateString())" :max="now()->toDateString()" required />
            <x-form.input name="location" :label="__('disciplinary.fields.location')" :value="old('location')" maxlength="255" />
            <div class="md:col-span-2">
                <x-form.textarea name="description" :label="__('disciplinary.fields.description')" rows="6" maxlength="10000" required>{{ old('description') }}</x-form.textarea>
            </div>
            <div class="md:col-span-2">
                <x-form.file-input name="evidence[]" :label="__('disciplinary.fields.evidence')" error="evidence" :error-keys="['evidence.*']" multiple
                    :help="__('employees.documents.file_help', ['types' => implode(', ', (array) setting('documents.allowed_extensions')), 'kb' => setting('documents.max_upload_size_kb')])" />
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.disciplinary-cases.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button icon="check">{{ __('disciplinary.cases.create') }}</x-button>
        </x-form.actions>
    </form>
@endsection
