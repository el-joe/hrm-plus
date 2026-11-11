@extends('layouts.admin', ['breadcrumbs' => [['label' => __('employees.index.title'), 'url' => route('admin.employees.index')]]])

@section('title', __('employees.index.create'))

@section('content')
    <form method="POST" action="{{ route('admin.employees.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4 lg:gap-6" data-validate data-employee-form>
        @csrf
        @include('admin.employees._form')
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.employees.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('employees.form.save_new') }}</x-button>
        </x-form.actions>
    </form>
@endsection
