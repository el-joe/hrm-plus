@extends('layouts.admin', ['breadcrumbs' => [['label' => __('payroll.entries.'.$resource.'.title'), 'url' => route('admin.'.$resource.'.index')]]])

@section('title', __('payroll.entries.'.$resource.'.create'))

@section('content')
    <form method="POST" action="{{ route('admin.'.$resource.'.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('payroll.entries.who')" :description="__('payroll.help.entry_target')">
            @include('admin.payroll._target-fields', ['scopes' => $targets, 'typeName' => 'target_type', 'selected' => $preset['target_type'], 'values' => ['employee_id' => $preset['employee_id']]])
        </x-form.section>

        <x-form.section :title="__('payroll.entries.what')">
            @include('admin.payroll.entries._fields')
        </x-form.section>

        <x-form.section :title="__('payroll.common.attachments')">
            <div class="md:col-span-2">
                <x-form.file-input name="attachments[]" id="attachments" :label="__('payroll.fields.attachments')" multiple :error-keys="['attachments', 'attachments.*']"
                    accept="{{ collect((array) setting('documents.allowed_extensions'))->map(fn ($ext) => '.'.$ext)->implode(',') }}" />
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.'.$resource.'.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
