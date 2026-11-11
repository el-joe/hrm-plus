@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('app.roles.title'), 'url' => route('admin.roles.index')],
]])

@section('title', __('app.roles.create'))

@section('content')
    <form method="POST" action="{{ route('admin.roles.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf

        @include('admin.roles._form')

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.roles.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
