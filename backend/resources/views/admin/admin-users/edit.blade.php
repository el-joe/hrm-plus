@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('app.admin_users.title'), 'url' => route('admin.admin-users.index')],
    ['label' => $admin->name, 'url' => route('admin.admin-users.show', $admin)],
]])

@section('title', __('app.admin_users.edit'))

@section('content')
    <form method="POST" action="{{ route('admin.admin-users.update', $admin) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @method('PUT')

        @include('admin.admin-users._form')

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.admin-users.show', $admin)">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
