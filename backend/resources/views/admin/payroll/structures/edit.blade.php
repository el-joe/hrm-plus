@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('payroll.structures.title'), 'url' => route('admin.salary-structures.index')],
    ['label' => $record->name, 'url' => route('admin.salary-structures.show', $record)],
]])

@section('title', __('payroll.structures.edit'))

@section('content')
    <form method="POST" action="{{ route('admin.salary-structures.update', $record) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @method('PUT')
        <x-form.section :title="__('payroll.components.identity')">
            @include('admin.structure._identity-fields')
            @include('admin.structure._description-field')
            @include('admin.structure._active-switch')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.salary-structures.show', $record)">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
