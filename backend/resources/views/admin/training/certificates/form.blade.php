@extends('layouts.admin', ['breadcrumbs' => [['label' => __('training.certificates.title'), 'url' => route('admin.training-certificates.index')]]])

@section('title', $certificate->exists ? __('training.certificates.edit') : __('training.certificates.create'))

@section('content')
    <form method="POST" action="{{ $certificate->exists ? route('admin.training-certificates.update', $certificate) : route('admin.training-certificates.store') }}"
        class="flex flex-col gap-4 lg:gap-6" enctype="multipart/form-data" data-validate>
        @csrf
        @if ($certificate->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('training.certificates.one')" :description="$certificate->exists ? $certificate->employee->label() : __('training.certificates.form_help')">
            @unless ($certificate->exists)
                <div class="md:col-span-2">
                    <x-form.select name="employee_id" :label="__('training.fields.employee')" required>
                        <option value="">—</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) old('employee_id', $certificate->employee_id) === (string) $employee->id)>{{ $employee->label() }}</option>
                        @endforeach
                    </x-form.select>
                </div>
            @endunless
            @include('admin.training.certificates._fields', ['fileRequired' => false])
            <x-form.select name="training_course_id" :label="__('training.fields.course')">
                <option value="">—</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected((string) old('training_course_id', $certificate->training_course_id) === (string) $course->id)>{{ $course->name }}</option>
                @endforeach
            </x-form.select>
            @if ($certificate->hasFile())
                <p class="text-body-sm text-text-secondary">{{ __('training.certificates.current_file') }}:
                    <a class="text-text-link hover:underline" href="{{ route('admin.training-certificates.download', $certificate) }}">{{ $certificate->file_name }}</a></p>
            @endif
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.training-certificates.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
