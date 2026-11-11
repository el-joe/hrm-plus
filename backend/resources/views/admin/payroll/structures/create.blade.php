@extends('layouts.admin', ['breadcrumbs' => [['label' => __('payroll.structures.title'), 'url' => route('admin.salary-structures.index')]]])

@section('title', __('payroll.structures.create'))

@section('content')
    <form method="POST" action="{{ route('admin.salary-structures.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('payroll.components.identity')">
            @include('admin.structure._identity-fields')
            @include('admin.structure._description-field')
            @include('admin.structure._active-switch')
        </x-form.section>

        <x-form.section :title="__('payroll.structures.lines')" :description="__('payroll.help.version', ['version' => 1])">
            <x-form.input type="date" name="effective_from" required :label="__('payroll.fields.effective_from')" :value="old('effective_from', $effectiveFrom)" />
            <x-form.input name="notes" maxlength="1000" :label="__('payroll.fields.notes')" :value="old('notes')" />
            @include('admin.payroll.structures._lines')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.salary-structures.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
