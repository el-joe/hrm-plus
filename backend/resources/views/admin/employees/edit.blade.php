@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('employees.index.title'), 'url' => route('admin.employees.index')],
    ['label' => $employee->name, 'url' => route('admin.employees.show', $employee)],
]])

@section('title', __('employees.form.edit'))

@section('content')
    <form method="POST" action="{{ route('admin.employees.update', $employee) }}" enctype="multipart/form-data" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @method('PUT')
        @include('admin.employees._form')
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.employees.show', $employee)">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
