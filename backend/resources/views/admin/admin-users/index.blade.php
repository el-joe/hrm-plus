@extends('layouts.admin', ['subtitle' => __('app.admin_users.subtitle')])

@section('title', __('app.admin_users.title'))

@section('page-actions')
    @can('create', App\Models\User::class)
        <x-button :href="route('admin.admin-users.create')" icon="plus">{{ __('app.admin_users.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.admin-users.index')" :reset="route('admin.admin-users.index')" data-region="filters">
        <x-form.input name="search" id="search" type="search" icon="search" :label="__('app.common.search')" hide-label
            :placeholder="__('app.admin_users.search_placeholder')" :value="$filters['search'] ?? ''" class="md:w-72" data-filter-debounce />

        <x-form.select name="status" id="status" :label="__('app.common.status')" hide-label class="md:w-44">
            <option value="">{{ __('app.common.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>

        <x-form.select name="role" id="role" :label="__('app.admin_users.roles')" hide-label class="md:w-52">
            <option value="">{{ __('app.admin_users.roles') }}: {{ __('app.common.all') }}</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) ($filters['role'] ?? '') === (string) $role->id)>{{ $role->displayName() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table data-region="table" :caption="__('app.admin_users.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-64">{{ __('app.common.name') }}</x-table.th>
                <x-table.th>{{ __('app.admin_users.roles') }}</x-table.th>
                <x-table.th>{{ __('app.common.status') }}</x-table.th>
                <x-table.th>{{ __('app.admin_users.last_login') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($admins as $admin)
            <x-table.row>
                <x-table.td>
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$admin->name" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.admin-users.show', $admin) }}" class="block truncate text-label-md text-text-primary hover:text-text-link">{{ $admin->name }}</a>
                            <p class="truncate text-caption-md text-text-tertiary">{{ $admin->email }}</p>
                        </div>
                    </div>
                </x-table.td>
                <x-table.td :label="__('app.admin_users.roles')">
                    <div class="flex flex-wrap gap-1">
                        @foreach ($admin->roles as $role)
                            <x-badge>{{ $role->displayName() }}</x-badge>
                        @endforeach
                    </div>
                </x-table.td>
                <x-table.td :label="__('app.common.status')">
                    @include('admin.admin-users._status', ['status' => $admin->status])
                </x-table.td>
                <x-table.td :label="__('app.admin_users.last_login')" class="whitespace-nowrap text-text-secondary">
                    {{ $admin->last_login_at?->format('Y-m-d H:i') ?? __('app.admin_users.never') }}
                </x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        <x-icon-button icon="eye" size="sm" :href="route('admin.admin-users.show', $admin)" :label="__('app.actions.view')" />
                        @can('update', $admin)
                            <x-icon-button icon="pencil" size="sm" :href="route('admin.admin-users.edit', $admin)" :label="__('app.actions.edit')" />
                        @endcan
                        @can('delete', $admin)
                            <form method="POST" action="{{ route('admin.admin-users.destroy', $admin) }}" data-confirm="{{ __('app.admin_users.confirm_delete') }}"
                                data-confirm-title="{{ __('app.actions.delete') }}: {{ $admin->name }}" data-confirm-label="{{ __('app.actions.delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endcan
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="users" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$admins" />
        </x-slot:footer>
    </x-table>
@endsection
