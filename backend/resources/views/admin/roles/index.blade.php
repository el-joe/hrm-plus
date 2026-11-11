@extends('layouts.admin', ['subtitle' => __('app.roles.subtitle')])

@section('title', __('app.roles.title'))

@section('page-actions')
    @can('create', App\Models\Role::class)
        <x-button :href="route('admin.roles.create')" icon="plus">{{ __('app.roles.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-table data-region="table" :caption="__('app.roles.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('app.common.name') }}</x-table.th>
                <x-table.th>{{ __('app.roles.type') }}</x-table.th>
                <x-table.th class="text-end">{{ __('app.roles.permissions_count') }}</x-table.th>
                <x-table.th class="text-end">{{ __('app.roles.users_count') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($roles as $role)
            <x-table.row>
                <x-table.td>
                    <p class="text-label-md text-text-primary">{{ $role->displayName() }}</p>
                    @if ($role->description)
                        <p class="text-caption-md text-text-tertiary">{{ $role->description }}</p>
                    @endif
                </x-table.td>
                <x-table.td :label="__('app.roles.type')">
                    <x-badge :tone="$role->is_system ? 'info' : 'neutral'">{{ $role->is_system ? __('app.roles.system') : __('app.roles.custom') }}</x-badge>
                </x-table.td>
                <x-table.td :label="__('app.roles.permissions_count')" class="text-end text-numeric-md">{{ $role->permissions_count }}</x-table.td>
                <x-table.td :label="__('app.roles.users_count')" class="text-end text-numeric-md">{{ $role->users_count }}</x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        @can('update', $role)
                            <x-icon-button icon="pencil" size="sm" :href="route('admin.roles.edit', $role)" :label="__('app.actions.edit')" />
                        @endcan
                        @can('delete', $role)
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" data-confirm="{{ __('app.roles.confirm_delete') }}"
                                data-confirm-title="{{ __('app.actions.delete') }}: {{ $role->displayName() }}" data-confirm-label="{{ __('app.actions.delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endcan
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="shield" />
        @endforelse
    </x-table>
@endsection
