@extends('layouts.admin', ['subtitle' => __('app.dashboard.subtitle')])

@section('title', __('app.dashboard.welcome', ['name' => $user->name]))

@section('page-actions')
    <x-button variant="ghost" icon="refresh" :href="route('admin.dashboard', [...request()->except('refresh'), 'refresh' => 1])" data-dashboard-refresh>{{ __('dashboard.actions.refresh') }}</x-button>
    <x-button variant="secondary" icon="settings" :href="route('admin.dashboard.customise')">{{ __('dashboard.actions.customise') }}</x-button>
@endsection

@section('content')
    @if ($user->can('dashboard.view'))
        <x-filter-bar :action="route('admin.dashboard')" :reset="route('admin.dashboard')" data-dashboard-filters>
            <x-form.input name="from" type="date" :label="__('reports.filters.from')" :value="$filters['from']" class="md:w-40" />
            <x-form.input name="to" type="date" :label="__('reports.filters.to')" :value="$filters['to']" class="md:w-40" />
            @foreach (['branch_id' => [$branches, 'branch'], 'department_id' => [$departments, 'department'], 'group_id' => [$groups, 'employee_group']] as $name => [$records, $label])
                @if ($records->isNotEmpty())
                    <x-form.select :name="$name" :label="__('reports.filters.'.$label)" class="md:w-44">
                        <option value="">{{ __('app.common.all') }}</option>
                        @foreach ($records as $record)
                            <option value="{{ $record->id }}" @selected((string) ($filters[$name] ?? '') === (string) $record->id)>{{ $record->code }} · {{ $record->name }}</option>
                        @endforeach
                    </x-form.select>
                @endif
            @endforeach
        </x-filter-bar>
    @endif

    @if ($widgets !== [])
        <section aria-label="{{ __('app.dashboard.overview') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:gap-6 xl:grid-cols-4" data-section="widgets">
            @foreach ($widgets as $widget)
                @include('admin.dashboard._widget', ['widget' => $widget])
            @endforeach
        </section>
    @elseif ($user->can('dashboard.view'))
        <x-empty-state icon="layout-grid" :title="__('dashboard.empty.no_widgets')" :description="__('dashboard.empty.no_widgets_help')">
            <x-button variant="secondary" :href="route('admin.dashboard.customise')">{{ __('dashboard.actions.customise') }}</x-button>
        </x-empty-state>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        @if ($recentActivity !== null)
            <x-card :title="__('app.dashboard.recent_activity')" padding="none" data-section="recent-activity">
                <x-slot:actions>
                    <x-button variant="ghost" size="sm" :href="route('admin.audit-logs.index')" icon-end="chevron-right">{{ __('app.dashboard.view_all') }}</x-button>
                </x-slot:actions>

                @if ($recentActivity->isEmpty())
                    <x-empty-state icon="history" :title="__('app.dashboard.no_activity')" />
                @else
                    <ul class="divide-y divide-border-subtle border-t border-border-subtle">
                        @foreach ($recentActivity as $log)
                            <li>
                                <a href="{{ route('admin.audit-logs.show', $log) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-subtle">
                                    <x-avatar :name="$log->user?->name ?? __('app.audit.system')" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-label-md text-text-primary">{{ $log->user?->name ?? __('app.audit.system') }}</p>
                                        <p class="truncate text-caption-md text-text-tertiary"><span title="{{ $log->event }}">{{ $log->label() }}</span>
                                            @if ($log->auditable_type) · {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }} @endif
                                        </p>
                                    </div>
                                    <x-date :value="$log->created_at" format="datetime" class="shrink-0 text-caption-md text-text-tertiary" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endif

        <div @class(['flex flex-col gap-4 lg:gap-6', 'xl:col-span-2' => $recentActivity === null])>
            <x-card :title="__('app.dashboard.your_roles')" data-section="roles">
                <div class="flex flex-wrap gap-2">
                    @forelse ($user->roles as $role)
                        <x-badge tone="primary" data-badge="role">{{ $role->displayName() }}</x-badge>
                    @empty
                        <p class="text-body-sm text-text-tertiary">{{ __('app.dashboard.no_roles') }}</p>
                    @endforelse
                </div>
            </x-card>

            <x-card :title="__('app.dashboard.your_access')" data-section="data-scopes">
                @if ($user->hasDataRestrictions())
                    <p class="mb-3 text-body-sm text-text-secondary">{{ __('app.dashboard.restricted_to') }}</p>
                    <x-detail-list :columns="1">
                        @foreach ($dataScopes as $type => $labels)
                            <x-detail-item :label="__('app.data_scope_types.'.$type)">{{ implode(__('structure.messages.list_separator'), $labels) }}</x-detail-item>
                        @endforeach
                    </x-detail-list>
                @else
                    <x-badge tone="success" dot>{{ __('app.admin_users.unrestricted') }}</x-badge>
                @endif
            </x-card>
        </div>
    </div>
@endsection
