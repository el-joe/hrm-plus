@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('app.admin_users.title'), 'url' => route('admin.admin-users.index')],
]])

@section('title', $admin->name)

@section('page-actions')
    @can('update', $admin)
        <x-button :href="route('admin.admin-users.edit', $admin)" icon="pencil">{{ __('app.actions.edit') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 lg:gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="flex min-w-0 flex-col gap-4 lg:gap-6">
            <x-card :title="__('app.admin_users.details')" data-section="details">
                <div class="mb-5 flex items-center gap-4">
                    <x-avatar :name="$admin->name" size="lg" />
                    <div class="min-w-0">
                        <p class="truncate text-h4">{{ $admin->name }}</p>
                        <p class="truncate text-body-sm text-text-tertiary">{{ $admin->email }}</p>
                    </div>
                </div>
                <x-detail-list>
                    <x-detail-item :label="__('app.common.email')">{{ $admin->email }}</x-detail-item>
                    <x-detail-item :label="__('app.common.status')">@include('admin.admin-users._status', ['status' => $admin->status])</x-detail-item>
                    <x-detail-item :label="__('app.admin_users.employee_id')">
                        @if ($linkedEmployee)
                            @can('view', $linkedEmployee)
                                <a href="{{ route('admin.employees.show', $linkedEmployee) }}" class="text-text-link hover:underline">{{ $linkedEmployee->label() }}</a>
                            @else
                                {{ $linkedEmployee->label() }}
                            @endcan
                        @else
                            {{ __('app.common.none') }}
                        @endif
                    </x-detail-item>
                    <x-detail-item :label="__('app.admin_users.last_login')">{{ $admin->last_login_at?->format('Y-m-d H:i') ?? __('app.admin_users.never') }}</x-detail-item>
                </x-detail-list>
            </x-card>

            <x-card :title="__('app.admin_users.recent_logins')" padding="none" data-section="recent-logins">
                <x-slot:actions>
                    <x-button variant="ghost" size="sm" :href="route('admin.admin-users.login-history', $admin)" icon-end="chevron-right">{{ __('app.admin_users.view_all_logins') }}</x-button>
                </x-slot:actions>
                <div class="px-5 pb-5">
                    @include('admin.admin-users._logins-table', ['logins' => $recentLogins])
                </div>
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('app.admin_users.roles')" data-section="roles">
                <div class="flex flex-wrap gap-2">
                    @forelse ($admin->roles as $role)
                        <x-badge tone="primary" data-badge="role">{{ $role->displayName() }}</x-badge>
                    @empty
                        <p class="text-body-sm text-text-tertiary">{{ __('app.common.none') }}</p>
                    @endforelse
                </div>
            </x-card>

            <x-card :title="__('app.admin_users.data_scopes')" data-section="data-scopes">
                @if ($scopes === [])
                    <x-badge tone="success" dot>{{ __('app.admin_users.unrestricted') }}</x-badge>
                @else
                    <x-detail-list :columns="1">
                        @foreach ($scopeTypes as $type)
                            @isset($scopes[$type->value])
                                <x-detail-item :label="$type->label()">{{ implode(__('structure.messages.list_separator'), $scopeLabels[$type->value]) }}</x-detail-item>
                            @endisset
                        @endforeach
                    </x-detail-list>
                @endif
            </x-card>

            <x-card :title="__('app.admin_users.permissions')" :subtitle="trans_choice('app.admin_users.permissions_count', count($permissions), ['count' => count($permissions)])" data-section="permissions">
                @if (count($permissions) === 0)
                    <p class="text-body-sm text-text-tertiary">{{ __('app.common.none') }}</p>
                @else
                    <ul class="flex max-h-96 flex-wrap gap-1.5 overflow-y-auto" dir="ltr">
                        @foreach ($permissions as $permission)
                            <li><code class="inline-block rounded-xs bg-surface-muted px-1.5 py-0.5 text-caption-md text-text-secondary">{{ $permission }}</code></li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
@endsection
